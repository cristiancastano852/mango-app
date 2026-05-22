# Incidente: Deploy accidental en cuenta AWS de empresa

**Fecha:** 2026-05-21  
**Cuenta AWS afectada:** `468913111314` (empresa)  
**Región:** `us-east-1`  
**Causa:** Se ejecutó `serverless deploy` localmente desde el computador del desarrollador, el cual tenía configuradas las credenciales AWS de la empresa en lugar de las personales. El proyecto `mango-app` debería desplegarse exclusivamente vía GitHub Actions en la cuenta personal.

**Stack CloudFormation creado:** `mango-app-dev`  
**Hora de creación:** 2026-05-21T17:56:13 UTC  
**Hora de última actualización:** 2026-05-21T17:58:03 UTC  
**Eliminado con:** `serverless remove --region us-east-1`

---

## Recursos eliminados

Todos los recursos fueron creados en el mismo evento y fueron eliminados el mismo día con `serverless remove`. No contenían datos de producción ni estaban integrados con otros sistemas de la empresa.

### Funciones Lambda (3)

| Nombre físico | ARN | Descripción |
|---|---|---|
| `mango-app-dev-web` | `arn:aws:lambda:us-east-1:468913111314:function:mango-app-dev-web:1` | Maneja peticiones HTTP vía PHP-FPM (Bref). Expuesta a través del API Gateway. |
| `mango-app-dev-artisan` | `arn:aws:lambda:us-east-1:468913111314:function:mango-app-dev-artisan:1` | Corre `php artisan schedule:run` cada minuto via EventBridge. |
| `mango-app-dev-queue` | `arn:aws:lambda:us-east-1:468913111314:function:mango-app-dev-queue:1` | Procesa jobs de SQS con el handler de Bref para Laravel queues. |

### Lambda Versions (3)
Versiones `$LATEST` de cada función. Se crean automáticamente con cada deploy de Serverless Framework.

### Lambda Permissions (2)
- `mango-app-dev-WebLambdaPermissionHttpApi-o9Lj0UzXTGRi` — Permite a API Gateway invocar `web`
- `mango-app-dev-ArtisanLambdaPermissionEventsRuleSchedule1-EqhQRhW6cSj6` — Permite a EventBridge invocar `artisan`

### Lambda Event Source Mapping (1)
- ID: `1d8c886c-6a9e-4737-938f-2e85dd79a8c4` — Conecta la cola SQS `mango-app-dev-jobs` con la Lambda `queue`

---

### API Gateway HTTP API (v2) (4 recursos)

| Tipo | ID físico | Descripción |
|---|---|---|
| `AWS::ApiGatewayV2::Api` | `7uy81abrm9` | API HTTP que recibe todo el tráfico web y lo reenvía a Lambda `web` |
| `AWS::ApiGatewayV2::Integration` | `e8zfjgo` | Integración entre el API y la Lambda `web` |
| `AWS::ApiGatewayV2::Route` | `ta11dq8` | Ruta `$default` que captura todos los paths (`*`) |
| `AWS::ApiGatewayV2::Stage` | `$default` | Stage de despliegue del API |

---

### CloudFront (3 recursos)

| Tipo | ID físico | Descripción |
|---|---|---|
| `AWS::CloudFront::Distribution` | `E2TV7LC3BBFMTZ` | CDN que sirve los assets estáticos desde S3 y enruta el tráfico dinámico al API Gateway |
| `AWS::CloudFront::Function` | `arn:aws:cloudfront::468913111314:function/mango-app-dev-us-east-1-website-request` | CloudFront Function que manipula las requests entrantes (reescritura de rutas para SPA) |
| `AWS::CloudFront::OriginAccessControl` | `E1NLAID34K96L2` | Control de acceso para que CloudFront lea el bucket S3 de assets sin hacerlo público |

---

### S3 Buckets (3)

| Nombre físico | Descripción |
|---|---|
| `mango-app-dev-serverlessdeploymentbucket-odvezz76t6eo` | Bucket interno de Serverless Framework para almacenar el artefacto del deploy (ZIP del código) |
| `mango-app-dev-storagebucketfbc61555-fvmvp2a5jg7d` | Bucket de storage de la app Laravel (equivalente a `storage/` para archivos en S3) |
| `mango-app-dev-websiteassets2a73bb69-8npkmlvrmxrn` | Bucket con los assets estáticos del frontend (build de Vite: JS, CSS, etc.) |

Cada bucket tenía su `BucketPolicy` asociada (también eliminada).

---

### SQS Queues (2)

| Nombre físico | URL | Descripción |
|---|---|---|
| `mango-app-dev-jobs` | `https://sqs.us-east-1.amazonaws.com/468913111314/mango-app-dev-jobs` | Cola principal de jobs de Laravel. La Lambda `queue` la escucha. |
| `mango-app-dev-jobs-dlq` | `https://sqs.us-east-1.amazonaws.com/468913111314/mango-app-dev-jobs-dlq` | Dead Letter Queue: recibe los jobs que fallaron 3 veces seguidas. |

---

### DynamoDB Table (1)

| Nombre físico | Descripción |
|---|---|
| `mango-app-dev-failed-jobs` | Tabla para registrar jobs fallidos de Laravel. Equivalente a la tabla `failed_jobs` de SQL. |

---

### IAM Role (1)

| Nombre físico | Descripción |
|---|---|
| `mango-app-dev-us-east-1-lambdaRole` | Rol de ejecución compartido por las 3 Lambdas. Tenía permisos para: enviar emails vía SES, operar la tabla DynamoDB `failed-jobs`, y enviar mensajes a la cola SQS `jobs`. |

---

### EventBridge Rule (1)

| Nombre físico | Descripción |
|---|---|
| `mango-app-dev-ArtisanEventsRuleSchedule1-27VAKlGaQH2c` | Regla `rate(1 minute)` que invocaba la Lambda `artisan` cada minuto para ejecutar el scheduler de Laravel. |

---

### CloudWatch Log Groups (3)

| Nombre físico | Descripción |
|---|---|
| `/aws/lambda/mango-app-dev-web` | Logs de la Lambda `web` |
| `/aws/lambda/mango-app-dev-artisan` | Logs de la Lambda `artisan` |
| `/aws/lambda/mango-app-dev-queue` | Logs de la Lambda `queue` |

---

## Impacto en otros proyectos de la empresa

**Ninguno.** Se verificó que todos los recursos tenían el prefijo exclusivo `mango-app-dev-` y el stack fue creado el mismo día del incidente. Los demás stacks activos en la cuenta no fueron tocados:

- `electronic-invoicing-api-dev`
- `fidubogota-dev`
- `tenants-admin-bref-dev`
- `tenants-admin-dev`
- `vapor-sandbox-vpc`
- `Infra-ECS-Cluster-unleash-232ec05c`

---

## Cómo evitar que vuelva a ocurrir

1. Nunca correr `serverless deploy` directamente desde el computador local de este proyecto.
2. El deploy de `mango-app` se hace **exclusivamente** vía GitHub Actions con las credenciales de la cuenta personal.
3. Si se necesita hacer un deploy de emergencia manual, verificar primero con `aws sts get-caller-identity` qué cuenta está activa antes de ejecutar cualquier comando de deploy.
