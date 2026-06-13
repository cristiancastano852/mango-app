# Proposal: daily-work-breakdown-table

## Why

El administrador no puede ver de un vistazo cómo trabajó cada empleado día a día: el reporte de empleado (`/reports/employee`) solo muestra totales del período, y el listado de registros (`/admin/time-entries`) muestra horas en formato 24h sin detalle de descansos. El desglose diario ya existe en el backend (`GenerateEmployeeReport::getDailyBreakdown()`) pero la vista web lo pide apagado — solo lo usan los exports PDF/Excel, y sin horario de entrada/salida ni detalle de pausas.

## What Changes

- **Backend — enriquecer `getDailyBreakdown()`** en `GenerateEmployeeReport` (dominio TimeTracking): agregar `clock_in`, `clock_out`, `status` y `breaks[]` (nombre, icono, color, inicio, fin, duración) a cada día. Gracias al constraint `unique(employee_id, date)` el desglose diario equivale a leer los entries con `with('breaks.breakType')` — sin GROUP BY, 2 queries indexadas (≤31 filas/mes).
- **Reporte de empleado**: la vista web pasa `includeDailyBreakdown: true` y renderiza una tabla de detalle diario debajo de la card de resumen de costos: día, horario (AM/PM), trabajado ("7h 11m"), descansos, con fila expandible (detalle de pausas + tipos de hora del día).
- **Días sin registro** dentro del rango: se muestran como fila atenuada "No laborado", solo hasta la fecha actual (días futuros del rango no se pintan).
- **Turnos en curso** (sin `clock_out`): en el reporte se muestran con badge "En curso", sin horas y sin sumar a totales (consistente con los totales actuales que los excluyen).
- **Listado `/admin/time-entries`**: filas enriquecidas con horario en AM/PM, trabajado y descansos en "Xh Ym", y detalle expandible de pausas. Backend: `break_hours`/`gross_hours` ya son columnas del entry (cero queries extra) + eager load `breaks.breakType` por página.
- **Formato de hora 12h**: nuevo helper `formatTime12h()` en `resources/js/lib/utils.ts`, fuente única para mostrar horas de reloj como `7:00 AM`.
- **Componente compartido** `DailyWorkTable.vue` (estructura visual común; en time-entries con columna de empleado, estado y acciones).
- **Exports PDF/Excel** ganan el horario de entrada/salida en su detalle diario existente (mismo dataset enriquecido).
- **Tests**: reforzar `GenerateEmployeeReportTest`, `ReportControllerTest` y `TimeEntryControllerTest` con aserciones campo a campo sobre los props de Inertia, pausas múltiples, pausas sin `ended_at`, orden cronológico y verificación de no-N+1.

## Capabilities

### New Capabilities

- `daily-work-breakdown`: desglose diario de trabajo por empleado — datos por día (horario, horas trabajadas, descansos, pausas detalladas, tipos de hora) y su presentación en el reporte de empleado y en el listado de registros, incluyendo días no laborados y turnos en curso.

### Modified Capabilities

- `admin-time-entry-management`: el listado muestra adicionalmente horario en formato 12h, horas trabajadas y de descanso en formato `Xh Ym`, y detalle expandible de pausas por registro.
- `hour-display-format`: nuevo requirement — las horas de reloj (entrada/salida, inicio/fin de pausas) se muestran en formato 12h con AM/PM mediante una utilidad reutilizable `formatTime12h()`.

## Non-goals

- No se modifica el cálculo ni la clasificación de horas (`CalculateWorkHours`, `RecalculateTimeEntry`).
- No se modifica el reporte de empresa (`/reports/company`) ni `GenerateCompanyReport`.
- No se cambia el layout de los exports PDF/Excel más allá de añadir el horario al detalle diario existente.
- No se agregan filtros nuevos ni cambia la paginación de `/admin/time-entries`.
- No se tocan las vistas del empleado (TimeClock, Calendar).

## Impact

- **Dominio afectado**: TimeTracking.
- **Migración de BD**: no requiere. Todos los datos ya están persistidos (`time_entries` con horas precalculadas, `breaks` con `duration_minutes`, `break_types` con `icon`/`color`).
- **Multi-tenancy**: sí toca datos con `company_id`. `GenerateEmployeeReport` ya usa `withoutGlobalScopes` con filtro explícito por empleado validado aguas arriba; `TimeEntryController::index` ya opera bajo `CompanyScope`. El eager load de `breaks.breakType` debe respetar el mismo aislamiento (las pausas pertenecen al entry, FK directa). Tests cross-company existentes deben seguir pasando.
- **Roles**: ambas vistas son `admin` + `super-admin` (sin cambio de autorización). `employee` sigue sin acceso (403).
- **Código afectado**:
  - `app/Domain/TimeTracking/Actions/GenerateEmployeeReport.php` (enriquecer breakdown)
  - `app/Http/Controllers/ReportController.php` (flag `includeDailyBreakdown: true`)
  - `app/Http/Controllers/Admin/TimeEntryController.php` (props del index)
  - `resources/js/pages/Reports/Employee.vue`, `resources/js/pages/Admin/TimeEntries/Index.vue`
  - Nuevo `resources/js/components/DailyWorkTable.vue`, `resources/js/lib/utils.ts` (`formatTime12h`)
  - `resources/views/exports/employee-report.blade.php`, `app/Exports/EmployeeReportExport.php` (columna horario)
  - i18n: `resources/js/locales/es.json`, `resources/js/locales/en.json`
  - Tests: `GenerateEmployeeReportTest`, `ReportControllerTest`, `Admin/TimeEntryControllerTest`
- **Performance**: sin riesgo — queries cubiertas por índices existentes (`unique(employee_id, date)`, FK de `breaks.time_entry_id`); volúmenes ≤31 días por reporte y 20 entries por página.
