## 1. Migración y modelo

- [x] 1.1 Crear migración `create_payroll_deductions_table` (`company_id`, `employee_id`, `effective_date` date, `days` decimal(4,1), `reason` string, `notes` text nullable, `created_by` nullable→users, timestamps; índice `(company_id, employee_id, effective_date)`)
- [x] 1.2 Actualizar `ai-specs/specs/data-model.md` con la tabla `payroll_deductions`
- [x] 1.3 Crear `app/Domain/TimeTracking/Models/PayrollDeduction.php` con `BelongsToCompany`, relaciones `employee()` y `createdBy()` con return types, y casts (`effective_date` => date, `days` => decimal:1)
- [x] 1.4 Crear enum PHP `PayrollDeductionReason` (`FaltaInjustificada`, `LicenciaNoRemunerada`, `PermisoNoRemunerado`, `Otro`) y castearlo en el modelo
- [x] 1.5 Crear `PayrollDeductionFactory` (con states por motivo) y seeder/demo si aplica

## 2. Cálculo del base con descuento (Actions puras)

- [x] 2.1 Extender `CalculatePeriodBaseSalary::execute()` para aceptar `float $deductedDays = 0` y devolver `max(0, salario × (díasComerciales − díasDescontados) / 30)`
- [x] 2.2 Test unitario de `CalculatePeriodBaseSalary`: quincena con 2 días → `salario × 13/30`; febrero = octubre con 1 día; clamp en 0 cuando descuento > pagables; `hourly`/0 días sin cambios
- [x] 2.3 Verificar que `CalculateReportCosts` sigue puro (recibe el base ya ajustado; no se toca su firma de horas) y que el total refleja el base con descuento

## 3. Integración en reportes

- [x] 3.1 En `GenerateEmployeeReport`: sumar `days` de `payroll_deductions` con `effective_date` en `[start, end]` del empleado, pasar `deductedDays` al cálculo del base y exponer el descuento (días, monto, flag "topado") en el payload
- [x] 3.2 En `GenerateCompanyReport`: agregar `SUM(days)` por `employee_id` en el rango (una query), mapear por empleado y restar en cada base; incluir el descuento en el desglose y en los totales
- [x] 3.3 Tests de `GenerateEmployeeReport`/`GenerateCompanyReport`: base con descuento, descuento fuera de rango no aplica, `hourly` ignora descuentos, descuento topado en 0

## 4. CRUD de descuentos (backend)

- [x] 4.1 Crear `StorePayrollDeductionRequest` (reglas + mensajes): `employee_id` exists con `company_id` condicional al tenant y `salary_type = monthly`; `days` numeric > 0; `reason` Enum; `effective_date` date; `notes` nullable
- [x] 4.2 Crear `PayrollDeductionController` delgado con `store` (setea `created_by`) y `destroy` (scope de tenant); delegar a Action `CreatePayrollDeduction` / `DeletePayrollDeduction`
- [x] 4.3 Registrar rutas con middleware de rol `admin`/`super-admin`; `php artisan wayfinder:generate`
- [x] 4.4 Actualizar `ai-specs/specs/domain-model.md` con el modelo y las Actions nuevas
- [x] 4.5 Feature tests por rol (admin, super-admin happy path; employee 403; cross-company assertSessionHasErrors; rechazo de `hourly`); `vendor/bin/pint --dirty --format agent`

## 5. UI en el resumen de quincena

- [x] 5.1 Revisar `components/ui/` (dialog, input, select, button) antes de crear nada nuevo
- [x] 5.2 En `Reports/Employee.vue`: acción "agregar descuento" (Form Inertia → `store` vía Wayfinder) y borrar (`destroy`), visible solo para empleados `monthly`; recargar reporte tras guardar
- [x] 5.3 Mostrar el descuento como línea propia bajo el salario base (días, monto, indicador "topado"); aplicar Tailwind consistente con el resto del reporte
- [x] 5.4 Agregar claves i18n en `resources/js/locales/en.json` y `es.json`
- [x] 5.5 `npm run build` y verificar el flujo completo en navegador

## 6. Cierre

- [x] 6.1 Ejecutar suite filtrada de los tests nuevos (`php artisan test --compact --filter=PayrollDeduction` y reportes); confirmar verde
- [x] 6.2 `openspec validate prorrateo-descuentos-novedades` y revisar consistencia proposal/design/specs/tasks

## 7. Paridad del descuento en exports (PDF + Excel)

> El total de los exports ya sale neto, pero el descuento NO aparece como línea propia y el "Salario
> base" se muestra ya descontado (engañoso en el desprendible). El spec `payroll-deductions` exige el
> descuento como línea separada del base bruto; falta cumplirlo en los 4 canales de export.
> El payload ya trae lo necesario: empleado → `deductions.amount`/`days`/`capped`; empresa →
> `deduction_amount`/`deduction_days` por empleado y `cost_summary.deductions` en el total. Base bruto = base neto + descuento.

- [x] 7.1 `resources/views/exports/employee-report.blade.php` (PDF): cuando `monthly` y `deductions.amount > 0`, mostrar el **base bruto** en la fila de salario base y añadir una fila "Descuento por novedad" en negativo con los días; marcar "topado" si `deductions.capped`
- [x] 7.2 `EmployeeReportExport` (Excel, `EmployeeReportSummarySheet::array()`): fila de base bruto + fila "Descuento por novedad" (negativa, con días) antes del `TOTAL`, solo `monthly` con descuento
- [x] 7.3 `resources/views/exports/company-report.blade.php` (PDF): fila "Descuentos" en el resumen de costos (`cost_summary.deductions`); en la tabla por empleado mostrar el descuento (columna o indicador) cuando aplique
- [x] 7.4 `CompanyReportExport` (Excel): fila "Descuentos (total)" en el sheet de resumen y columna "Descuento" en el sheet por empleado (`deduction_amount`)
- [x] 7.5 Tests en `ReportExportTest`: a nivel de datos/HTML (no binario) — assert que `EmployeeReportSummarySheet::array()`/`CompanyReportExport` incluyen la fila de descuento; render del blade a HTML (`view(...)->render()`) contiene la línea de descuento y el base bruto para un `monthly` con descuento
- [x] 7.6 `vendor/bin/pint --dirty --format agent`; correr `ReportExportTest` y confirmar verde
