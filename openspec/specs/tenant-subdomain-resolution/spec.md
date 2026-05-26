## ADDED Requirements

### Requirement: Resolución del tenant actual desde el subdominio

El sistema SHALL resolver la `Company` actual a partir del host de la petición HTTP usando un middleware en el grupo `web` que lee `$request->getHost()` y resuelve `Company` por `slug` igual a la primera etiqueta del host. La `Company` resuelta SHALL quedar disponible como "tenant actual" durante la petición.

**Business Rules:**
- El identificador de subdominio es el `companies.slug` (no se introduce columna nueva).
- Los hosts no-tenant NO resuelven a ningún tenant: el host público (`webplena.com`, `www`) y el host de administración de plataforma (`admin.webplena.com`). Los subdominios reservados incluyen al menos `www` y `admin`.
- Un subdominio que no corresponde a ninguna `Company` SHALL producir 404.
- La resolución ocurre antes de la autorización de la ruta, de modo que el gate de login y el aislamiento de datos puedan usar el tenant.

**Authorization:**
- Aplica a todas las peticiones `web`. No depende del rol; el tenant existe (o no) independientemente de quién esté autenticado.

#### Scenario: Subdominio válido resuelve a su empresa
- **WHEN** llega una petición a `restaurantex.webplena.com` y existe una `Company` con `slug = "restaurantex"`
- **THEN** el tenant actual de la petición es esa `Company`

#### Scenario: Subdominio inexistente responde 404
- **WHEN** llega una petición a `noexiste.webplena.com` y ningún `Company.slug` es `"noexiste"`
- **THEN** la respuesta es 404

#### Scenario: Host público no fija tenant
- **WHEN** llega una petición a `webplena.com`
- **THEN** no hay tenant actual y la petición se sirve como host público (landing)

#### Scenario: Host de administración no fija tenant
- **WHEN** llega una petición a `admin.webplena.com`
- **THEN** no hay tenant actual y la petición se sirve como host de super-admin

#### Scenario: Subdominio reservado no se interpreta como slug
- **WHEN** llega una petición a `www.webplena.com` o `admin.webplena.com`
- **THEN** no se intenta resolver una `Company` con `slug = "www"` ni `"admin"` y la petición se trata como host no-tenant

### Requirement: Aislamiento de datos reforzado por el tenant del subdominio

El sistema SHALL mantener el aislamiento por `company_id` mediante el `CompanyScope` existente, y SHALL preferir el tenant resuelto del subdominio cuando exista. En contextos sin tenant de subdominio (CLI, jobs, dominio central) el scope SHALL conservar el comportamiento actual basado en `auth()->user()->company_id`.

**Business Rules:**
- Defensa en profundidad: el gate de login garantiza que el usuario autenticado pertenece al tenant; el scope reforzado contiene fugas si una capa fallara.
- El `super-admin` (`company_id = null`) opera sin filtro de company, solo en el dominio central.

**Authorization:**
- `admin` / `employee`: ven exclusivamente datos de la `Company` de su subdominio.
- `super-admin`: sin filtro, solo en dominio central.

#### Scenario: Consultas filtradas al tenant del subdominio
- **WHEN** un `admin` del tenant A hace una consulta de un modelo con `BelongsToCompany` en `tenantA.webplena.com`
- **THEN** solo se devuelven registros con `company_id` del tenant A

#### Scenario: Sin contexto de subdominio se usa el company del usuario
- **WHEN** se ejecuta una consulta fuera de una petición de subdominio (p. ej. job en cola) con un usuario autenticado con `company_id`
- **THEN** el scope filtra por `auth()->user()->company_id` (comportamiento actual)

### Requirement: Generación de URLs del tenant sin contexto de request

El sistema SHALL generar enlaces apuntando al subdominio correcto del tenant en contextos sin petición HTTP (notificaciones/mailables encolados, p. ej. restablecer contraseña), fijando la raíz de URL con el `slug` del tenant en lugar de depender de `APP_URL`.

**Business Rules:**
- Los emails se procesan en una cola (SQS/Lambda `queue`) donde no existe `Host`; `APP_URL` es un valor único y no representa al tenant.

#### Scenario: Email de restablecimiento usa el subdominio del tenant
- **WHEN** se encola un correo de restablecimiento de contraseña para un usuario de la empresa con `slug = "restaurantex"`
- **THEN** el enlace del correo apunta a `https://restaurantex.webplena.com/...` y no a `APP_URL`

### Requirement: Preservación del host original en infraestructura serverless

El sistema SHALL recibir el host original del cliente (`{slug}.webplena.com`) dentro de la función Lambda, de modo que `$request->getHost()` devuelva el subdominio del tenant. Esto SHALL lograrse confiando en el proxy (`TrustProxies`) y leyendo el host reenviado por CloudFront como `x-forwarded-host`.

**Business Rules:**
- CloudFront reescribe el header `Host` al dominio del origen; el host del viewer se reenvía aparte (`x-forwarded-host`) mediante una CloudFront Function.
- Laravel SHALL confiar en el proxy e incluir `X-Forwarded-Host` para resolver el host real.

#### Scenario: getHost refleja el subdominio del tenant en Lambda
- **WHEN** un cliente accede a `restaurantex.webplena.com` a través de CloudFront → API Gateway → Lambda
- **THEN** `$request->getHost()` dentro de la aplicación devuelve `restaurantex.webplena.com`
