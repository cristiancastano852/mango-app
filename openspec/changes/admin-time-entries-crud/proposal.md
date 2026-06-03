## Why

Hoy el administrador no tiene una sección real para gestionar los registros de tiempo de sus empleados: solo puede editar `clock_in`/`clock_out` y el motivo de una entrada (sin crear, sin eliminar, sin tocar pausas), y se llega a esa edición únicamente desde un link incrustado en el dashboard. El dashboard, a su vez, mezcla estado en vivo con horas y edición, cuando debería ser solo un panel de "quién está trabajando ahora". Necesitamos una sección administrativa dedicada con CRUD completo sobre los registros y sus pausas, y un dashboard enfocado solo en estado actual.

## What Changes

- **Nueva sección admin "Registros"** con item propio en el sidebar (solo `admin`/`super-admin`), separada del dashboard.
- **CRUD completo sobre `TimeEntry`** en `Admin/TimeEntryController`:
  - `index` (existe): mejorar con filtro por **rango de fechas** (además del filtro por empleado y fecha única actual).
  - `create`/`store` (nuevo): crear manualmente un registro para un empleado en un día **sin** registro existente.
  - `edit`/`update` (existe): ampliar para gestionar pausas dentro del mismo formulario.
  - `destroy` (nuevo): **soft-delete** del registro.
- **Gestión de pausas (`BreakEntry`) dentro del editor del registro**: editar hora de inicio/fin, agregar pausa nueva, eliminar pausa y cambiar el tipo de pausa.
- **Recálculo consistente** tras cualquier cambio de horas o pausas: recomputar `break_hours` (solo pausas no pagadas finalizadas) → `net_hours` = `max(0, gross_hours − break_hours)` → `CalculateWorkHours` (reclasifica los 8 buckets y marca `status = 'edited'`).
- **BREAKING (schema):** agregar `deleted_at` (SoftDeletes) a `time_entries` y cambiar el índice `unique(employee_id, date)` por `unique(employee_id, date, deleted_at)` para permitir recrear un registro tras eliminarlo.
- **Dashboard simplificado a solo-estado**: el panel de empleados muestra avatar, nombre y estado (`working`/`on_break`/`absent`/`done`); se quitan `net_hours_today`, las horas de `clock_in`/`clock_out` y el link "Editar". El FAB de check-in manual permanece. Los KPIs en vivo se conservan.

## Capabilities

### New Capabilities
- `admin-time-entry-management`: gestión administrativa de los registros de tiempo — listado con filtros (empleado + rango de fechas), creación manual, edición de horas, edición/creación/eliminación de pausas, eliminación con soft-delete, y recálculo de horas tras cada cambio. Incluye reglas de autorización (admin/super-admin), multi-tenancy y la restricción de un registro por empleado/día.
- `dashboard-live-status`: el dashboard del admin presenta únicamente el estado en vivo de los empleados (presente/en pausa/ausente/finalizado) y los KPIs agregados, sin exponer tiempos por empleado ni acciones de edición de registros.

### Modified Capabilities
<!-- Ninguna: CalculateWorkHours y la clasificación de 8 buckets se reutilizan sin cambiar sus requisitos. -->

## Impact

- **Dominio afectado:** principalmente `TimeTracking` (modelos `TimeEntry`, `BreakEntry`; acción `CalculateWorkHours`); toca `Employee` (selección de empleado) y la UI del dashboard.
- **Multi-tenant:** sí — `TimeEntry` y `BreakEntry` usan `BelongsToCompany` (`company_id`); todas las operaciones admin deben respetar el scope de empresa y rechazar recursos cross-company con `assertSessionHasErrors`.
- **Roles:** la sección y todas sus operaciones son solo para `admin` y `super-admin`; el empleado no tiene acceso.
- **Migración de BD:** sí — `time_entries`: añadir `deleted_at` + cambiar índice único a `unique(employee_id, date, deleted_at)`. Actualizar `ai-specs/specs/data-model.md`.
- **Código:**
  - Backend: `Admin/TimeEntryController` (+create/store/destroy), nuevos Form Requests (`StoreTimeEntryRequest`, `UpdateTimeEntryRequest` ampliado, requests de pausas), nueva(s) Action(s) para recomputar `break_hours`/`net_hours` y orquestar el recálculo, `TimeEntry` con `SoftDeletes`, `DashboardController` (payload reducido).
  - Frontend: `Admin/TimeEntries/Index.vue` (filtro de rango + acciones crear/eliminar), `Create.vue` (nuevo), `Edit.vue` (gestión de pausas), `Dashboard.vue` (quitar tiempos/edición), `AppSidebar.vue` (nuevo nav item), i18n (`es.json`/`en.json`, `messages.php`).
  - Wayfinder regenerado; `npm run build`.
- **Non-goals:**
  - No se permiten múltiples registros por empleado en el mismo día (se mantiene 1/día).
  - No se elimina ni modifica la lógica de clasificación de horas (`CalculateWorkHours`).
  - No se cambia el flujo de fichaje del kiosko ni del empleado (`time-clock`).
  - No se construye una papelera/UI de restauración de registros eliminados (el soft-delete habilita recuperación futura, pero la UI de restaurar queda fuera de alcance).
