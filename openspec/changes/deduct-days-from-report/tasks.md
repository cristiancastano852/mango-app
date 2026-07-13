## 1. Persistencia (BD y modelo)

- [x] 1.1 Crear migración `day_deduction_decisions`: `id`, `company_id` (FK), `employee_id` (FK), `start_date` (date), `end_date` (date), `deducted_days` (unsignedInteger, default 0), `exported_by` (FK users nullable), `exported_at` (timestamp nullable), timestamps; único `(company_id, employee_id, start_date, end_date)`.
- [x] 1.2 Crear modelo `App\Domain\Company\Models\DayDeductionDecision` con `BelongsToCompany`, `$fillable` y casts de fechas (espejo de `DominicalPaymentDecision`).
- [x] 1.3 Crear factory `DayDeductionDecisionFactory` con estado por defecto y `for(company)`/`for(employee)`.
- [x] 1.4 Ejecutar `php artisan migrate` y correr un test de creación del modelo (`--filter`) para verificar tabla y scope multi-tenant.

## 2. Resolución de la decisión por periodo

- [x] 2.1 Crear acción `App\Domain\TimeTracking\Actions\ResolveDayDeductionDecision` con precedencia request → decisión guardada → default `0`; normalizar negativos a `0`; soportar `super-admin` sin filtro `company_id`.
- [x] 2.2 Test unitario de `ResolveDayDeductionDecision`: request manda sobre guardado, sin request usa guardado, sin ninguno devuelve `0`, negativo → `0`.

## 3. Cálculo del descuento

- [x] 3.1 Extender `CalculateReportCosts::execute` con `int $deductedDays = 0` y `float $deductionDayValue = 0.0`; calcular `dayDeduction = max(0, deductedDays) * deductionDayValue`; restarlo en `finalPay`; exponer `deducted_days`, `day_deduction_value`, `day_deduction` en el output.
- [x] 3.2 Actualizar `GenerateEmployeeReport::execute` para aceptar `int $deductedDays` y pasar `employee.normal_day_value` + `deductedDays` a `CalculateReportCosts`; incluir los nuevos campos en el payload del reporte.
- [x] 3.3 Tests de `CalculateReportCosts`: descuento reduce `final_pay` sin tocar IBC/seguridad social; `0` días sin efecto; `day_deduction_value = 0` → $0; días negativos → $0.

## 4. Controller y validación

- [x] 4.1 Añadir regla `deducted_days` (`nullable, integer, min:0`) en `ReportFilterRequest`.
- [x] 4.2 En `ReportController`: helper `requestDeductedDays()`; resolver vía `ResolveDayDeductionDecision` en `employee()`; pasar `deductedDays` al builder; incluirlo en `filters`.
- [x] 4.3 En `ReportController`: método `persistEmployeeDayDeduction()` (upsert a `day_deduction_decisions` con `exported_by`/`exported_at`) invocado desde `exportEmployeeExcel` y `exportEmployeePdf`.
- [x] 4.4 Tests de feature (`admin` y `super-admin`): ver reporte con `deducted_days` recalcula total; exportar persiste la decisión; ver sin exportar no persiste; aislamiento cross-company (`assertSessionHasErrors` / no visibilidad). Correr `vendor/bin/pint --dirty --format agent`.

## 5. Exports (PDF/Excel)

- [x] 5.1 Añadir la línea "Descuento por días" (días × valor + monto) a `EmployeeReportExport` (Excel).
- [x] 5.2 Añadir la línea en la vista `exports.employee-report` (PDF), separada de las Deducciones.
- [x] 5.3 Test de export que verifica presencia del descuento y `final_pay` con el descuento aplicado.

## 6. Frontend (reporte de empleado)

- [x] 6.1 Wayfinder no requiere regeneración (no hubo cambios de ruta; `deducted_days` es query param en rutas existentes).
- [x] 6.2 Añadir input entero "Descontar días" en `Reports/Employee.vue`, inicializado con el valor de `filters`, con preview del monto `deducted_days × day_deduction_value`; recargar el reporte con el filtro al cambiar.
- [x] 6.3 Mostrar la línea de descuento y advertencia cuando `final_pay ≤ 0` en el resumen de costo (pantalla).
- [x] 6.4 Agregar claves i18n en `resources/js/locales/en.json` y `es.json` (`reports.day_deduction.*`, `reports.costs.day_deduction`).
- [x] 6.5 `npm run build` exitoso; `vue-tsc` sin errores nuevos en `Reports/Employee.vue`.

## 7. Cierre

- [x] 7.1 Correr la suite relacionada (`php artisan test --compact --filter=...`) y `vendor/bin/pint --dirty --format agent`; confirmar todo verde.
