## 1. Migración y modelo

- [x] 1.1 Crear migración que agregue `paid_break_overage_hours` decimal(5,2) default 0.00 a `time_entries` (con `->after('break_hours')`) y su `down()` que la elimina; ejecutar `php artisan migrate`.
- [x] 1.2 En `TimeEntry`: agregar `paid_break_overage_hours` a `$fillable` y al cast `decimal:2` en `casts()`, con comentario PHPDoc del significado.
- [x] 1.3 Agregar método `paidBreakOverageHours(): float` en `TimeEntry` que sume, por cada pausa finalizada de tipo pagado con `max_duration_minutes` definido, `max(0, duration_minutes − max_duration_minutes)`, dividido entre 60 y redondeado a 2 (consultando `breaks()` con `breakType`, análogo a `paidBreakHours()`).

## 2. Cálculo de horas (acciones del dominio)

- [x] 2.1 En `ClockOut::execute()`: calcular `$paidBreakOverageHours = $timeEntry->paidBreakOverageHours()` y actualizar `net_hours = max(0, gross_hours − break_hours − paid_break_overage_hours)`, persistiendo `paid_break_overage_hours`.
- [x] 2.2 En `RecalculateTimeEntry::execute()`: aplicar la misma fórmula y persistir `paid_break_overage_hours` antes de llamar a `CalculateWorkHours`.
- [x] 2.3 Verificar que `CalculateWorkHours` no requiere cambios (opera sobre `net_hours`/`gross_hours`); confirmar con un test que la suma de los 8 tipos sigue igualando `net_hours` tras el descuento.

## 3. Tests backend (unidad y feature)

- [x] 3.1 Test unit de `TimeEntry::paidBreakOverageHours()`: pausa pagada excedida (25 min, límite 15 → 10), pausa pagada dentro de límite (→ 0), pausa pagada sin límite (`null` → 0), pausa no pagada (→ 0), pausa en curso (→ 0), múltiples excesos suman.
- [x] 3.2 Test feature de `ClockOut`: escenario objetivo (12:00→20:00, pausa pagada 14:00–14:25 límite 15) ⇒ `paid_break_overage_hours = 0.17` y `net_hours = 7.83`.
- [x] 3.3 Test feature de `RecalculateTimeEntry`/edición admin: agregar/editar/cambiar tipo de pausa recomputa `break_hours`, `paid_break_overage_hours` y `net_hours`; `assertDatabaseHas` incluyendo `status`, `paid_break_overage_hours` y `net_hours`.
- [x] 3.4 Test que confirma invariante: suma de los 8 tipos = `net_hours` con exceso descontado.
- [x] 3.5 Ejecutar los tests afectados con `php artisan test --compact --filter=...` hasta verde y correr `vendor/bin/pint --dirty --format agent`.

## 4. Reportes (exponer el dato)

- [x] 4.1 En `GenerateEmployeeReport::aggregateTotals()`: agregar `COALESCE(SUM(paid_break_overage_hours),0) as total_paid_break_overage` y exponerlo en `totals` como `paid_break_overage_hours`.
- [x] 4.2 En `GenerateEmployeeReport::mapDay()`: agregar `paid_break_overage_hours` al día (null si en curso) y al PHPDoc del método/clase.
- [x] 4.3 En `BreakEntry::toDisplayArray()`: agregar `overage_minutes` (exceso de esa pausa: `max(0, duration − max_duration_minutes)` si pagada con límite y finalizada; 0 si no aplica) y actualizar el PHPDoc del shape.
- [x] 4.4 Revisar `GenerateCompanyReport` y los exports (`EmployeeReportExport`/`CompanyReportExport`): incluir el exceso solo si aporta claridad; mantener consistencia de columnas. (Fila "Exceso pausas pagadas (descontado)" agregada al resumen de `EmployeeReportExport`; company report agrega `net_hours` ya descontado, sin cambios.)
- [x] 4.5 Test feature de `GenerateEmployeeReport`: `totals.paid_break_overage_hours` agregado y `daily_breakdown[].paid_break_overage_hours` + `overage_minutes` por pausa correctos.
- [x] 4.6 Ejecutar tests de reportes y `vendor/bin/pint --dirty --format agent`.

## 5. Frontend (visualización del descuento)

- [x] 5.1 Actualizar tipos en `resources/js/types/models.ts`: `DailyWorkDay.paid_break_overage_hours` y `DailyBreak.overage_minutes`.
- [x] 5.2 En `DailyWorkDayDetail.vue`: marcar la pausa excedida mostrando el exceso descontado (`overage_minutes`) con badge/indicador claro junto a su duración.
- [x] 5.3 En `DailyWorkTable.vue`: indicar el exceso de pausas pagadas descontado por día y en los totales (cuando > 0); ajustar la nota explicativa (`daily_work.paid_hint`) para reflejar que el exceso de pausas pagadas sí se descuenta.
- [x] 5.4 En `resources/js/pages/Admin/TimeEntries/Index.vue` (y `Reports/Employee.vue` si aplica): mostrar el exceso descontado de forma comprensible en fila/detalle expandible. (Index.vue muestra el exceso bajo descansos pagados; Reports/Employee.vue lo refleja vía `DailyWorkTable`.)
- [x] 5.5 Agregar claves i18n en `en.json` y `es.json` para el exceso de pausa pagada (etiqueta de columna/indicador y nota explicativa).
- [x] 5.6 Ejecutar `php artisan wayfinder:generate` (si cambian rutas) y `npm run build`; verificar build exitoso. (Sin cambios de rutas; build exitoso.)

## 6. Verificación final

- [x] 6.1 Correr `/check-tests` para validar cobertura por rol, casos de error y edge cases del descuento. (Agregados 2 tests de edición de pausa que generan overage.)
- [x] 6.2 Ofrecer al usuario correr la suite completa (`php artisan test --compact`) y confirmar verde. (499 passed, 2 skipped.)
