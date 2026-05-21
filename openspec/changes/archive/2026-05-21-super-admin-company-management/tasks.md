## 1. Domain Action

- [x] 1.1 Crear `App\Domain\Company\Actions\CreateCompanyAdminUser`: recibe `Company` + datos del usuario, genera contraseña con `Str::password(12)`, crea `User` con `company_id`, asigna rol `admin`, retorna `[$user, $plainPassword]`

## 2. Form Requests

- [x] 2.1 Crear `App\Http\Requests\SuperAdmin\UpdateCompanyRequest`: validar `name` (required, max:255), `slug` (required, unique ignorando empresa actual, regex alfanumérico-guiones), `timezone` (required, timezone PHP válido), `country` (nullable, size:2), `subscription_plan` (nullable, string), `trial_ends_at` (nullable, date)
- [x] 2.2 Crear `App\Http\Requests\SuperAdmin\StoreAdminUserRequest`: validar `name` (required, max:255), `email` (required, email, unique:users)

## 3. Controller y Rutas

- [x] 3.1 Crear `App\Http\Controllers\SuperAdmin\CompanyController` con métodos: `index` (lista empresas), `edit` (empresa + admins existentes), `update` (actualiza empresa usando `UpdateCompanyRequest`), `storeAdminUser` (delega a `CreateCompanyAdminUser`, flash `created_password`, redirige a edit)
- [x] 3.2 Agregar grupo de rutas en `routes/web.php` con middleware `['auth', 'verified', 'role:super-admin']` y prefijo `/super-admin`: `GET /companies`, `GET /companies/{company}/edit`, `PUT /companies/{company}`, `POST /companies/{company}/admin-users`
- [x] 3.3 Agregar redirect en `DashboardController::__invoke()`: si `$user->isSuperAdmin()` → `redirect()->route('super-admin.companies.index')`
- [x] 3.4 Ejecutar `php artisan wayfinder:generate` y `npm run build`

## 4. Frontend — Listado de empresas

- [x] 4.1 Crear `resources/js/pages/SuperAdmin/Companies/Index.vue`: tabla con columnas nombre, slug, plan, fecha de creación; cada fila con botón "Editar" que navega a la página de edición; usar componentes `ui/table` o `ui/card` existentes
- [x] 4.2 Agregar traducciones en `locales/es.json` y `locales/en.json` para las claves de este panel (`super_admin.companies.*`)
- [x] 4.3 Ejecutar `npm run build`

## 5. Frontend — Edición de empresa y creación de admin

- [x] 5.1 Crear `resources/js/pages/SuperAdmin/Companies/Edit.vue`: sección "Información de empresa" con todos los campos editables (nombre, slug, timezone, país, plan, trial); sección "Administradores" con lista de admins existentes; sección "Crear administrador" con campos nombre y email; mostrar alerta `created_password` al volver de crear un admin (reutilizar patrón de `Employees/Show.vue`)
- [x] 5.2 Agregar traducciones faltantes en `locales/es.json` y `locales/en.json`
- [x] 5.3 Ejecutar `npm run build`

## 6. Navegación lateral

- [x] 6.1 Modificar `AppSidebar.vue`: agregar computed `isSuperAdmin` e `superAdminNavItems` con link a `/super-admin/companies` (sección "Plataforma"); ocultar items de admin (Employees, Schedules, Calendar, Reports, Locations) cuando el usuario es super-admin
- [x] 6.2 Agregar clave de traducción `nav.companies` en `locales/es.json` y `locales/en.json`
- [x] 6.3 Ejecutar `npm run build`

## 7. Tests

- [x] 7.1 Crear `tests/Feature/SuperAdmin/CompanyManagementTest.php` con tests para: super-admin lista empresas (200), admin recibe 403 en listado, empleado recibe 403 en listado, super-admin edita empresa con datos válidos (actualiza BD + redirect), slug duplicado rechazado (sessionHasErrors), nombre vacío rechazado (sessionHasErrors)
- [x] 7.2 Agregar tests en el mismo archivo para: super-admin ve admins existentes en edit, super-admin crea admin con datos válidos (BD + rol + created_password en sesión), email duplicado rechazado, email inválido rechazado, admin recibe 403 en storeAdminUser
- [x] 7.3 Agregar tests para: super-admin en `/dashboard` redirige a `super-admin.companies.index`, admin en `/dashboard` devuelve 200 (sin regresión)
- [x] 7.4 Ejecutar `vendor/bin/pint --dirty --format agent`
- [x] 7.5 Ejecutar `php artisan test --compact --filter=CompanyManagementTest` y verificar que todos los tests pasan
