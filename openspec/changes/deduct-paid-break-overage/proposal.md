## Why

Hoy las pausas pagadas (`is_paid = true`) cuentan como tiempo trabajado sin importar cuánto se extiendan: el cálculo de `net_hours` solo descuenta las pausas no pagadas. Si un tipo de pausa pagada tiene un límite (`max_duration_minutes`, p. ej. 15 min) y el empleado se excede (toma 25 min), esos 10 minutos extra hoy se pagan como tiempo trabajado. El negocio necesita que el exceso sobre el límite de una pausa pagada se descuente del tiempo trabajado, y que ese descuento sea visible y comprensible en el panel administrativo.

**Ejemplo objetivo:** entrada 12:00 p.m., salida 8:00 p.m. (8h brutas), pausa pagada (límite 15 min) de 2:00 a 2:25 p.m. (25 min). El exceso de 10 min se descuenta → el registro muestra **7h 50m** trabajadas, no 8h.

## What Changes

- **BREAKING (regla de negocio):** `net_hours` ahora descuenta, además de las pausas no pagadas, el **exceso de las pausas pagadas** por encima de su `max_duration_minutes`. Nueva fórmula: `net_hours = max(0, gross_hours − unpaid_break_hours − paid_break_overage_hours)`.
- El exceso de una pausa pagada se calcula por pausa finalizada: `max(0, duration_minutes − max_duration_minutes)`. Las pausas pagadas **sin límite** (`max_duration_minutes = null`, p. ej. "Baño", "Médica") no generan exceso. La porción dentro del límite sigue siendo pagada y NO se descuenta.
- Se persiste el exceso descontado en una nueva columna `time_entries.paid_break_overage_hours` para agregación eficiente en reportes y para mostrarlo en la UI.
- `ClockOut` y `RecalculateTimeEntry` (edición admin de horas/pausas y creación manual) calculan y guardan `paid_break_overage_hours` antes de reclasificar las horas. Como `CalculateWorkHours` distribuye `net_hours` proporcionalmente, el descuento fluye automáticamente a los 8 tipos de hora.
- El panel administrativo refleja visualmente el descuento: el desglose diario del reporte de empleado, el listado `/admin/time-entries` y el detalle expandible de pausas muestran el exceso descontado por pausas pagadas, con la pausa específica que se excedió marcada.

## Capabilities

### New Capabilities
- `paid-break-overage-deduction`: La regla de que el tiempo de una pausa pagada que excede su límite configurado se descuenta del tiempo trabajado (`net_hours`), su cálculo, persistencia (`paid_break_overage_hours`) y su aplicación en el alta/edición/recálculo de registros.

### Modified Capabilities
- `admin-time-entry-management`: Las reglas "Editar horas del registro" y "Gestión de pausas del registro" hoy definen `net_hours = max(0, gross_hours − break_hours)` y "solo las pausas no pagadas descuentan tiempo". Cambian para incluir el descuento del exceso de pausas pagadas en el recálculo.
- `daily-work-breakdown`: La tabla de detalle diario hoy afirma que los descansos pagados "no descuentan tiempo trabajado". Cambia para exponer y mostrar el exceso de pausas pagadas que sí se descuenta, tanto en los datos del desglose como en la presentación visual (tabla y detalle expandible).

## Impact

- **Dominio afectado:** TimeTracking.
- **Backend:** `app/Domain/TimeTracking/Actions/ClockOut.php`, `RecalculateTimeEntry.php`; `app/Domain/TimeTracking/Models/TimeEntry.php` (nuevo método `paidBreakOverageHours()` y cast/fillable de la nueva columna); `app/Domain/TimeTracking/Actions/GenerateEmployeeReport.php` (agregación y desglose diario). Posible toque en `GenerateCompanyReport.php` si reporta el dato.
- **Migración de BD:** SÍ — agrega `paid_break_overage_hours` decimal(5,2) default 0.00 a `time_entries`.
- **Frontend:** `resources/js/components/DailyWorkTable.vue`, `DailyWorkDayDetail.vue`, `resources/js/pages/Admin/TimeEntries/Index.vue`, `resources/js/pages/Reports/Employee.vue`; tipos en `resources/js/types/models.ts`; claves i18n en `en.json`/`es.json`.
- **Multi-tenant:** sí, opera sobre `time_entries`/`breaks`/`break_types` con `company_id`; sin cambios en aislamiento.
- **Roles:** el descuento aplica a todos los registros (afecta el `ClockOut` de cualquier empleado); la visualización del exceso es para `admin`/`super-admin` en las vistas administrativas existentes.

## Non-goals

- No se cambia el comportamiento de las pausas **no pagadas** (siguen descontando su duración completa).
- No se cambia la clasificación en los 8 tipos de hora ni los recargos; el descuento entra vía `net_hours` y se distribuye proporcionalmente.
- No se agregan límites nuevos ni configuración nueva de tipos de pausa: se reutiliza `break_types.max_duration_minutes` existente.
- No se bloquea ni se impide al empleado excederse en una pausa; solo se descuenta el exceso.
- No se recalculan registros históricos ya existentes como parte de este cambio (solo aplica a registros creados/recalculados tras el despliegue).
