# Análisis: Multitenancy por subdominio (SaaS)

> **Estado:** análisis / exploración. **No implementado todavía.**
> **Fecha:** 2026-05-25
> **Objetivo:** Vender mango-app como SaaS donde cada empresa (tenant) tiene su propio subdominio (`restaurantex.midominio.com`). Al entrar a ese subdominio, el sistema debe saber a qué company corresponde, permitir login solo a usuarios de esa company, y mostrar únicamente datos de esa company.

---

## 1. Estado actual del sistema (lo que hay hoy)

### 1.1 Modelo de multitenancy

- **Single database, row-scoped por `company_id`.** No hay base de datos ni esquema por tenant. Todas las tablas comparten una sola BD y cada registro lleva `company_id`.
- **No se usa ningún paquete de tenancy** (ni `stancl/tenancy` ni `spatie/laravel-multitenancy`). Es 100% custom sobre Eloquent Global Scopes. El único paquete relacionado es `spatie/laravel-permission` v7, que se usa para roles (`super-admin`, `admin`, `employee`), no para tenancy.

### 1.2 Las 3 piezas del aislamiento

**1. `CompanyScope`** — `app/Domain/Shared/Scopes/CompanyScope.php`

Global scope que filtra cada query automáticamente:

```php
if (auth()->check() && auth()->user()->company_id) {
    $builder->where($model->getTable().'.company_id', auth()->user()->company_id);
}
```

**2. Trait `BelongsToCompany`** — `app/Domain/Shared/Traits/BelongsToCompany.php`

```php
public static function bootBelongsToCompany(): void
{
    static::addGlobalScope(new CompanyScope);

    static::creating(function ($model) {
        if (! $model->company_id && auth()->check()) {
            $model->company_id = auth()->user()->company_id;
        }
    });
}
```

- Registra el `CompanyScope` (filtra lecturas).
- En `creating`, auto-asigna `company_id` desde el usuario autenticado.
- Aporta la relación `company()`.

**3. Modelos que usan el trait (10):** `Department`, `Position`, `Schedule`, `Location`, `BreakType`, `BreakEntry`, `TimeEntry`, `Employee`, `SurchargeRule`, `Holiday`.

### 1.3 Las "anclas" (NO usan el trait)

- **`Company`**: es el tenant raíz, no se filtra a sí misma.
- **`User`**: tiene `company_id` pero NO usa el trait. Es el punto de origen del tenant (de él se lee el `company_id`). Por eso `SuperAdmin/CompanyController` usa `withoutGlobalScopes()` al listar usuarios de una empresa.

### 1.4 Super-admin (bypass del tenant)

- Se crea **sin `company_id`** (null) — ver `database/seeders/DemoSeeder.php:20-27`.
- Como `CompanyScope` exige `auth()->user()->company_id` truthy, con super-admin (null) **el scope no filtra** → ve todo, de todas las empresas.
- Protección de rutas vía middleware Spatie: `role:super-admin` y `role:admin|super-admin`.

### 1.5 Flujo de creación de un tenant (hoy)

1. Super-admin entra a `/super-admin/companies` → `CreateCompanyWithAdmin::execute()`.
2. En una transacción: crea `Company`, crea el `User` admin con ese `company_id`, le asigna rol `admin`.
3. `CompanyObserver::created()` dispara: crea su `SurchargeRule` por defecto + siembra festivos colombianos (`ColombianHolidaysSeeder::seedForCompany`).
4. Cuando ese admin se loguea y crea datos, el `company_id` se inyecta solo vía el trait.

### 1.6 Generación del slug (hoy)

`app/Domain/Company/Actions/CreateCompanyWithAdmin.php:17`:

```php
'slug' => Str::limit(Str::slug($data['company_name']), 248, '').'-'.Str::random(6),
// → "restaurante-el-mango-x7k2p9"
```

- `slug` es `NOT NULL` y único (sufijo random garantiza unicidad).

### 1.7 Datos relevantes de routing / auth / sesión

- **Rutas:** `routes/web.php`, `routes/settings.php`. Usan Wayfinder intensivamente en el frontend (URLs generadas desde rutas).
- **Precedente de tenant-por-URL:** el kiosko ya usa `kiosk/{company:slug}` en el path (`routes/web.php:25`). Los subdominios son la evolución natural.
- **Auth:** Laravel Fortify. Vistas e Inertia configuradas en `app/Providers/FortifyServiceProvider.php`. Login view + rate limiting ya personalizados.
- **Landing:** `LandingController` en `/` (`routes/web.php:21`).
- **Sesión:** `config/session.php`. `SESSION_DOMAIN` está **sin definir (null)** → cookies **host-only** → **cada subdominio tiene su propia sesión** (aislamiento correcto para tenants). ⚠️ Si se cambiara a `.midominio.com`, se compartiría sesión entre subdominios (rompe aislamiento).
- **Deploy:** `bref/bref` + `bref/laravel-bridge` → **se despliega en AWS Lambda (serverless)**. Esto condiciona fuertemente la infra de wildcard domains.

---

## 2. El cambio conceptual

El sistema actual **no tiene "tenant actual"**. El tenant se deriva del usuario logueado:

```
Petición → ¿auth? → user.company_id → CompanyScope filtra
```

Lo que se pide invierte el orden:

```
Petición → subdominio → company (define el tenant ANTES de auth)
                                  ↓
                        login gateado a esa company
```

Pasamos de **"el tenant es quien eres"** a **"el tenant es dónde estás"**. El aislamiento de datos (CompanyScope + trait) ya hace el 90% del trabajo; falta la **resolución de tenant por host** y el **gate de login**.

---

## 3. ¿Hace falta un paquete? — Decisión: NO

| Paquete | Para qué existe realmente | ¿Sirve aquí? |
|---|---|---|
| **stancl/tenancy** | Cambiar conexión de BD / cache / filesystem / queue por tenant (su fuerte es DB-per-tenant) | ❌ Pelearía contra el CompanyScope existente |
| **spatie/multitenancy** | "Tenant actual" + switch de conexión, orientado a multi-DB | ⚠️ Aporta el concepto de current tenant pero trae peso no usado |
| **Custom (nativo Laravel)** | `getHost()` + middleware + `CompanyScope` existente | ✅ Encaja exacto |

Los paquetes grandes brillan con **DB por tenant**. Aquí es single-DB row-scoped a propósito. Lo necesario (subdominio → company + gate de login) es nativo: ~150 líneas, no un paquete.

---

## 4. Bifurcación de diseño clave

### Opción A — `Route::domain('{subdomain}.midominio.com')`
- Cada ruta gana un parámetro `{subdomain}`.
- `route()` y Wayfinder necesitan pasar ese param siempre.
- Muy invasivo: toca casi todo el frontend. ❌

### Opción B — Middleware que lee `$request->getHost()` ✅ RECOMENDADO
- `IdentifyTenant` lee el host, resuelve la `Company`, la guarda como "current tenant" (singleton en container / `Context`).
- Las rutas NO cambian. Wayfinder/`route()` siguen generando URLs relativas → se quedan en el subdominio actual.
- Cambio quirúrgico. Es el patrón que usa `InitializeTenancyBySubdomain` de stancl por dentro.

```
                    ┌──────────────────────────────────┐
                    │  *.midominio.com  (wildcard DNS)   │
                    └──────────────────────────────────┘
                                   │
         ┌─────────────────────────┼──────────────────────────┐
         ▼                         ▼                            ▼
  midominio.com            admin.midominio.com         restaurantex.midominio.com
  (landing/marketing)      (super-admin, sin tenant)   (tenant: company X)
                                                                 │
                                                       ┌─────────▼──────────┐
                                                       │ IdentifyTenant MW  │
                                                       │ host → Company X   │
                                                       └─────────┬──────────┘
                                                                 ▼
                                                       ┌────────────────────┐
                                                       │ Login gate:        │
                                                       │ user.company_id != │
                                                       │ tenant → rechazar  │
                                                       └────────────────────┘
```

---

## 5. Piezas a construir (Opción B)

1. **Identificador de subdominio en `companies`** → se usará el `slug` existente (ver decisión 1).
2. **Middleware `IdentifyTenant`** → lee `getHost()`, extrae subdominio, resuelve `Company`, la registra como current tenant. 404 si no existe.
3. **Gate de login** → en la autenticación de Fortify (`Fortify::authenticateUsing` o middleware en la ruta de login), rechazar si `$user->company_id !== currentTenant()->id`.
4. **Endurecer `CompanyScope` (defensa en profundidad)** → preferir el tenant del subdominio si existe, además del de `auth()`.
5. **Dominio central** → mover landing (`LandingController`) y panel `super-admin` a `midominio.com` / `admin.midominio.com`, fuera de los subdominios de tenant.
6. **Reset password / emails** → los links deben apuntar al subdominio correcto del tenant, no al dominio central.

### A favor (ya construido)
- ✅ `CompanyScope` + `BelongsToCompany` (aislamiento de datos).
- ✅ `companies.slug` único y `NOT NULL`.
- ✅ Precedente kiosko `kiosk/{company:slug}`.
- ✅ Super-admin con `company_id = null` → encaja en dominio central.
- ✅ `SESSION_DOMAIN` null → sesiones host-only → aislamiento por subdominio correcto.

---

## 6. Decisiones tomadas

| # | Tema | Decisión |
|---|---|---|
| 1 | ¿Qué es el subdominio? | **El `slug`.** Como ya existe en toda company (NOT NULL, único), también elimina la necesidad de migrar las companies existentes. |
| 2 | ¿Gate único o defensa en profundidad? | **Ambos:** gate de subdominio + `CompanyScope` por auth. Si uno falla, el otro contiene la fuga. |
| 3 | ¿Dónde viven super-admin y marketing? | **Dominio central:** `midominio.com` (landing) y `admin.midominio.com` (super-admin), sin tenant. Tenants solo en `{sub}.midominio.com`. |
| 4 | Migración de companies existentes | **Innecesaria** gracias a la decisión 1 (el slug ya existe). |
| 5 | Links de reset password | Deben apuntar al subdominio del tenant. **Contemplado.** |
| 6 | Dominio propio a futuro | **Probable.** Diseñar `IdentifyTenant` para resolver por **host completo** desde ya: primero un eventual `custom_domain`, si no, el subdominio. Hoy solo se implementa la rama del subdominio, pero la forma del código contempla la futura (barato ahora, caro refactorizar después). |

---

## 7. Decisión pendiente: el sufijo random del slug

El slug actual es `restaurante-el-mango-x7k2p9`. Como subdominio → `restaurante-el-mango-x7k2p9.midominio.com`. Técnicamente perfecto (único, DNS-safe), pero comercialmente feo para un SaaS.

| Opción | Subdominio | Costo |
|---|---|---|
| Dejar el slug tal cual | `restaurante-el-mango-x7k2p9` | Cero. Feo pero funcional. |
| Quitar el sufijo random | `restaurante-el-mango` | Requiere garantizar unicidad de otra forma (validación en onboarding). |
| Slug elegible/editable por el admin | `elmango` | Lo más "SaaS"; más trabajo (UI + validación de disponibilidad). |

**Sin resolver.** Tensión entre "usar el slug" y "subdominios bonitos" por el sufijo random.

---

## 8. Dificultad

```
Código de resolución de tenant    ████░░░░░░  Fácil (~días)
Gate de login                     ███░░░░░░░  Fácil
Mover landing/super-admin         ███░░░░░░░  Fácil
DNS wildcard *.midominio.com      ██░░░░░░░░  Fácil (1 registro, una vez)
TLS wildcard                      ██░░░░░░░░  Fácil (la guía ya lo cubre)
Dev local (subdominios)           ███████░░░  Molesto
Header Host en CloudFront→Lambda  ██████░░░░  ⚠️ El único reto técnico real
```

> **Revisión tras analizar la infra (sección 11):** el deploy serverless resultó **menos** problemático de lo que se temía. Un solo CloudFront con `*.midominio.com` como alternate domain + cert wildcard sirve a TODOS los tenants, y **provisionar un tenant nuevo no requiere ningún cambio de infra** (ni DNS, ni cert, ni CloudFront). El único punto delicado es preservar el `Host` original del navegador hasta Lambda.

- **El código es fácil** porque la base de aislamiento ya existe.
- **El riesgo real y único:** que `$request->getHost()` dentro de Lambda vea `restaurantex.midominio.com` y no el dominio interno de API Gateway/CloudFront (ver sección 11).
- **Dev local:** los subdominios son incómodos (`*.mango.test` con Valet/dnsmasq, o `/etc/hosts` por tenant de prueba).

---

## 9. Resumen ejecutivo

- **Dificultad real: media.** Código sencillo; el reto es la infra serverless + wildcard DNS/TLS.
- **Sin paquete.** Custom: `getHost()` + middleware `IdentifyTenant` + `CompanyScope` existente.
- **Enfoque: Opción B** (middleware por host), no `Route::domain()`, para no romper Wayfinder.
- **Lo más subestimado:** deploy en Lambda y dev local, no el código.
- **Única decisión de producto viva:** el sufijo random del slug (sección 7).

---

## 10. Pendiente de explorar

- Estrategia de **dev local** para subdominios (`*.mango.test`, `/etc/hosts`, o `lvh.me`).
- Diseño detallado del **gate de login** en Fortify (`authenticateUsing` vs middleware).
- **Spike de infra** (sección 11.4): confirmar el comportamiento del `Host`/`X-Forwarded-Host` a través del construct `server-side-website` de Lift.

---

## 11. Infraestructura serverless (Bref / AWS Lambda)

> Analizado a partir de `serverless.yml` y `BREF_DEPLOY_GUIDE.md` del repo.

### 11.1 Cómo está montado el deploy HOY

```
Browser
   │
   ▼
Cloudflare (solo DNS, proxy OFF / nube gris)
   │  CNAME → CloudFront
   ▼
CloudFront  (construct `server-side-website` de serverless-lift)
   ├── /build/*  → S3 (assets Vite, no pasan por Lambda)
   └── /*        → API Gateway (HTTP API, evento httpApi: "*")
                        ▼
                  Lambda web (php-84-fpm, Bref)  →  handler public/index.php
```

- **Sesión:** en Lambda `SESSION_DRIVER=cookie` + `SESSION_ENCRYPT=true`. `SESSION_DOMAIN` sin definir → cookies host-only.
- **TLS:** ACM en `us-east-1` (gratis con CloudFront). La guía YA documenta cómo crear un cert wildcard `*.webplena.com` (Fase 9b).
- **DNS:** Cloudflare, registros CNAME con proxy OFF (si se activa el proxy hay conflicto SSL con CloudFront).
- **Sin VPC.** Supabase (PostgreSQL) y Upstash (Redis) son endpoints públicos.

### 11.2 El reto técnico central: el `Host` original

Para la **Opción B** (middleware lee `$request->getHost()`), el host del navegador (`restaurantex.midominio.com`) debe llegar intacto hasta Laravel en Lambda. **Por defecto NO llega:**

- **CloudFront sobrescribe el header `Host`** al reenviar al origen (API Gateway): lo reemplaza por el dominio del origen.
- Si se fuerza a CloudFront a reenviar el `Host` del viewer, **API Gateway responde 403** porque valida que el `Host` coincida con su propio dominio `execute-api`.

Por tanto, `getHost()` dentro de Lambda vería el dominio interno de API Gateway, **no** el subdominio del tenant. Este es el único obstáculo serio.

### 11.3 Solución recomendada (la de menor fricción)

**Reenviar el host original en un header aparte (`X-Forwarded-Host`), sin tocar el `Host`:**

```
Browser (Host: restaurantex.midominio.com)
   │
   ▼
CloudFront
   │  • Host  → lo reescribe al dominio de API GW   (evita el 403)
   │  • X-Forwarded-Host: restaurantex.midominio.com (origin request policy)
   ▼
API Gateway (Host = execute-api, OK, sin 403)
   │  pasa X-Forwarded-Host tal cual
   ▼
Lambda web (Bref)
   │
   ▼
Laravel: TrustProxies confía en el proxy y lee X-Forwarded-Host
   → $request->getHost() === "restaurantex.midominio.com"  ✓
   → IdentifyTenant resuelve la Company por slug
```

Requisitos:
1. **CloudFront**: una *origin request policy* que reenvíe `X-Forwarded-Host` (con el host del viewer) al origen. Hay que verificar si el construct `server-side-website` de Lift ya lo hace o si hay que personalizar la distribución.
2. **Laravel `TrustProxies`** — **hoy NO está configurado** (se verificó: no hay `trustProxies` en `bootstrap/app.php`). Habría que añadir, en `bootstrap/app.php`, confianza en el proxy y en el header `X_FORWARDED_HOST` para que `getHost()` use `X-Forwarded-Host`. Detrás de API Gateway lo habitual es confiar en `*`.

Alternativas (más pesadas, NO recomendadas de entrada):
- **API Gateway custom domain wildcard** (`*.midominio.com`) → acepta el `Host` real y se elimina el 403, pero añade gestión de dominios en API GW.
- **Lambda@Edge / CloudFront Function** que copie `Host` a un header custom → más piezas móviles.

### 11.4 Provisión de un tenant nuevo: CERO infra

Éste es el gran hallazgo que abarata todo:

- **Un solo CloudFront** acepta `*.midominio.com` como *alternate domain name* (CloudFront soporta wildcard en alternate domains).
- **Un solo cert wildcard** `*.midominio.com` en ACM.
- **Un solo registro DNS** wildcard `*` → cloudfront.net en Cloudflare.

Con esos tres elementos (todos de una sola vez), crear el tenant `restauranteY` **no requiere ningún cambio de infra**: `restauranteY.midominio.com` resuelve al mismo CloudFront → misma Lambda, y el `IdentifyTenant` lo resuelve por slug. La provisión de tenants queda 100% en código/datos.

> ⚠️ Limitación de wildcard: `*.midominio.com` cubre subdominios de **primer nivel**. `sub.tenant.midominio.com` NO. Para el caso de uso (un subdominio plano por empresa) es irrelevante.

### 11.5 Generación de URLs sin contexto de request (emails / colas)

Conecta con la **decisión 5** (links de reset password). En Lambda los emails se procesan en la **función `queue` (SQS)**, que **no tiene request HTTP** → no hay `Host`. Laravel cae a `APP_URL`, que es **un único valor** y no puede representar a cada tenant.

Implicación: al **encolar** un mail de un tenant hay que fijar explícitamente la raíz de URL del tenant (p. ej. `URL::forceRootUrl("https://{$slug}.midominio.com")` dentro del job/notification, o construir los links con el subdominio guardado). No se puede depender de `APP_URL`.

### 11.6 Aislamiento de sesión en serverless

Favorable y gratis: con `SESSION_DRIVER=cookie` y `SESSION_DOMAIN` sin definir, la cookie es **host-only**. Cada subdominio recibe su propia cookie de sesión aunque compartan nombre (`mango-app-session`), porque el navegador las separa por host. Refuerza el aislamiento entre tenants sin configuración extra. **No** poner `SESSION_DOMAIN=.midominio.com`.

### 11.7 Costos

El modelo de costos actual (sección de la guía: ~$1-28/mes) **no cambia** con multitenancy: misma Lambda, mismo CloudFront, misma BD. El cert wildcard es gratis (ACM + CloudFront). No hay NAT ni recursos por-tenant.

### 11.8 Resumen de cambios de infra necesarios

| Cambio | Frecuencia | Dificultad |
|---|---|---|
| Cert wildcard `*.midominio.com` (ACM us-east-1) | Una vez | Fácil (guía Fase 9b) |
| `*.midominio.com` en alternate domain names de CloudFront | Una vez | Fácil |
| Registro DNS wildcard `*` → CloudFront (Cloudflare, proxy OFF) | Una vez | Fácil |
| CloudFront reenvía `X-Forwarded-Host` | Una vez | ⚠️ Verificar Lift / personalizar |
| `TrustProxies` en `bootstrap/app.php` | Una vez (código) | Fácil |
| Provisionar cada tenant nuevo | Por tenant | **Cero infra** |

---

## 11.9 Setup concreto: `webplena.com` (Cloudflare + CloudFront + Supabase)

> Aterrizaje del análisis sobre la infra **real** del proyecto.

### Estado real actual

- **Dominio:** `webplena.com`, gestionado en **Cloudflare como DNS-only** (proxy OFF / nube gris). No se usa Route 53.
- **CloudFront:** distribución `d2x9u47fwwnfoi.cloudfront.net` (creada por el construct `server-side-website` de serverless-lift).
- **BD:** Supabase PostgreSQL (transaction pooler, 6543) — **única, compartida**.
- **Registros DNS actuales en Cloudflare (5):**

| Type | Name | Content | Proxy |
|---|---|---|---|
| CNAME | `_c8db04a2...` | `_769b7c32....jkddzztszm.acm-validations.aws` | DNS only |
| CNAME | `_f43d920d....www` | `_427d46b7....jkddzztszm.acm-validations.aws` | DNS only |
| CNAME | `webplena.com` | `d2x9u47fwwnfoi.cloudfront.net` | DNS only |
| CNAME | `www` | `d2x9u47fwwnfoi.cloudfront.net` | DNS only |

Los dos primeros son la validación ACM del cert actual (`webplena.com` + `www`).

### Lo que NO cambia

- **Supabase:** nada. Single-DB row-scoped por `company_id`. Los subdominios son pura resolución de tenant en la app — ni tabla ni conexión por tenant.
- **Lambda / API Gateway / costos:** igual. Misma función, mismo CloudFront, mismo precio.

### Layout de dominios propuesto

```
webplena.com               → central: landing + super-admin (/super-admin)   [NO tenant]
www.webplena.com           → central (redirige al apex)                       [NO tenant]
restaurantex.webplena.com  → tenant cuyo slug = "restaurantex"
restaurantey.webplena.com  → tenant cuyo slug = "restaurantey"
```

El super-admin se queda en `webplena.com/super-admin` (las rutas ya usan ese prefijo). **No hace falta un subdominio extra** para él. `IdentifyTenant` trata `webplena.com` y `www` como reservados/central; cualquier otro subdominio se resuelve como slug.

### Pasos de infra (todos una sola vez)

**1. Cert ACM apex + wildcard.** Como solo se puede adjuntar **un** cert a CloudFront, pedir uno NUEVO en **ACM (us-east-1)** con dos nombres:
- `webplena.com`
- `*.webplena.com`

Cubre apex + todos los subdominios (incluido `www`, que cae bajo el wildcard). ACM dará nuevos CNAME de validación → agregarlos en Cloudflare con **proxy OFF**. Dejar los actuales.

**2. CloudFront — alternate domain names** en la distribución `d2x9u47fwwnfoi`:
- Alternate domain names (CNAMEs): `webplena.com` y `*.webplena.com` (el wildcard ya cubre `www`)
- Custom SSL certificate: el nuevo cert del paso 1

**3. Cloudflare — un CNAME wildcard** (sexto registro):

| Type | Name | Content | Proxy |
|---|---|---|---|
| CNAME | `*` | `d2x9u47fwwnfoi.cloudfront.net` | **DNS only** (gris) |

> En plan free de Cloudflare el wildcard `*` funciona **solo en DNS-only** (justo el caso actual). En proxy naranja requeriría Enterprise.

**4. ⚠️ Header `Host` (único trabajo técnico real).** CloudFront reescribe el `Host` antes del origen, así que Lambda no vería `restaurantex.webplena.com` por defecto. Solución de menor fricción:

- **CloudFront Function** (viewer-request) que copie el host original a un header custom:
  ```js
  function handler(event) {
    event.request.headers['x-forwarded-host'] = { value: event.request.headers.host.value };
    return event.request;
  }
  ```
- La *origin request policy* del construct `server-side-website` reenvía todos los headers excepto `Host`, por lo que `x-forwarded-host` **sí llega** a Lambda.
- **`TrustProxies`** en `bootstrap/app.php` (hoy no existe) confiando en el proxy + `X-Forwarded-Host` → `$request->getHost()` devuelve el host del tenant en toda la app (incl. redirects y `url()`).
- **Plan B** si el header no pasa: API Gateway custom domain wildcard.

**5. Reset password / emails** (Lambda `queue`, sin request): fijar la URL del tenant al encolar (`URL::forceRootUrl("https://{$slug}.webplena.com")` o construir el link con el slug). Ver sección 11.5.

### Diagrama final (valores reales)

```
*.webplena.com (Cloudflare, wildcard CNAME, DNS-only)
        │
        ▼
CloudFront d2x9u47fwwnfoi  (alt domains: webplena.com + *.webplena.com, cert wildcard)
        │  CloudFront Function: Host → x-forwarded-host
        ▼
API Gateway (Host = execute-api → sin 403; reenvía x-forwarded-host)
        ▼
Lambda + Laravel (TrustProxies lee X-Forwarded-Host)
        ▼
IdentifyTenant:  webplena.com/www → central ; otro → Company por slug
        ▼
Supabase (misma BD, filtrada por company_id)  ← sin cambios
```

### Primera tarea antes de escribir código: SPIKE del Host

Antes de implementar nada, validar el paso 4: desplegar una ruta temporal que vuelque `$request->getHost()` y los headers, entrar por `cualquiercosa.webplena.com` y confirmar que el host del tenant llega a Lambda. Es el único punto con riesgo; se despeja en ~30 min. Si pasa, el resto es código directo sobre la base de aislamiento ya existente.

---

## Referencias

- [Laravel Multi-Tenancy: Database vs Subdomain vs Path Routing Strategies](https://hafiz.dev/blog/laravel-multi-tenancy-database-vs-subdomain-vs-path-routing-strategies)
- [Tenancy for Laravel — Package comparison](https://tenancyforlaravel.com/docs/v3/package-comparison/)
- [Tenant identification | Tenancy for Laravel](https://tenancyforlaravel.com/docs/v3/tenant-identification/)
- [How to Implement Multi-tenancy in Laravel (OneUptime)](https://oneuptime.com/blog/post/2026-02-02-laravel-multi-tenancy/view)
- [Sharing Cookies with Subdomains in Laravel — Will Browning](https://willbrowning.me/sharing-cookies-with-subdomains-in-laravel/)
- [How to make CloudFront forward the Host or X-Forwarded-Host header to API Gateway — AWS re:Post](https://repost.aws/questions/QUcGTe5JT6QLqtF9zELETJuw/how-to-make-cloudfront-forward-the-host-or-x-forwarded-host-header-to-api-gateway)
- [CloudFront with API Gateway authorization headers — tommoore](https://tmmr.uk/post/api-gateway/api-gateway-with-cloudfront-authorization/)
- [Bref — Advanced HTTP use-cases](https://bref.sh/docs/use-cases/http/advanced-use-cases)
- Fuentes internas del repo: `serverless.yml`, `BREF_DEPLOY_GUIDE.md` (Fase 9b — subdominios).
