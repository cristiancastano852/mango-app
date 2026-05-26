## Context

mango-app es un SaaS multi-tenant single-DB: todas las tablas llevan `company_id` y el trait `BelongsToCompany` aplica `CompanyScope`, que filtra por `auth()->user()->company_id`. No hay paquete de tenancy. Hoy el tenant se deriva del usuario autenticado; no existe "tenant actual" a partir de la URL.

El objetivo es servir cada empresa en `{slug}.webplena.com`. La infra ya está montada y verificada: cert wildcard ACM (`webplena.com` + `*.webplena.com`), CloudFront `d2x9u47fwwnfoi` con esos alternate domains, y CNAME wildcard `*` en Cloudflare (DNS-only). `prueba.webplena.com` carga la app sin error de SSL. El deploy es serverless (Bref/Lambda): CloudFront → API Gateway HTTP API → Lambda `web`; los emails corren en una Lambda `queue` (SQS) sin contexto de request.

Análisis y decisiones de producto/infra en `docs/analysis/multitenancy-subdominios.md`.

Restricciones del proyecto: arquitectura de dominios (`app/Domain/...`), controllers delgados + Actions, Form Requests con mensajes, y reglas de multi-tenancy de `base-standards.mdc` (`super-admin` con `company_id=null` nunca recibe validaciones de company; `withoutGlobalScopes()` en tests/seeders/observers).

## Goals / Non-Goals

**Goals:**
- Resolver la `Company` actual desde el host de la petición, sin tocar las rutas existentes ni Wayfinder.
- Restringir el login al subdominio de la empresa del usuario; `super-admin` solo en el dominio central.
- Mantener y endurecer el aislamiento de datos (defensa en profundidad).
- Que `$request->getHost()` devuelva el subdominio real dentro de Lambda (host header a través de CloudFront).
- URLs correctas del tenant en emails encolados (reset password).
- Subdominios limpios para nuevas empresas, sin migración de las existentes.

**Non-Goals:**
- Dominios propios por tenant (`custom_domain`) — el resolver se diseña extensible, no se implementa.
- DB por tenant o cambios al modelo single-DB.
- Landing nueva o rediseño.
- Tooling de desarrollo local para subdominios (se documenta aparte).

## Decisions

### D1 — Resolución por host con middleware, no `Route::domain()`
Un middleware `IdentifyTenant` en el grupo `web` lee `$request->getHost()`, extrae la primera etiqueta y resuelve `Company::where('slug', $label)`. Se registra el resultado como "tenant actual" en el container (`app()->instance('tenant', $company)`) o vía `Context`.

- **Por qué:** las rutas y los helpers Wayfinder/`route()` generan URLs relativas que permanecen en el subdominio actual sin cambios. `Route::domain('{sub}.…')` obligaría a propagar un parámetro de subdominio por toda la app.
- **Alternativa descartada:** `Route::domain()` (invasivo); paquetes stancl/spatie (orientados a multi-DB, duplicarían el `CompanyScope`).

### D2 — Hosts no-tenant: público y admin
Hay tres clases de host, ninguno de los dos primeros resuelve a tenant:
- **Público:** `webplena.com` y `www` → landing pública, sin tenant y sin login.
- **Admin de plataforma:** `admin.webplena.com` → login y panel del `super-admin` (`company_id=null`). Las rutas `super-admin` (hoy bajo el prefijo `/super-admin`) se sirven en este host.
- **Tenant:** `{slug}.webplena.com`.

El conjunto se define en config (`config('tenancy.base_domain')`, `config('tenancy.public_hosts')` = `webplena.com`/`www`, `config('tenancy.admin_host')` = `admin.webplena.com`, `config('tenancy.reserved_subdomains')` incluyendo al menos `www` y `admin`). En hosts no-tenant, `IdentifyTenant` no fija tenant. Un subdominio que no corresponde a ninguna `Company` → **404**.

- **Por qué `admin.webplena.com` y no el apex:** separa el panel interno del super-admin de la landing pública de marketing que vivirá en el apex. El wildcard ya cubre `admin` sin infra extra; solo se reserva ese subdominio. Decisión confirmada por el usuario.

### D3 — Gate de login por host
La autenticación de Fortify se restringe así:
- En un subdominio de tenant: solo autentica usuarios con `company_id === tenant->id`. Un usuario de otra empresa o el `super-admin` → falla con error de credenciales/autorización, sin iniciar sesión.
- En `admin.webplena.com`: solo autentica `super-admin`; un `admin`/`employee` no puede loguearse ahí.
- En el host público (`webplena.com`/`www`): no hay login (solo landing).

Implementación con `Fortify::authenticateUsing()` (recibe el `Request`, tiene acceso al host/tenant) devolviendo `null` cuando el usuario no pertenece al contexto.

- **Por qué:** es el punto único por donde pasa el login; evita middleware adicional y mantiene el rate-limiting existente.
- **BREAKING:** cambia el comportamiento actual (hoy cualquier usuario puede loguearse en cualquier host).

### D4 — Defensa en profundidad en `CompanyScope`
`CompanyScope` se endurece para preferir el tenant resuelto del subdominio si existe; si no (CLI, central, jobs) cae al comportamiento actual (`auth()->user()->company_id`). El gate de login (D3) ya garantiza que `auth user.company_id === tenant`, así que ambos coinciden en peticiones normales; el endurecimiento contiene fugas si una de las dos capas fallara.

- **Por qué:** el usuario eligió defensa en profundidad. Una sola capa es frágil para datos multi-empresa.

### D5 — Host header en serverless (el riesgo técnico real)
CloudFront reescribe el `Host` al dominio del origen antes de API Gateway (por eso `prueba.webplena.com` no dio 403 pero Laravel aún no ve el subdominio). Solución:
1. **CloudFront Function** (viewer-request) que copia `Host` a `x-forwarded-host`. La origin request policy del construct `server-side-website` de Lift reenvía todos los headers excepto `Host`, así que `x-forwarded-host` llega a Lambda.
2. **`TrustProxies`** en `bootstrap/app.php` (hoy ausente) confiando en el proxy (`at: '*'`) e incluyendo `Request::HEADER_X_FORWARDED_HOST`, para que `$request->getHost()` use `x-forwarded-host`.

- **Spike obligatorio antes de codear el resto:** ruta temporal que vuelque `getHost()` + headers, accedida por `algo.webplena.com`, para confirmar que el subdominio llega.
- **Plan B:** API Gateway custom domain wildcard (`*.webplena.com`) que acepta el `Host` real — más gestión, solo si el header no pasa.

### D6 — URLs de tenant en contextos sin request (cola SQS)
Los emails (reset password) se procesan en la Lambda `queue`, sin `Host`; `APP_URL` es único. Al **encolar** una notificación de un tenant se fija la raíz de URL con el slug del tenant (`URL::forceRootUrl("https://{$slug}." . config('tenancy.base_domain'))`) dentro de la notification/mailable, o se construye el enlace con el slug. No depender de `APP_URL`.

### D7 — Slug como subdominio (resuelve la decisión pendiente)
El `slug` existente es el identificador del subdominio (sin columna nueva, sin migración). Para nuevas empresas el `super-admin` **puede indicar un subdominio explícito** en el formulario de creación; si lo omite, se autogenera desde el nombre **sin sufijo random**, agregando un sufijo numérico **solo en caso de colisión** (`elmango`, `elmango-2`). Validación: etiqueta DNS-safe (`^[a-z0-9]([a-z0-9-]*[a-z0-9])?$`, longitud ≤ 63), no reservada, única en `companies.slug`.

- **Por qué:** da subdominios limpios y comerciales para nuevos clientes sin romper los slugs existentes (que siguen siendo etiquetas DNS válidas) ni requerir migración.
- **Alternativas:** dejar el sufijo random siempre (feo); slug totalmente editable post-creación (riesgo de romper URLs/sesiones de un tenant en vivo — fuera de alcance).

## Risks / Trade-offs

- **El `Host` no llega a Lambda** → Mitigación: spike (D5) como primera tarea; plan B con API Gateway custom domain.
- **Cambiar un slug en vivo rompería el subdominio del tenant** → Mitigación: el slug es inmutable tras la creación en este alcance; edición de subdominio queda fuera.
- **`super-admin` intentando entrar a un subdominio de tenant** (o viceversa) → Mitigación: el gate (D3) lo bloquea explícitamente; cubrir con tests por rol.
- **Aislamiento de sesión entre subdominios** → ya correcto: `SESSION_DOMAIN` sin definir → cookies host-only. **No** poner `.webplena.com`.
- **Jobs/comandos sin tenant** podrían leer datos sin scope de subdominio → Mitigación: `CompanyScope` cae al comportamiento actual basado en auth; los jobs ya setean contexto por modelo.
- **Colisión de slug autogenerado bajo concurrencia** → Mitigación: unicidad a nivel de BD + reintento; baja probabilidad (creación la hace super-admin).

## Migration Plan

1. **Spike del Host** (CloudFront Function + `TrustProxies`) y verificación en `algo.webplena.com`.
2. Desplegar `IdentifyTenant` + endurecimiento de `CompanyScope` (sin gate aún) — comportamiento equivalente al actual si el tenant coincide con el auth user.
3. Activar el gate de login (D3) — cambio observable; comunicar a usuarios.
4. Reglas de slug en creación de empresas (D7) y URLs de email por tenant (D6).
5. **Rollback:** quitar el middleware del grupo `web` y revertir el gate de login restaura el comportamiento previo; la CloudFront Function y `TrustProxies` son inocuos si el middleware no está activo.

## Open Questions

- Dominio base `webplena.com` y hosts confirmados: público `webplena.com`/`www`, admin `admin.webplena.com`, tenants `{slug}.webplena.com`. Subdominios reservados: `www`, `admin` (¿añadir `app`/`api` preventivamente?).
- Edición del subdominio de un tenant existente: **fuera de alcance** (slug inmutable tras la creación). Si en el futuro se requiere renombrar, será un cambio aparte con tabla de alias/historial de slugs + redirección 301 del subdominio viejo al nuevo.
