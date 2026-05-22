## Why

El motor de clasificación de horas (`CalculateWorkHours`) colapsa 8 tipos de hora distintos en 4 buckets, causando que recargos como "extra nocturna dominical" (150%) se paguen como "extra diurna" (25%). Los campos de `surcharge_rules` para los 4 tipos adicionales ya existen en base de datos pero nunca se aplican.

## What Changes

- **BREAKING** Renombrar columna `time_entries.overtime_hours` → `overtime_day_hours`
- Agregar 4 columnas nuevas a `time_entries`: `overtime_night_hours`, `night_sunday_hours`, `overtime_day_sunday_hours`, `overtime_night_sunday_hours`
- Expandir el clasificador de `CalculateWorkHours` de 4 ramas a 8 ramas (match combinando `$isOvertime`, `$isSundayOrHoliday`, `$isNight`)
- Expandir `CalculateReportCosts` para calcular costos con los 8 tipos y sus recargos correspondientes
- Actualizar `GenerateEmployeeReport` y `GenerateCompanyReport` para agregar y retornar las 8 columnas
- Actualizar exports de Excel para mostrar 8 filas en el resumen
- Actualizar UI (Employee.vue, Company.vue) para mostrar 8 tipos en KPI cards y tabla de costos
- Agregar comentarios al modelo `TimeEntry` explicando el significado y condición de cada campo de horas

## Capabilities

### New Capabilities

- `8-hour-type-classification`: Clasificación precisa de horas en 8 tipos mutuamente excluyentes según la combinación de tres atributos: semana/dom-festivo × diurno/nocturno × dentro-límite/extra.

### Modified Capabilities

- `overtime-daily-limit`: El escenario "Overtime diario tiene prioridad sobre nocturno y dominical" cambia: overtime ya no es una categoría plana sino que se sub-clasifica en `overtime_day`, `overtime_night`, `overtime_day_sunday`, `overtime_night_sunday` según los atributos del minuto.
- `night-schedule-config`: El escenario final "Overtime diario tiene prioridad sobre nocturno y dominical" cambia de la misma forma — overtime se sub-clasifica, no aplana.

## Impact

**Backend:**
- `database/migrations/` — nueva migración: rename + 4 ADD COLUMN
- `app/Domain/TimeTracking/Models/TimeEntry.php` — 4 campos nuevos + comentarios PHPDoc
- `app/Domain/TimeTracking/Actions/CalculateWorkHours.php` — buckets y match 8-way
- `app/Domain/TimeTracking/Actions/CalculateReportCosts.php` — 8 inputs, 8 outputs
- `app/Domain/TimeTracking/Actions/GenerateEmployeeReport.php` — SQL SUM × 8
- `app/Domain/TimeTracking/Actions/GenerateCompanyReport.php` — SQL SUM × 8
- `app/Exports/EmployeeReportExport.php` — 8 filas en resumen
- `app/Exports/CompanyReportExport.php` — 8 filas en resumen

**Frontend:**
- `resources/js/types/models.ts` — 4 campos nuevos en TimeEntry
- `resources/js/pages/Reports/Employee.vue` — 8 KPI cards + hourTypeLabel()
- `resources/js/pages/Reports/Company.vue` — 8 tipos en tabla

**Tests:**
- `tests/Feature/WorkHourCalculationTest.php` — ~18 nuevos casos
- `tests/Feature/TimeTracking/CalculateWorkHoursTest.php` — actualizar existentes
- `tests/Unit/CalculateReportCostsTest.php` — ~6 nuevos casos

**Multi-tenant:** Todas las columnas son en `time_entries` que ya tiene `company_id`. No hay cambios en la lógica de scoping.

**Roles:** Solo admins y super-admins generan reportes. No cambia la autorización.

**Non-goals:**
- No se recalculan entradas históricas (sistema en desarrollo, sin datos de producción)
- No se cambian los valores de `surcharge_rules` ni su UI de configuración
- No se agrega nueva lógica de validación de turnos
