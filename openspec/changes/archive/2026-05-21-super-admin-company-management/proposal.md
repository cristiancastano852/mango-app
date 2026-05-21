## Why

El super-admin de la plataforma no tiene un panel dedicado para gestionar las empresas registradas: actualmente no puede listar empresas, editar su información ni crear usuarios administradores para ellas. Esto obliga a gestionar todo directamente en base de datos.

## What Changes

- **Nuevo panel `/super-admin/companies`**: vista de listado de todas las empresas registradas en la plataforma.
- **Edición de empresa desde super-admin**: formulario para editar cualquier campo de una empresa (nombre, slug, timezone, país, logo, plan de suscripción, trial).
- **Creación de usuario administrador**: desde el panel de edición de una empresa, el super-admin puede crear un nuevo usuario con rol `admin` asociado a esa empresa. La contraseña generada se muestra una sola vez en pantalla (patrón existente `created_password`).
- **Vista de admins existentes**: el panel de edición muestra los usuarios con rol `admin` ya asignados a la empresa.
- **Redirect del Dashboard**: el super-admin es redirigido al panel de empresas al acceder a `/dashboard`, en lugar del dashboard de métricas (que asume una empresa activa).
- **Navegación lateral**: el sidebar muestra una sección "Plataforma" con enlace a "Empresas" cuando el usuario es super-admin.

## Capabilities

### New Capabilities

- `super-admin-companies`: Panel exclusivo de super-admin para listar empresas, editar su información completa y crear usuarios administradores para cada empresa.

### Modified Capabilities

- `company-profile`: La ruta existente `GET /settings/company-profile` para super-admin ya muestra estado vacío (escenario documentado en spec). No cambia el comportamiento — el nuevo panel es independiente.

## Impact

- **Rutas**: Nuevo grupo `routes/web.php` con prefijo `/super-admin`, middleware `role:super-admin`.
- **Controlador**: `App\Http\Controllers\SuperAdmin\CompanyController` (index, edit, update, storeAdminUser).
- **Form Requests**: `UpdateSuperAdminCompanyRequest`, `StoreAdminUserRequest`.
- **Frontend**: Dos páginas Vue nuevas: `SuperAdmin/Companies/Index.vue`, `SuperAdmin/Companies/Edit.vue`.
- **Sidebar**: Modificación de `AppSidebar.vue` para mostrar navegación diferente al super-admin.
- **DashboardController**: Redirect condicional si `isSuperAdmin()`.
- **Multi-tenancy**: Las queries del nuevo panel usan `Company::query()` directamente (sin `BelongsToCompany` scope) y `User::withoutGlobalScopes()` para listar admins de cualquier empresa.
- **Roles**: Exclusivo para `super-admin`. Los endpoints no exponen datos de otras empresas a roles inferiores.
- **Sin migración de BD**: No se requiere ningún cambio de schema.

## Non-goals

- No incluye impersonación de empresa (el super-admin no "entra" a operar como admin de una empresa).
- No incluye eliminación de empresas.
- No incluye gestión de empleados desde este panel.
- No incluye envío de email de bienvenida al nuevo admin (la contraseña se comparte manualmente).
- No incluye paginación en el listado inicial (se asume volumen bajo de empresas en esta etapa).
