## 1. Base de datos

- [x] 1.1 Crear migración `rename_and_expand_time_entries_hour_columns`: renombrar `overtime_hours` → `overtime_day_hours` y agregar 4 columnas nuevas (`overtime_night_hours`, `night_sunday_hours`, `overtime_day_sunday_hours`, `overtime_night_sunday_hours`) como `decimal(5,2) default 0.00 not null`
- [x] 1.2 Ejecutar la migración y verificar que la tabla tiene las 8 columnas correctas

## 2. Modelo TimeEntry

- [x] 2.1 Actualizar `app/Domain/TimeTracking/Models/TimeEntry.php`: renombrar referencias a `overtime_hours` → `overtime_day_hours`, agregar los 4 campos nuevos al `$fillable` (si aplica), y agregar comentarios PHPDoc a cada propiedad de horas explicando su condición exacta (semana/dom-fest × diurno/noc × dentro-límite/extra)

## 3. Motor de clasificación

- [x] 3.1 Actualizar `CalculateWorkHours`: cambiar el array `$buckets` para incluir los 8 tipos y reemplazar el `match` de 4 ramas por el `match` de 8 ramas que combina `$isOvertime`, `$isSundayOrHoliday`, `$isNight`
- [x] 3.2 Actualizar la llamada a `$entry->update()` al final de `execute()` para guardar los 8 campos (incluyendo el renombrado `overtime_day_hours`)

## 4. Cálculo de costos

- [x] 4.1 Actualizar `CalculateReportCosts`: leer los 8 keys de `$hourTotals`, calcular el costo de cada tipo con su recargo de `SurchargeRule`, y retornar 8 items en el array `details`
- [x] 4.2 Actualizar PHPDoc de `CalculateReportCosts::execute()` con la nueva firma de entrada y salida

## 5. Generación de reportes

- [x] 5.1 Actualizar `GenerateEmployeeReport`: agregar las 4 columnas nuevas en la query SQL `COALESCE(SUM(...))`, propagarlas en los arrays de `totals` y `daily_breakdown`
- [x] 5.2 Actualizar `GenerateCompanyReport`: mismo cambio en la query SQL y en los arrays de retorno por empleado y totales

## 6. Exports Excel

- [x] 6.1 Actualizar `EmployeeReportExport`: agregar las 4 filas nuevas en la hoja de resumen (`$summarySheet`) y las 4 columnas nuevas en la hoja de detalle diario
- [x] 6.2 Actualizar `CompanyReportExport`: agregar las 4 filas/columnas nuevas en el resumen y en la tabla de empleados

## 7. Buscar y reemplazar `overtime_hours`

- [x] 7.1 Buscar todas las referencias a `overtime_hours` en PHP, TypeScript y Vue y reemplazarlas por `overtime_day_hours` (usar `grep -r "overtime_hours"` para encontrarlas todas antes de editar)

## 8. TypeScript y Frontend

- [x] 8.1 Actualizar `resources/js/types/models.ts`: agregar los 4 campos nuevos al tipo `TimeEntry` (`overtime_night_hours`, `night_sunday_hours`, `overtime_day_sunday_hours`, `overtime_night_sunday_hours`)
- [x] 8.2 Actualizar `Employee.vue`: agregar los 4 labels nuevos en `hourTypeLabel()` y agregar las 4 KPI cards para los tipos nuevos
- [x] 8.3 Actualizar `Company.vue`: agregar los 4 tipos nuevos en el gráfico de series y en la tabla de resumen si aplica
- [x] 8.4 Agregar las keys de i18n faltantes para los 4 tipos nuevos en los archivos de traducción
- [x] 8.5 Correr `npm run build` y verificar que no hay errores de TypeScript ni de Vite

## 9. Tests — CalculateReportCostsTest

- [x] 9.1 Actualizar `test_details_array_contains_correct_surcharge_percentages`: cambiar `assertCount(4, ...)` a `assertCount(8, ...)`
- [x] 9.2 Agregar `test_overtime_night_applies_75_percent_surcharge`
- [x] 9.3 Agregar `test_night_sunday_applies_110_percent_surcharge`
- [x] 9.4 Agregar `test_overtime_day_sunday_applies_100_percent_surcharge`
- [x] 9.5 Agregar `test_overtime_night_sunday_applies_150_percent_surcharge`
- [x] 9.6 Agregar `test_total_cost_sums_all_8_types`
- [x] 9.7 Correr `php artisan test --compact tests/Unit/CalculateReportCostsTest.php` — todos deben pasar

## 10. Tests — WorkHourCalculationTest (nuevos casos)

- [x] 10.1 Agregar `test_sunday_night_classified_as_night_sunday` (Caso 1.4: dom 21:00–23:00, 0h previas)
- [x] 10.2 Agregar `test_overtime_night_when_daily_exhausted_and_nighttime` (Caso 1.6: lun 8h previas + 21:00–23:00)
- [x] 10.3 Agregar `test_overtime_day_sunday_when_daily_limit_exceeded` (Caso 1.7 / 4.2: dom 06:00–18:00)
- [x] 10.4 Agregar `test_overtime_night_sunday_all_three_conditions_met` (Caso 1.8 / 4.3 parcial: dom 8h previas + 21:00–23:00)
- [x] 10.5 Agregar `test_long_weekday_shift_produces_regular_overtime_day_overtime_night` (Caso 3.3: lun 06:00–23:00)
- [x] 10.6 Agregar `test_shift_crossing_midnight_resets_daily_and_returns_to_night` (Caso 3.4: lun 14:00–02:00 mar)
- [x] 10.7 Agregar `test_full_sunday_shift_produces_all_four_sunday_types` (Caso 4.3: dom 06:00–23:00)
- [x] 10.8 Agregar `test_sunday_shift_crossing_night_threshold` (Caso 4.1: dom 19:00–23:00)
- [x] 10.9 Agregar `test_holiday_night_shift_crossing_into_weekday` (Caso 4.5: festivo 21:00–01:00)
- [x] 10.10 Agregar `test_saturday_night_crossing_to_sunday_changes_surcharge_type` (Caso 5.1: sáb 22:00–04:00 dom)
- [x] 10.11 Agregar `test_sunday_night_crossing_to_monday_changes_surcharge_type` (Caso 5.2: dom 22:00–04:00 lun)
- [x] 10.12 Agregar `test_saturday_evening_crossing_night_then_sunday_midnight` (Caso 5.3: sáb 20:00–04:00 dom)
- [x] 10.13 Agregar `test_weekly_limit_exhausted_during_night_shift_produces_overtime_night` (Caso 6.2)
- [x] 10.14 Agregar `test_weekly_limit_exhausted_on_sunday_produces_overtime_day_sunday` (Caso 6.3)
- [x] 10.15 Correr `php artisan test --compact tests/Feature/WorkHourCalculationTest.php` — todos deben pasar

## 11. Tests — CalculateWorkHoursTest (actualizar existentes)

- [x] 11.1 Actualizar referencias a `overtime_hours` → `overtime_day_hours` en `CalculateWorkHoursTest.php`
- [x] 11.2 Correr `php artisan test --compact tests/Feature/TimeTracking/CalculateWorkHoursTest.php` — todos deben pasar

## 12. Suite completa y formato

- [x] 12.1 Correr `php artisan test --compact` — todos los tests del proyecto deben pasar
- [x] 12.2 Correr `vendor/bin/pint --dirty --format agent` para corregir formato PHP
