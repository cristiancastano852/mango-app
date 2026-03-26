# Deploy: Laravel + Inertia + Bref v3 en AWS Lambda

**Stack:** Laravel 12 · Inertia Vue 3 · Bref v3 · Supabase PostgreSQL · Upstash Redis · SQS · SES · GitHub Actions OIDC

**Sin VPC.** Tanto Supabase como Upstash tienen endpoints públicos. Sin VPC no hay NAT Gateway (~$35-70/mes de ahorro).

> **Importante sobre assets estáticos:** Bref's PHP-FPM no sirve archivos estáticos — todo request pasa por PHP/Laravel. Los assets de Vite (JS/CSS) deben ir a **S3 + CloudFront** vía el construct `server-side-website` de serverless-lift. CloudFront enruta `/build/*` a S3 y el resto a Lambda.

---

## Arquitectura

```
Internet
   │
   ▼
Cloudflare (DNS + CDN + DDoS)
   │  CNAME → CloudFront
   ▼
CloudFront (serverless-lift)
   │
   ├── /build/* → S3 (assets JS/CSS de Vite, sin pasar por Lambda)
   │
   └── /* → API Gateway (HTTP API)
                  │
                  ▼
            Lambda: web (php-84-fpm, 28s)
            │   handler: public/index.php
            │
            ├── dispatch(Job)
            ▼
SQS Queue (mango-app-{stage}-jobs)
   │                          ┌─ DLQ (después de 3 reintentos)
   ▼                          │
Lambda: queue (php-84, 60s) ──┘

EventBridge (rate: 1 minute)
   │   input: "schedule:run"
   ▼
Lambda: artisan (php-84-console, 300s)

          ┌───────────────────────┐    ┌──────────────────────┐
          │  Supabase PostgreSQL  │    │    Upstash Redis      │
          │  (público, port 6543) │    │  (público, TLS 6379)  │
          └───────────────────────┘    └──────────────────────┘

          ┌───────────────────────┐    ┌──────────────────────┐    ┌──────────────────────┐
          │  S3 assets (Lift)     │    │  S3 storage (Lift)   │    │   DynamoDB           │
          │  (JS/CSS vía CF)      │    │  (archivos usuarios) │    │   (failed jobs)      │
          └───────────────────────┘    └──────────────────────┘    └──────────────────────┘
```

---

## Costos estimados

| Servicio | Costo |
|---|---|
| Lambda + API Gateway | ~$0 (free tier: 1M requests/mes) |
| Supabase | $0 (free) / $25 (Pro) |
| Upstash Redis | $0 (free tier) |
| SQS | ~$0 (free tier: 1M requests/mes) |
| DynamoDB | ~$0 (pay per request) |
| S3 (assets + storage) | ~$0-2 |
| CloudFront | ~$0 (free tier: 1TB + 10M requests/mes) |
| SES | $0.10/1000 emails |
| ACM (SSL) | **$0** (gratis con CloudFront) |
| Dominio | ~$1/mes |
| **Total** | **$1-28/mes** |

---

## Requisitos Previos

- PHP 8.2+ y Composer instalados localmente
- Node.js 22 y npm
- Serverless Framework: `npm install -g serverless`
- Cuenta de AWS con credenciales configuradas (`aws configure`)
- Cuenta de Supabase con proyecto creado
- Cuenta de Upstash con base de datos Redis creada

---

## Fase 1 — Preparar el Proyecto

### 1.1 Instalar paquetes PHP

```bash
composer require bref/bref:^3 bref/laravel-bridge:^3 --update-with-dependencies
composer require league/flysystem-aws-s3-v3
```

### 1.2 Instalar plugin Serverless

```bash
npm install --save-dev serverless-lift
```

### 1.3 Habilitar extensión Redis en Lambda

Bref v3 escanea `php/conf.d/` automáticamente en cada invocación.

```bash
mkdir -p php/conf.d
echo "extension=redis" > php/conf.d/php.ini
```

### 1.4 Deshabilitar SSR de Inertia

En Lambda no puede correr el servidor Node.js de SSR. En `vite.config.ts` quitar la línea `ssr`:

```ts
laravel({
    input: ['resources/js/app.ts'],
    // ssr: 'resources/js/ssr.ts',  ← eliminado
    refresh: true,
}),
```

### 1.5 Agregar al `.gitignore`

```
.serverless
```

---

## Fase 2 — Configurar Laravel para Lambda

### 2.1 `config/services.php` — SES con token IAM

```php
'ses' => [
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'token'  => env('AWS_SESSION_TOKEN'),
    'options' => [
        'FromEmailAddressIdentityArn' => env('AWS_SES_ARN'),
    ],
],
```

### 2.2 `config/database.php` — Redis con TLS para Upstash

Agregar `scheme` a las conexiones `default` y `cache`:

```php
'default' => [
    'scheme'   => env('REDIS_SCHEME', 'tcp'),  // ← agregar
    'url'      => env('REDIS_URL'),
    'host'     => env('REDIS_HOST', '127.0.0.1'),
    // ... resto sin cambios
],

'cache' => [
    'scheme'   => env('REDIS_SCHEME', 'tcp'),  // ← agregar
    'url'      => env('REDIS_URL'),
    'host'     => env('REDIS_HOST', '127.0.0.1'),
    // ... resto sin cambios
],
```

### 2.3 `config/queue.php` — SQS con token IAM + DynamoDB para failed jobs

Agregar `token` a la conexión SQS:

```php
'sqs' => [
    'driver' => 'sqs',
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'token'  => env('AWS_SESSION_TOKEN'),  // ← agregar
    // ... resto sin cambios
],
```

Actualizar la sección `failed` para soportar DynamoDB:

```php
'failed' => [
    'driver'   => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'sqlite'),
    'table'    => env('QUEUE_FAILED_TABLE', 'failed_jobs'),
    'region'   => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'key'      => env('AWS_ACCESS_KEY_ID'),
    'secret'   => env('AWS_SECRET_ACCESS_KEY'),
],
```

> En Lambda: `QUEUE_FAILED_DRIVER=dynamodb`. En local: `database-uuids` (sin cambios).

### Por qué es necesario configurar los campos aunque BrefServiceProvider los inyecta

`bref/laravel-bridge` incluye un `BrefServiceProvider` que en runtime inyecta `AWS_SESSION_TOKEN` en las configuraciones de SQS, S3, SES y DynamoDB. Pero necesita que los campos `key`, `secret`, `region` ya existan en el array de config para poder completarlos. Si no existen, BrefServiceProvider no puede inyectar el token y las llamadas a AWS fallan.

---

## Fase 3 — Servicios externos

### 3.1 Supabase PostgreSQL

1. Ir a Supabase → Project Settings → Database → Connection string
2. Seleccionar **Transaction pooler** (modo recomendado para serverless)
3. Copiar el host (formato: `aws-0-region.pooler.supabase.com`)
4. Puerto: **6543**
5. `DB_SSLMODE=require`

```env
DB_CONNECTION=pgsql
DB_HOST=aws-0-us-east-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.tu-project-ref
DB_PASSWORD=tu-password
DB_SSLMODE=require
```

### 3.2 Upstash Redis

1. Ir a [upstash.com](https://upstash.com) → Create Database → Regional (misma región que Lambda)
2. Copiar: Endpoint, Port, Password
3. Upstash usa TLS por defecto en el puerto 6379

```env
REDIS_HOST=tu-endpoint.upstash.io
REDIS_PORT=6379
REDIS_PASSWORD=tu-password
REDIS_SCHEME=tls
REDIS_CLIENT=phpredis
REDIS_CACHE_DB=1
```

### 3.3 SES (correo)

1. AWS Console → SES → Verified identities → Create identity
2. Verificar el **dominio** desde el cual se enviarán correos
3. Copiar el **ARN** de la identidad → `AWS_SES_ARN`

> SES está en sandbox por defecto (solo envía a emails verificados). Para producción, solicitar salida del sandbox en AWS Console → SES → Account dashboard.

---

## Fase 4 — Variables de Entorno

Las variables llegan al Lambda a través del **`.env` empaquetado en el zip**. El archivo `.env.deployment` es la plantilla; `create_env_deploy.sh` lo convierte en `.env` inyectando los valores del CI/CD.

### `.env.deployment` (plantilla)

```env
APP_NAME
APP_ENV=production
APP_KEY
APP_URL
APP_DEBUG=false

LOG_CHANNEL=stderr

DB_CONNECTION=pgsql
DB_HOST
DB_PORT=6543
DB_DATABASE
DB_USERNAME
DB_PASSWORD
DB_SSLMODE=require

CACHE_STORE=redis

REDIS_HOST
REDIS_PORT=6379
REDIS_SCHEME=tls
REDIS_PASSWORD
REDIS_CACHE_DB=1
REDIS_CLIENT=phpredis
CACHE_PREFIX

SESSION_DRIVER=cookie
SESSION_ENCRYPT=true

QUEUE_CONNECTION=sqs
QUEUE_FAILED_DRIVER=dynamodb
SQS_QUEUE

FILESYSTEM_DISK=s3
AWS_BUCKET

MAIL_MAILER=ses
MAIL_FROM_ADDRESS
MAIL_FROM_NAME
AWS_SES_ARN

XDG_CONFIG_HOME=/tmp
```

### `create_env_deploy.sh`

```bash
#!/bin/bash
cat ./.env.deployment | while read line; do
  if [ "$line" != "" ]; then
    if [[ $line != *"="* ]]; then
      VAR=$(printenv $line)
      if [[ ! -n "${VAR}" ]]; then
        echo $line;
      else
        echo $line=\"$VAR\"
      fi
    else
      echo $line
    fi
  fi
done
```

Cómo funciona:
- Líneas con `=` (ej: `APP_ENV=production`) → se copian tal cual
- Líneas solo con nombre (ej: `APP_KEY`) → busca el valor en las variables de entorno del CI/CD y lo escribe como `APP_KEY="valor"`

---

## Fase 5 — `serverless.yml`

```yaml
service: mango-app

provider:
  name: aws
  region: ${opt:region, 'us-east-1'}
  stage: ${opt:stage, 'dev'}
  runtime: provided.al2023
  environment:
    XDG_CONFIG_HOME: /tmp
    SQS_QUEUE: !Ref JobsQueue
    QUEUE_FAILED_TABLE: !Ref FailedJobsTable

  iam:
    role:
      statements:
        - Effect: Allow
          Action:
            - ses:SendRawEmail
            - ses:SendEmail
            - dynamodb:DescribeTable
            - dynamodb:Query
            - dynamodb:Scan
            - dynamodb:GetItem
            - dynamodb:PutItem
            - dynamodb:UpdateItem
            - dynamodb:DeleteItem
          Resource: "*"
        - Effect: Allow
          Action:
            - sqs:SendMessage
          Resource: !GetAtt JobsQueue.Arn

package:
  patterns:
    - "!node_modules/**"
    - "!public/hot"
    - "!public/storage"
    - "!resources/assets/**"
    - "!storage/**"
    - "!tests/**"
    - "!vendor/**/test/**"
    - "!vendor/**/tests/**"
    - "!vendor/**/docs/**"
    - "!vendor/**/samples/**"
    - "!vendor/**/*.txt"
    - "!vendor/**/*.md"
    - "!vendor/**/*.csv"
    - "!vendor/**/phpunit.xml"
    - "!vendor/**/composer.lock"
    # public/build/ NO va en el zip — los assets van a S3 vía el construct website

functions:
  web:
    handler: public/index.php
    runtime: php-84-fpm
    timeout: 28
    events:
      - httpApi: "*"

  artisan:
    handler: artisan
    runtime: php-84-console
    timeout: 300
    events:
      - schedule:
          rate: rate(1 minute)
          input: '"schedule:run"'

  queue:
    handler: Bref\LaravelBridge\Queue\QueueHandler
    runtime: php-84
    timeout: 60
    events:
      - sqs:
          arn:
            Fn::GetAtt:
              - JobsQueue
              - Arn

plugins:
  - ./vendor/bref/bref
  - serverless-lift

constructs:
  website:
    type: server-side-website
    assets:
      '/build/*': './public/build'
      '/favicon.ico': './public/favicon.ico'
      '/favicon.svg': './public/favicon.svg'

  storage:
    type: storage

resources:
  Resources:
    JobsQueue:
      Type: AWS::SQS::Queue
      Properties:
        QueueName: ${self:service}-${self:provider.stage}-jobs
        VisibilityTimeout: 60
        RedrivePolicy:
          deadLetterTargetArn: !GetAtt JobsDeadLetterQueue.Arn
          maxReceiveCount: 3

    JobsDeadLetterQueue:
      Type: AWS::SQS::Queue
      Properties:
        QueueName: ${self:service}-${self:provider.stage}-jobs-dlq

    FailedJobsTable:
      Type: AWS::DynamoDB::Table
      Properties:
        TableName: ${self:service}-${self:provider.stage}-failed-jobs
        AttributeDefinitions:
          - AttributeName: application
            AttributeType: S
          - AttributeName: uuid
            AttributeType: S
        KeySchema:
          - AttributeName: application
            KeyType: HASH
          - AttributeName: uuid
            KeyType: RANGE
        BillingMode: PAY_PER_REQUEST
        SSESpecification:
          SSEEnabled: true
```

**Puntos clave:**
- **Sin VPC** — Supabase y Upstash son públicos; sin VPC no hay NAT Gateway
- **`SQS_QUEUE` y `QUEUE_FAILED_TABLE`** usan CloudFormation refs (`!Ref`) — se resuelven en el deploy
- **`public/build/` NO va en el zip** — los assets van a S3 vía el construct `website`; Bref's PHP-FPM no sirve archivos estáticos
- **`website` construct** — crea S3 + CloudFront; sube `public/build` automáticamente en cada deploy; el URL de CloudFront es el punto de entrada de la app
- **`storage` construct** — bucket S3 separado para archivos subidos por usuarios

---

## Fase 6 — GitHub Actions

### 6.1 OIDC en AWS (una sola vez)

1. AWS Console → IAM → Identity providers → Add provider
   - Provider URL: `https://token.actions.githubusercontent.com`
   - Audience: `sts.amazonaws.com`

2. Crear IAM Role con trust policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::ACCOUNT_ID:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": "repo:TU_ORG/mango-app:*"
        }
      }
    }
  ]
}
```

3. Adjuntar al role las siguientes políticas administradas por AWS (buscar cada una en el buscador de políticas):

| Política | Para qué |
|---|---|
| `AWSCloudFormationFullAccess` | Crear/actualizar el stack de CloudFormation |
| `AWSLambda_FullAccess` | Crear/actualizar las funciones Lambda |
| `AmazonAPIGatewayAdministrator` | Crear y configurar API Gateway |
| `AmazonS3FullAccess` | Bucket de deployment + storage de archivos |
| `AmazonSQSFullAccess` | Crear colas SQS (jobs y DLQ) |
| `AmazonDynamoDBFullAccess` | Tabla DynamoDB de failed jobs |
| `IAMFullAccess` | Serverless crea roles IAM para las Lambdas automáticamente |
| `CloudWatchLogsFullAccess` | Log groups de las funciones Lambda |
| `AmazonEventBridgeFullAccess` | El scheduler de Lambda usa EventBridge |
| `CloudFrontFullAccess` | crear funciones de CloudFront, que son necesarias para el construct server-side-website de Lift |




> **Nota sobre `IAMFullAccess`**: Es amplio pero necesario porque Serverless Framework crea un IAM role para las Lambdas durante el deploy. Sin este permiso el deploy falla. En entornos más maduros se puede reemplazar por una política inline más restrictiva.

### 6.2 Secrets y Variables en GitHub

**Settings → Secrets and variables → Actions → Environments** (crear `develop` y `production`):

**Secrets:**

| Secret | Descripción |
|---|---|
| `AWS_DEPLOY_ROLE_ARN` | ARN del IAM Role para OIDC |
| `APP_KEY` | Laravel app key (`base64:...`) |
| `DB_HOST` | Host de Supabase Transaction Pooler |
| `DB_DATABASE` | Nombre de la base de datos |
| `DB_USERNAME` | Usuario Supabase |
| `DB_PASSWORD` | Contraseña Supabase |
| `REDIS_HOST` | Endpoint Upstash |
| `REDIS_PASSWORD` | Password Upstash |
| `AWS_SES_ARN` | ARN de la identidad SES |

**Variables:**

| Variable | Ejemplo |
|---|---|
| `AWS_REGION` | `us-east-1` |
| `APP_NAME` | `mango-app` |
| `APP_URL` | `https://api.tu-dominio.com` |
| `REDIS_CACHE_DB` | `1` |
| `REDIS_CLIENT` | `phpredis` |
| `CACHE_PREFIX` | `mango` |
| `FILESYSTEM_DISK` | `s3` |
| `AWS_BUCKET` | (se obtiene del primer deploy) |
| `MAIL_FROM_ADDRESS` | `noreply@tu-dominio.com` |
| `MAIL_FROM_NAME` | `Mango App` |

> `AWS_BUCKET` se conoce después del primer deploy — verlo en el output de `serverless info`.

### 6.3 Workflow `.github/workflows/deploy.yml`

Ver el archivo en el repositorio. Se activa en push a `main` (→ production) y `develop` (→ dev).

**Pasos del pipeline:**
1. Setup PHP 8.4 con extensiones `pdo_pgsql`, `redis`
2. Configure AWS credentials via OIDC
3. `composer install --no-dev --optimize-autoloader`
4. `npm ci && npm run build` → genera `public/build/`
5. `serverless` install
6. `./create_env_deploy.sh > .env` → genera el `.env` con secretos
7. `php artisan config:clear`
8. `serverless deploy`
9. `serverless invoke -f artisan --data '"migrate --force"'`

---

## Fase 7 — Primer Deploy

### 7.1 Instalar dependencias de Bref

```bash
composer require bref/bref:^3 bref/laravel-bridge:^3 --update-with-dependencies
composer require league/flysystem-aws-s3-v3
npm install --save-dev serverless-lift
```

### 7.2 Build de assets

```bash
npm run build
```

### 7.3 Preparar `.env` para deploy local

Editar el `.env` con los valores del ambiente destino y luego:

```bash
serverless deploy --stage dev
```

O usando el script:

```bash
# Exportar variables de entorno y luego:
./create_env_deploy.sh > .env
serverless deploy --stage dev
```

**Output esperado:**

```
✔ Service deployed to stack mango-app-dev

endpoint: ANY - https://abc123.execute-api.us-east-1.amazonaws.com

functions:
  web:     mango-app-dev-web
  artisan: mango-app-dev-artisan
  queue:   mango-app-dev-queue
website:
  url:   https://d2x9u47fwwnfoi.cloudfront.net   ← este es el URL de la app
  cname: d2x9u47fwwnfoi.cloudfront.net
storage: mango-app-dev-storagebucket-xxxx
```

> **La URL de la app es la de CloudFront (`website.url`), NO la de API Gateway.** API Gateway es el backend interno; CloudFront enruta `/build/*` a S3 y el resto a Lambda. Actualizar `APP_URL` con la URL de CloudFront.

### 7.4 Ejecutar migraciones

```bash
serverless invoke -f artisan --data '"migrate --force"'
```

### 7.5 Ejecutar seeders

**Dev** — roles + datos demo (empresa, empleados, usuarios de prueba):

```bash
serverless invoke -f artisan --data '"db:seed --force"'
```

Usuarios creados por el DemoSeeder:
- Super Admin: `admin@mangoapp.co` / `password`
- Admin empresa: `carlos@elmango.co` / `password`
- Empleados: `maria@elmango.co`, `juan@elmango.co`, etc. / `password`

**Producción** — solo roles, sin datos demo:

```bash
serverless invoke -f artisan --data '"db:seed --class=RoleSeeder --force"'
```

### 7.6 Obtener el nombre del bucket S3

```bash
serverless info --stage dev
```

Buscar `storageBucketName` en el output → ese es el valor para `AWS_BUCKET`.

---

## Fase 8 — Custom Domain con Cloudflare + CloudFront

> Esta guía usa **Cloudflare como DNS** y **CloudFront como punto de entrada** (el construct `website` de Lift). No se usa Route 53 ni API Gateway custom domain.

### 8.1 Certificado SSL en ACM

**Obligatorio:** el certificado debe crearse en la región **`us-east-1`** — CloudFront solo acepta certificados de esa región.

```
AWS Console → ACM (región us-east-1) → Request a public certificate
→ Fully qualified domain names: tudominio.com
                                 www.tudominio.com
→ Validation method: DNS validation
→ Request
```

El certificado quedará en estado **"Pendiente de validación"**. AWS muestra 2 registros CNAME para validar la propiedad del dominio — los agregas en el siguiente paso.

### 8.2 Agregar los 4 registros DNS en Cloudflare

En Cloudflare → DNS → **Add record**. En total son 4 registros CNAME.

**Registros de validación ACM** (AWS los provee en la consola de ACM):

| Tipo | Name | Content | Proxy |
|------|------|---------|-------|
| CNAME | `_c8db04...` (el prefijo que da AWS) | `_769b7c....acm-validations.aws` | OFF (nube gris) |
| CNAME | `_f43d92....www` (el prefijo que da AWS) | `_427d46....acm-validations.aws` | OFF (nube gris) |

> En el campo **Name** de Cloudflare ingresa solo la parte antes de `.tudominio.com` — Cloudflare agrega el dominio automáticamente.

Espera 5–30 minutos hasta que el certificado cambie a estado **"Emitido"** en ACM.

**Registros que apuntan el dominio a CloudFront** (agregar una vez emitido el certificado):

| Tipo | Name | Content | Proxy |
|------|------|---------|-------|
| CNAME | `@` | `d2x9u47fwwnfoi.cloudfront.net` | OFF (nube gris) |
| CNAME | `www` | `d2x9u47fwwnfoi.cloudfront.net` | OFF (nube gris) |

> **Importante:** El proxy de Cloudflare debe estar en **OFF** (nube gris). Si se activa (nube naranja) habrá conflicto de SSL entre Cloudflare y CloudFront.

### 8.3 Configurar CloudFront para aceptar el dominio

```
AWS Console → CloudFront → tu distribución → Edit
→ Alternate domain names (CNAMEs):
    tudominio.com
    www.tudominio.com
→ Custom SSL certificate: seleccionar el certificado emitido en el paso 8.1
→ Save changes
```

CloudFront tarda ~5–15 minutos en propagar los cambios.

### 8.4 Actualizar APP_URL

En las variables de entorno de producción (GitHub Secrets o `.env`):

```env
APP_URL=https://www.tudominio.com
```

Re-deployar para que Lambda tome el nuevo `APP_URL`:

```bash
serverless deploy function -f web
```

---

## Fase 9 — Ver Logs

`serverless logs` en Bref v3 redirige a bref.sh/cloud (pago). Usar CloudWatch directamente.

Grupos de logs: `/aws/lambda/mango-app-{stage}-{function}`

```bash
# En tiempo real
aws logs tail /aws/lambda/mango-app-dev-web --follow --region us-east-1

# Últimos 30 minutos
aws logs tail /aws/lambda/mango-app-dev-web --since 30m --region us-east-1

# Filtrar errores
aws logs filter-log-events \
  --log-group-name /aws/lambda/mango-app-dev-web \
  --filter-pattern "ERROR" \
  --region us-east-1
```

---

## Referencia: Variables de Entorno por Ambiente

| Variable | Local | Lambda (dev/uat) | Lambda (prod) |
|---|---|---|---|
| `APP_ENV` | `local` | `production` | `production` |
| `APP_DEBUG` | `true` | `false` | `false` |
| `LOG_CHANNEL` | `stack` | `stderr` | `stderr` |
| `SESSION_DRIVER` | `database` | `cookie` | `cookie` |
| `SESSION_ENCRYPT` | — | `true` | `true` |
| `QUEUE_CONNECTION` | `database` | `sqs` | `sqs` |
| `QUEUE_FAILED_DRIVER` | `database-uuids` | `dynamodb` | `dynamodb` |
| `CACHE_STORE` | `redis`/`array` | `redis` | `redis` |
| `REDIS_SCHEME` | `tcp` | `tls` | `tls` |
| `DB_PORT` | `5432` | `6543` | `6543` |
| `DB_SSLMODE` | `prefer` | `require` | `require` |
| `FILESYSTEM_DISK` | `local` | `s3` | `s3` |
| `MAIL_MAILER` | `log` | `ses` | `ses` |
| `XDG_CONFIG_HOME` | — | `/tmp` | `/tmp` |

---

## Comandos Útiles

```bash
# Deploy completo
serverless deploy --stage dev

# Deploy solo una función (más rápido, para cambios de código)
serverless deploy function -f web
serverless deploy function -f artisan
serverless deploy function -f queue

# Invocar comandos artisan en Lambda
serverless invoke -f artisan --data '"migrate --force"'
serverless invoke -f artisan --data '"db:seed --class=NombreSeeder --force"'
serverless invoke -f artisan --data '"schedule:run"'
serverless invoke -f artisan --data '"route:list"'

# Info del stack
serverless info --stage dev

# Logs (CloudWatch)
aws logs tail /aws/lambda/mango-app-dev-web --follow --region us-east-1

# Eliminar stack completo (¡destruye todos los recursos!)
serverless remove --stage dev
```

---

## Fase 9b — Agregar Subdominios (sitio1.webplena.com)

> Para cada nuevo sitio o app que quieras alojar bajo el mismo dominio raíz.

### Paso 1 — Certificado wildcard en ACM (una sola vez)

Si ya tienes el certificado de `webplena.com`, crea uno adicional wildcard que cubra todos los subdominios. Solo necesitas hacerlo una vez — sirve para `sitio1.webplena.com`, `sitio2.webplena.com`, etc.

```
AWS Console → ACM (región us-east-1) → Request a public certificate
→ Fully qualified domain name: *.webplena.com
→ Validation method: DNS validation
→ Request
```

ACM te dará 1 registro CNAME de validación. Agrégalo en Cloudflare con proxy **OFF**:

```
Tipo:    CNAME
Name:    _xxxx (el prefijo que da AWS)
Content: _yyyy.acm-validations.aws
Proxy:   OFF
```

Espera a que el estado cambie a **"Emitido"**. Este certificado cubre cualquier subdominio de primer nivel (`*.webplena.com`), pero **no** el dominio raíz (`webplena.com`) — para ese sigue usando el certificado de la Fase 8.

### Paso 2 — Desplegar el nuevo sitio

Despliega tu nueva app normalmente (otro `serverless deploy`, una app diferente, etc.) y obtén su URL de CloudFront del output:

```
website:
  url:   https://d9xyz123abc.cloudfront.net   ← URL del nuevo sitio
  cname: d9xyz123abc.cloudfront.net
```

### Paso 3 — Configurar CloudFront del nuevo sitio

```
AWS Console → CloudFront → distribución del nuevo sitio → Edit
→ Alternate domain names (CNAMEs): sitio1.webplena.com
→ Custom SSL certificate: *.webplena.com  (el wildcard del Paso 1)
→ Save changes
```

### Paso 4 — Agregar CNAME en Cloudflare

Un solo registro por subdominio:

```
Tipo:    CNAME
Name:    sitio1
Content: d9xyz123abc.cloudfront.net
Proxy:   OFF (nube gris)
```

### Paso 5 — Actualizar APP_URL del nuevo sitio

```env
APP_URL=https://sitio1.webplena.com
```

---

### Resumen: certificados por escenario

| Dominio | Certificado a usar |
|---|---|
| `webplena.com` | Certificado exacto `webplena.com` (Fase 8) |
| `www.webplena.com` | Certificado exacto `webplena.com` (ya incluye www) |
| `sitio1.webplena.com` | Wildcard `*.webplena.com` |
| `sitio2.webplena.com` | Wildcard `*.webplena.com` |
| `sub.sitio1.webplena.com` | No cubierto por `*.webplena.com` — necesita certificado propio |

---

## Errores Comunes

| Problema | Causa | Solución |
|---|---|---|
| `Plugin "./vendor/bref/bref" not found` | Dependencias no instaladas | `composer install` |
| `502 Bad Gateway` | Error en Lambda | Revisar CloudWatch → `/aws/lambda/mango-app-dev-web` |
| `ErrorException: Undefined array key "key"` en BrefServiceProvider | `config/queue.php` sin `key` en sección `failed` | Agregar `key`, `secret`, `region` a `failed` (ver Fase 2.3) |
| Redis connection error | TLS no habilitado | Verificar `php/conf.d/php.ini` con `extension=redis` y `REDIS_SCHEME=tls` |
| `SQLSTATE[08006]` (PostgreSQL) | Puerto incorrecto | Supabase transaction pooler usa **6543**, no 5432 |
| Assets JS/CSS no cargan (404) | Bref PHP-FPM no sirve estáticos; accediendo por API Gateway en vez de CloudFront | Acceder por la URL de CloudFront (`website.url` del output). Los assets van a S3 vía construct `website`, no en el zip |
| `NoSuchBucket` en S3 | Bucket no creado | Ejecutar `serverless deploy` completo (no `deploy function`) |
| `SES MessageRejected` | Email no verificado | `MAIL_FROM_ADDRESS` debe usar dominio verificado en SES |
| Scheduler no ejecuta | EventBridge desconectado | Verificar función `artisan` deployada con el event `schedule` |
| `APPLICATION IN PRODUCTION. Command cancelled.` | Comando bloqueado | Agregar `--force` al comando |
| OIDC `Not authorized to perform sts:AssumeRoleWithWebIdentity` | Trust policy mal configurada | Verificar `sub` en trust policy: `repo:ORG/mango-app:*` |
| `Error: ENAMETOOLONG` en deploy | Zip excede límites | Revisar `package.patterns` y excluir `node_modules`, `tests` |
| `serverless logs` no muestra nada | Bref v3 redirige a bref.sh/cloud | Usar CloudWatch directamente |
