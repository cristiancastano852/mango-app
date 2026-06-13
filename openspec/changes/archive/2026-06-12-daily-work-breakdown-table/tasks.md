# Tasks: daily-work-breakdown-table

## 1. Backend — desglose diario enriquecido

- [x] 1.1 Enriquecer `GenerateEmployeeReport::getDailyBreakdown()`: reemplazar el `GROUP BY date` por lectura de entries con `with('breaks.breakType')` (pausas ordenadas por `started_at`), manteniendo el shape actual y agregando `clock_in`/`clock_out` (ISO 8601 con offset), `status` (con `in_progress` derivado para entries sin `clock_out`, horas en null) y `breaks[]` (name, icon, color, is_paid, started_at, ended_at, duration_minutes; pausa sin `ended_at` marcada en curso sin duración). Incluir entries sin `clock_out` solo en el breakdown — `aggregateTotals()` no cambia.
- [x] 1.2 Extraer el mapeo de pausas a un único punto reutilizable del dominio TimeTracking para que reporte y listado serialicen `breaks[]` con el mismo shape.
- [x] 1.3 Actualizar tests de `GenerateEmployeeReportTest`: campos nuevos campo a campo (clock_in/out ISO, breaks anidadas con icon/color/is_paid), pausas múltiples por día, pausa sin `ended_at`, orden cronológico, entry en curso presente en breakdown pero excluido de `totals`, soft-deleted excluido del shape nuevo. Correr `php artisan test --compact --filter=GenerateEmployeeReportTest`.

## 2. Backend — controllers y exports

- [x] 2.1 `ReportController::employee`: pasar `includeDailyBreakdown: true` (mantener `includeBreaksByType: false`).
- [x] 2.2 `Admin/TimeEntryController::index`: agregar al `through()` `gross_hours`, `break_hours`, `clock_in`/`clock_out` en ISO 8601 y `breaks[]` (eager load `breaks.breakType`, mapeo compartido de 1.2).
- [x] 2.3 Exports: filtrar filas `in_progress` en `EmployeeReportExport` y en el blade del PDF; añadir columna de horario (entrada/salida formateada en PHP con `g:i A`) al detalle diario de ambos exports.
- [x] 2.4 Tests: `ReportControllerTest::test_employee_report_returns_correct_data` con asserts campo a campo del prop `report.daily_breakdown` (AssertableInertia); `Admin/TimeEntryControllerTest` con asserts de los campos nuevos del index y test de conteo constante de queries (sin N+1 con 20 entries con pausas); `ReportExportTest` cubre exclusión de `in_progress` y columna horario. Correr los tres filtros.
- [x] 2.5 `vendor/bin/pint --dirty --format agent` y actualizar `ai-specs/specs/domain-model.md` si cambió la superficie de Actions.

## 3. Frontend — utilidades y componente compartido

- [x] 3.1 Agregar `formatTime12h(iso)` a `resources/js/lib/utils.ts` (Intl.DateTimeFormat `hour12: true`; null/inválido → `—`).
- [x] 3.2 Crear `resources/js/components/DailyWorkTable.vue` con skill `frontend-design` activo: columnas Día / Horario (AM/PM) / Trabajado / Descansos; fila expandible (`collapsible` de ui/) con pausas (icono + color del BreakType, tolerante a duraciones anómalas) y chips de tipos de hora > 0; indicadores: badge rojo domingo/festivo, rayo ámbar extras, badge azul "En curso", fila atenuada "No laborado"; fila de totales al pie; relleno de días faltantes en cliente hasta `min(period.end, hoy)` cuando `fillMissingDays`.
- [x] 3.3 Agregar claves i18n es/en (`resources/js/locales/{es,en}.json`) para todas las etiquetas nuevas.

## 4. Frontend — integración en las dos vistas

- [x] 4.1 `Reports/Employee.vue`: tipar y consumir `report.daily_breakdown`, renderizar `DailyWorkTable` debajo de la card de resumen de costos (con `fillMissingDays` y `period`). `npm run build` exitoso.
- [x] 4.2 `Admin/TimeEntries/Index.vue`: filas enriquecidas con la misma estructura visual (horario AM/PM vía `formatTime12h`, trabajado/descansos en `Xh Ym`, expandible de pausas) más columna empleado, badge de estado y acciones editar/borrar; reusar `DailyWorkTable` con slots o sus subcomponentes de celda si el encaje lo complica. `npm run build` exitoso.

## 5. Verificación final

- [x] 5.1 Correr suite afectada completa: `php artisan test --compact --filter='GenerateEmployeeReportTest|ReportControllerTest|TimeEntryControllerTest|ReportExportTest'` + `vendor/bin/pint --dirty --format agent` + `npm run build`.
- [x] 5.2 Revisión manual de ambas vistas (datos reales de dev): AM/PM correcto, "No laborado" sin días futuros, turno en curso visible sin sumar totales, expandibles, dark mode; en time-entries confirmar que crear/editar/eliminar siguen funcionando y que expandir una fila no se traga el click de las acciones.

## 6. Ajustes post-revisión

- [x] 6.1 Separar descansos en pagados y no pagados: `TimeEntry::paidBreakHours()` (helper compartido), campo `paid_break_hours` en `daily_breakdown` y en el index de time-entries; dos columnas con colores distintos (teal pagados, ámbar no pagados) y totales en ambas tablas; nota explicativa "los pagados no descuentan"; tests actualizados.
- [x] 6.2 Encabezados de columna en `/admin/time-entries` (Empleado, Horario, Trabajado, Descansos pagados, Descansos no pagados, Estado) con layout grid alineado al de la tabla del reporte.
