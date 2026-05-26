## Why

Para vender mango-app como SaaS, cada empresa debe tener su propio subdominio (`{slug}.webplena.com`). Hoy el tenant se deriva solo del usuario autenticado (`auth()->user()->company_id`); no existe noción de "tenant actual" a partir de la URL. Esto impide aislar el acceso por empresa antes del login y dar a cada cliente una dirección propia. La infraestructura (cert wildcard ACM, CloudFront, DNS wildcard en Cloudflare) ya está montada y verificada en `prueba.webplena.com`; falta que la aplicación reconozca el subdominio.

Análisis completo y decisiones en `docs/analysis/multitenancy-subdominios.md`.

## What Changes

- Resolver el **tenant actual a partir del host** de la petición mediante un middleware `IdentifyTenant` (lee `$request->getHost()`), **no** con `Route::domain()` (evita romper Wayfinder y las rutas existentes).
- El **subdominio = el `slug`** existente de `companies`. No se crea columna nueva para identificar el tenant.
- **Hosts no-tenant** (reservados, nunca se interpretan como slug):
  - `webplena.com` / `www` → landing pública (sin tenant, sin login).
  - `admin.webplena.com` → panel y login del `super-admin` de la plataforma (`company_id = null`).
- **Gate de login por host**: en un subdominio de tenant solo pueden autenticarse usuarios de esa empresa; el `super-admin` solo en `admin.webplena.com`. **BREAKING**: cambia el comportamiento del login (un usuario ya no puede loguearse en cualquier host).
- **Defensa en profundidad**: el aislamiento de datos mantiene el `CompanyScope` actual (por `auth()->user()->company_id`) y además se endurece para preferir el tenant del subdominio cuando exista.
- **Infra/host**: CloudFront Function que copia el `Host` del viewer a `x-forwarded-host`, y `TrustProxies` en `bootstrap/app.php` (hoy no configurado) para que `getHost()` devuelva el subdominio real dentro de Lambda.
- **URLs sin request** (emails de la cola SQS, p. ej. reset password): fijar la raíz de URL del tenant al encolar (`URL::forceRootUrl("https://{slug}.webplena.com")`), porque en la Lambda `queue` no hay `Host` y `APP_URL` es único.
- Sin paquete de tenancy: implementación custom sobre el `CompanyScope` ya existente.

## Capabilities

### New Capabilities
- `tenant-subdomain-resolution`: identificar la `Company` actual desde el subdominio del host; tratar el dominio central y subdominios reservados como "sin tenant"; responder 404 ante un subdominio desconocido; endurecer el aislamiento de datos para preferir el tenant resuelto; generar URLs correctas del tenant en contextos sin request (emails encolados).
- `tenant-scoped-login`: restringir la autenticación para que un usuario solo pueda iniciar sesión en el subdominio de su propia empresa, y el `super-admin` solo en el dominio central.

### Modified Capabilities
- `super-admin-company-creation`: el `slug` de la empresa pasa a ser su subdominio público, por lo que debe ser una etiqueta DNS-safe, única y estable. (La decisión sobre el sufijo random del slug —dejarlo, quitarlo, o hacerlo editable— se resuelve en design.)

## Impact

- **Dominios afectados:** Shared (nuevo middleware `IdentifyTenant`, endurecimiento de `CompanyScope`), Company (reglas del `slug` en la creación de empresas).
- **Código:**
  - `bootstrap/app.php` — `trustProxies(...)` + registro/uso del middleware `IdentifyTenant` en el grupo `web`.
  - `app/Domain/Shared/` — `IdentifyTenant` y posible helper de "current tenant" (singleton/`Context`).
  - `app/Domain/Shared/Scopes/CompanyScope.php` — preferir tenant del subdominio (defensa en profundidad).
  - Autenticación Fortify (`FortifyServiceProvider` / acción de login) — gate por subdominio.
  - Notifications/Mailables de reset password — `URL::forceRootUrl` por tenant.
  - `app/Domain/Company/Actions/CreateCompanyWithAdmin.php` y su Form Request — reglas del slug como subdominio.
- **Infra (fuera del repo, ya parcialmente hecha):** cert wildcard ACM ✅, CloudFront alt domains ✅, DNS wildcard ✅; pendiente CloudFront Function `Host→x-forwarded-host`.
- **Roles:** `super-admin` (solo `admin.webplena.com`, `company_id=null`), `admin`/`employee` (solo el subdominio de su empresa).
- **Migración de BD:** **no requerida** — el `slug` ya existe (`NOT NULL`, único) en todas las empresas, por lo que no hay backfill. (Un eventual `custom_domain` para dominios propios queda fuera de alcance; ver Non-goals.)
- **Dependencias:** ninguna nueva. No se añade paquete de tenancy.

## Non-goals

- **Dominios propios por tenant** (`reservas.elmango.com` en vez de subdominio). El resolver se diseñará para poder extenderse a esto, pero no se implementa ahora.
- **Base de datos por tenant** o cualquier cambio al modelo single-DB row-scoped.
- **Rediseño de la landing** o creación de una landing nueva (hoy el apex sirve la app).
- **Entorno de desarrollo local** para subdominios (estrategia `*.test`/`lvh.me`): se documenta aparte, no es parte del entregable de código.
- **Migración de empresas existentes**: innecesaria al usar el slug.
