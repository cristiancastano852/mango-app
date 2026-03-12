## Why

Mango App carece de un panel de configuración unificado para que los administradores personalicen su tenant. Actualmente, los ajustes de recargos y festivos ya existen, pero no hay forma de gestionar tipos de pausa, datos de la empresa (nombre, logo), zona horaria, días laborales ni horario por defecto. Esto obliga a configuraciones manuales o hardcodeadas que no escalan al onboardear nuevas empresas colombianas.

## What Changes

- **Tipos de pausa (CRUD):** Admin puede crear, editar, desactivar y configurar tipos de pausa (`break_types`) — incluyendo si es pagada, duración máxima, frecuencia por día y cuál es el almuerzo por defecto.
- **Perfil de empresa:** Admin puede editar nombre, logo (upload de imagen) y país de la empresa desde Settings.
- **Zona horaria:** Admin puede cambiar el timezone de su empresa, afectando cálculos futuros de `CalculateWorkHours`.
- **Días laborales:** Admin puede definir qué días de la semana opera la empresa (almacenado en `companies.settings.working_days`), sirviendo como default para nuevos schedules.
- **Horario por defecto:** Admin puede seleccionar un schedule existente como default de la empresa; empleados nuevos lo heredan automáticamente.
- Gestión de festivos y reglas de recargos **ya implementados** — no requieren cambios.

## Capabilities

### New Capabilities
- `break-type-management`: CRUD completo de tipos de pausa por empresa — crear, editar, desactivar, gestión de is_default (almuerzo), validaciones de is_paid/max_duration/max_per_day.
- `company-profile`: Edición de datos básicos de empresa (nombre, logo upload/delete, país) y zona horaria desde Settings.
- `company-working-days`: Configuración de días laborales de la empresa y horario por defecto (default_schedule_id), almacenados en `companies.settings` jsonb.

### Modified Capabilities
_(ninguna — los features existentes de holidays y surcharge rules no cambian sus requirements)_

## Impact

- **Backend:**
  - Nuevos controllers: `Settings/BreakTypeController`, `Settings/CompanyProfileController`, `Settings/CompanySettingsController`
  - Nuevas actions: `CreateBreakType`, `UpdateBreakType`, `ToggleBreakTypeActive`, `UpdateCompanyProfile`, `UpdateCompanySettings`
  - Nuevo factory: `BreakTypeFactory`
  - Form requests: `StoreBreakTypeRequest`, `UpdateBreakTypeRequest`, `UpdateCompanyProfileRequest`, `UpdateCompanySettingsRequest`
  - Rutas nuevas en `routes/settings.php` (middleware `role:admin|super-admin`)
  - Modificación de `CreateEmployee` para asignar schedule default
- **Frontend:**
  - Nuevas páginas Vue: `settings/BreakTypes.vue`, `settings/CompanyProfile.vue`, `settings/CompanySettings.vue`
  - Actualización de `layouts/settings/Layout.vue` para agregar nav items admin (Empresa, Tipos de pausa, Días laborales)
  - i18n: claves nuevas en `en.json` y `es.json`
  - Wayfinder: regenerar tras agregar rutas
- **Storage:** upload de logos requiere `storage:link` y disco `public` configurado
- **Multi-tenant:** todos los endpoints filtran por `company_id`; super-admin con `company_id = null` accede a cualquier empresa
