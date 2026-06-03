## Context

El dominio `TimeTracking` ya modela `TimeEntry` (1 por empleado/día, con 8 buckets de horas) y `BreakEntry` (pausas con `started_at`/`ended_at`, tipo pagado/no pagado). La clasificación de horas vive en la acción `CalculateWorkHours`, que recibe un `TimeEntry` con `gross_hours`/`break_hours`/`net_hours` ya seteados y distribuye el neto en los 8 buckets, marcando `status = 'calculated'`.

Hoy `Admin/TimeEntryController` solo expone `index`/`edit`/`update`, y el `update` recomputa `net_hours = gross − break_hours` usando el `break_hours` **existente** (no recalcula desde las pausas). El dashboard (`DashboardController` + `Dashboard.vue`) mezcla estado en vivo con tiempos por empleado y un link de edición.

Restricciones clave:
- `time_entries` tiene `unique(employee_id, date)` y **no** tiene soft-deletes.
- `BelongsToCompany` aplica un global scope por `company_id` en ambos modelos.
- `break_hours` solo cuenta pausas **no pagadas** y **finalizadas** (`ended_at` no nulo).
- Controllers delgados; toda la lógica va en Actions del dominio `TimeTracking`.

## Goals / Non-Goals

**Goals:**
- CRUD completo de `TimeEntry` para admin: crear, listar (filtros empleado + rango), editar horas, eliminar (soft-delete).
- Gestión de pausas dentro del editor del registro (agregar/editar/eliminar/cambiar tipo).
- Un único punto de recálculo reutilizable: `break_hours` → `net_hours` → `CalculateWorkHours`.
- Dashboard reducido a estado en vivo + KPIs + check-in manual.
- Soft-delete que permita recrear el registro del mismo empleado/día.

**Non-Goals:**
- Múltiples registros activos por empleado/día.
- UI de papelera/restauración de registros eliminados.
- Cambios a la lógica de clasificación de `CalculateWorkHours`.
- Cambios al flujo de fichaje de kiosko/empleado.

## Decisions

### 1. Soft-delete con índice único compuesto (opción A)
Agregar `SoftDeletes` a `TimeEntry` y reemplazar `unique(employee_id, date)` por `unique(employee_id, date, deleted_at)`.

- **Por qué:** mantener "1 registro activo por día" sin perder historial. Con `deleted_at` en el índice, dos filas con misma `(employee_id, date)` pueden coexistir si una está eliminada (su `deleted_at` difiere).
- **Detalle MySQL/SQLite:** en un índice único, múltiples `NULL` se consideran distintos, por lo que la fila activa (`deleted_at = NULL`) nunca choca con filas eliminadas (`deleted_at` con timestamp). No se requiere columna generada.
- **Validación de unicidad en la app:** además del índice, `StoreTimeEntryRequest` valida con `Rule::unique('time_entries')->where(employee_id, date)->whereNull('deleted_at')` para devolver un 422 limpio en vez de un error de BD.
- **Alternativas descartadas:** (B) restaurar-y-sobrescribir el registro eliminado al recrear — menos predecible y mezcla semántica de "crear" con "restaurar".

### 2. Action de recálculo centralizada: `RecalculateTimeEntry`
Nueva Action en `Domain/TimeTracking/Actions` que, dado un `TimeEntry`, recomputa en orden:
1. `gross_hours = diffInMinutes(clock_in, clock_out) / 60`
2. `break_hours = Σ duration_minutes de pausas no pagadas finalizadas / 60`
3. `net_hours = max(0, gross_hours − break_hours)`
4. invoca `CalculateWorkHours->execute()` (que persiste buckets y deja `status='calculated'`)
5. fija `status = 'edited'` y persiste `edited_by`/`edit_reason` cuando aplica.

- **Por qué:** hoy la lógica de recálculo está duplicada/parcial en `ClockOut` y en el `update` del controller. Centralizarla evita inconsistencias y es el único lugar que `store`, `update` y las operaciones de pausas invocan.
- **Reutiliza** la fórmula de `break_hours` ya presente en `ClockOut` (pausas `is_paid = false` + `ended_at` no nulo).
- **Nota:** las pausas creadas por el admin se consideran finalizadas (tienen `started_at` y `ended_at`), por lo que cuentan para `break_hours` si su tipo es no pagado.

### 3. Pausas gestionadas como sub-recurso anidado del registro
Las pausas se editan dentro de `Edit.vue` del registro, no como sección propia. Operaciones expuestas como rutas anidadas bajo el registro:
`admin/time-entries/{timeEntry}/breaks` (store), `.../breaks/{break}` (update, destroy).

- **Por qué:** una pausa solo tiene sentido dentro de su turno; el rango válido depende de `clock_in`/`clock_out` del registro. Anidar simplifica autorización (cargar el `timeEntry` y validar empresa) y la validación de rango.
- Cada operación de pausa termina invocando `RecalculateTimeEntry` sobre el registro padre.
- **Validación de rango:** `started_at`/`ended_at` deben caer dentro de `[clock_in, clock_out]` y `ended_at > started_at`; `break_type_id` validado con `Rule::exists` condicionado a `company_id`.
- **Alternativa considerada:** enviar todas las pausas en el mismo payload del `update` del registro (sincronización masiva). Descartada por complejidad de diffing y manejo de errores parciales; las rutas anidadas dan feedback granular.

### 4. Form Requests dedicados
- `StoreTimeEntryRequest`: `employee_id` (exists + company), `date`, `clock_in`, `clock_out` (after clock_in), unicidad activa.
- `UpdateTimeEntryRequest` (ampliar el existente): `clock_in`, `clock_out` (after), `edit_reason` requerido.
- `StoreBreakRequest` / `UpdateBreakRequest`: `break_type_id` (exists+company), `started_at`, `ended_at` (after start), rango dentro del turno.
- Todos con `authorize()` validando `isCompanyAdmin() || isSuperAdmin()` y mensajes en `messages.php`.

### 5. Dashboard: reducir payload, no solo ocultar en UI
`DashboardController` deja de enviar `net_hours_today`, `clock_in`, `clock_out` y `time_entry_id` en `employeeStatus`; `Dashboard.vue` elimina esas columnas y el link "Editar". Se conservan KPIs, polling y FAB de check-in.

- **Por qué:** la regla es "el dashboard no muestra ni edita tiempos"; quitar los datos del payload (no solo ocultarlos) cumple el requisito de no exponerlos.

### 6. Navegación
Nuevo item de sidebar "Registros" (icono `ClipboardList` o similar) en `AppSidebar.vue`, condicionado a `isAdmin`, apuntando a `admin.time-entries.index`. i18n en `nav.time_entries`.

## Risks / Trade-offs

- **Índice único con `deleted_at` en SQLite (tests):** SQLite trata múltiples `NULL` como distintos en índices únicos, igual que MySQL, así que el comportamiento es consistente entre test y producción → mitigación: test explícito de "recrear tras soft-delete".
- **Recálculo con turnos nocturnos / multidía:** `CalculateWorkHours` ya maneja cruces de medianoche y depende de la consistencia de `net_hours`. Riesgo: editar horas de un registro afecta el acumulado semanal/diario de otros registros del mismo empleado → mitigación: el recálculo de un registro usa los netos de los demás registros tal como están; documentar que editar registros pasados puede requerir revisar registros posteriores de la misma semana (mismo comportamiento que hoy, no se agrava).
- **Pausas que invalidan el turno al editar horas:** si el admin reduce el rango `clock_in`–`clock_out` dejando pausas fuera → mitigación: validar en `UpdateTimeEntryRequest` que las pausas existentes sigan dentro del nuevo rango, o rechazar con error claro.
- **Borrado en cascada:** soft-delete de `TimeEntry` no propaga a `breaks` (cascade es a nivel FK físico) → las pausas quedan "huérfanas lógicas" pero ocultas con el registro; aceptable porque no se listan independientemente. No se requiere soft-delete en `breaks`.

## Migration Plan

1. Migración: `Schema::table('time_entries')` → `dropUnique(['employee_id','date'])`, `softDeletes()`, `unique(['employee_id','date','deleted_at'])`. Incluir `down()` que revierta.
2. Actualizar `ai-specs/specs/data-model.md` con `deleted_at` y el nuevo índice.
3. Desplegar backend + frontend juntos (Wayfinder regenerado, `npm run build`).
4. **Rollback:** la migración `down()` restaura el índice simple y elimina `deleted_at`; como no se borran datos físicamente, no hay pérdida al revertir (los registros soft-deleted reaparecerían como activos — aceptable y poco frecuente en ventana de rollback).

## Open Questions

- ¿El recálculo debe re-disparar la decisión de pago de overtime (`ResolveOvertimePaymentDecision`) o eso solo aplica en reportes? (Asumido: no, el recálculo solo reclasifica buckets; la decisión de pago se resuelve en la generación de reportes como hoy.)
