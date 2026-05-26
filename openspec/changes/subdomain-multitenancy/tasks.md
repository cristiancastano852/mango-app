## 1. Spike del Host (bloqueante — antes de codear el resto)

- [x] 1.1 Crear `TrustProxies` en `bootstrap/app.php` confiando en el proxy (`at: '*'`) con `Request::HEADER_X_FORWARDED_HOST` incluido
- [x] 1.2 Agregar CloudFront Function (viewer-request) que copia `Host` → `x-forwarded-host` y asociarla a la distribución `d2x9u47fwwnfoi` (`forward-host-mango-app`, publicada y asociada a Default(*) Viewer Request)
- [x] 1.3 Ruta temporal de diagnóstico que devuelve `$request->getHost()` + headers; desplegar y verificar en `algo.webplena.com` que `getHost()` devuelve el subdominio (verificado: `getHost()=prueba.webplena.com`)
- [x] 1.4 Confirmar resultado del spike; si el host no llega, evaluar plan B (API Gateway custom domain). Eliminar la ruta temporal (spike OK; ruta eliminada)

## 2. Configuración de tenancy

- [x] 2.1 Crear `config/tenancy.php` con `base_domain`, `public_hosts` (`webplena.com`/`www`), `admin_host` (`admin.webplena.com`) y `reserved_subdomains` (al menos `www`, `admin`), leyendo de env
- [x] 2.2 Añadir las variables correspondientes a `.env.example` y a la plantilla de deploy (`.env.deployment` / `BREF_DEPLOY_GUIDE.md`)

## 3. Resolución del tenant (Shared)

- [x] 3.1 Implementar helper de "tenant actual" (binding en container, p. ej. `app()->instance('tenant', ...)` + accessor) en `app/Domain/Shared/`
- [x] 3.2 Crear middleware `IdentifyTenant` en `app/Http/Middleware/`: resuelve `Company` por slug del host; central/reservado → sin tenant; subdominio desconocido → 404
- [x] 3.3 Registrar `IdentifyTenant` en el grupo `web` en `bootstrap/app.php`
- [x] 3.4 Endurecer `CompanyScope` para preferir el tenant del subdominio y caer a `auth()->user()->company_id` cuando no haya tenant
- [x] 3.5 Feature tests: subdominio válido resuelve, subdominio inexistente → 404, dominio central sin tenant, subdominio reservado sin tenant
- [x] 3.6 Feature tests de aislamiento: consultas filtradas al tenant del subdominio; fallback sin contexto de subdominio
- [x] 3.7 `vendor/bin/pint --dirty --format agent` y `php artisan test --compact` de los tests nuevos

## 4. Gate de login por subdominio

- [x] 4.1 Implementar el gate con `Fortify::authenticateUsing()` en `FortifyServiceProvider`: en subdominio solo usuarios de esa company; en `admin.webplena.com` solo `super-admin`; sin login en host público
- [x] 4.2 Restringir las rutas `super-admin` al host `admin.webplena.com` (y servir login/panel ahí); ajustar redirecciones post-login por tipo de host
- [x] 4.3 Feature tests por rol: usuario del tenant entra; usuario de otra empresa falla; super-admin falla en subdominio; super-admin entra en `admin.webplena.com`; admin/employee fallan en `admin.webplena.com`
- [x] 4.4 Verificar que el rate-limiting de login existente sigue operando
- [x] 4.5 `vendor/bin/pint --dirty --format agent` y `php artisan test --compact` de los tests de login

## 5. Slug como subdominio en creación de empresas (Company)

- [x] 5.1 Actualizar `CreateCompanyWithAdmin` para autogenerar slug DNS-safe sin sufijo random (sufijo numérico solo en colisión) y aceptar slug explícito opcional
- [x] 5.2 Crear/actualizar el Form Request de creación: regla DNS-safe (`^[a-z0-9]([a-z0-9-]*[a-z0-9])?$`, ≤ 63), no reservado, único en `companies.slug`, con mensajes
- [x] 5.3 Agregar el campo subdominio (opcional) al formulario `SuperAdmin/Companies/Create.vue` con Wayfinder + i18n (`en.json`/`es.json`); revisar `components/ui/` antes de crear inputs
- [x] 5.4 `php artisan wayfinder:generate` y `npm run build`
- [x] 5.5 Feature tests: autogeneración limpia, colisión → sufijo numérico, slug explícito válido, duplicado rechazado, formato inválido rechazado, reservado rechazado
- [x] 5.6 `vendor/bin/pint --dirty --format agent` y `php artisan test --compact`

## 6. URLs del tenant en emails encolados

- [x] 6.1 Fijar la raíz de URL del tenant (`URL::forceRootUrl("https://{slug}." . config('tenancy.base_domain'))`) en la notification/mailable de reset password (o construir el enlace con el slug)
- [x] 6.2 Feature test: el enlace del correo de reset apunta al subdominio del tenant, no a `APP_URL`
- [x] 6.3 `vendor/bin/pint --dirty --format agent` y `php artisan test --compact`

## 7. Cierre

- [x] 7.1 Actualizar `ai-specs/specs/domain-model.md` (nuevo middleware `IdentifyTenant`, helper de tenant) y `docs/analysis/multitenancy-subdominios.md` (marcar pasos completados)
- [x] 7.2 Ejecutar la suite completa `php artisan test --compact` y confirmar verde
- [ ] 7.3 Verificación manual en un subdominio real: login del tenant, aislamiento de datos, bloqueo cross-company, login central de super-admin
