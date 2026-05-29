## 1. Configuración y migración de BD

- [x] 1.1 Crear `config/payroll.php` con el SMLV vigente (`smlv_monthly`) y el divisor de valor hora (`hourly_divisor` = 220), leído solo en config (no `env()` en código).
- [x] 1.2 Migración: agregar `employees.monthly_base_salary` (decimal 10,2 nullable).
- [x] 1.3 Migración: agregar `surcharge_rules.default_monthly_salary` y `default_hourly_rate` (decimal 10,2) con default basado en el SMLV; para filas existentes setear `default_monthly_salary = SMLV` y `default_hourly_rate = round(SMLV/220)`.
- [x] 1.4 Actualizar `ai-specs/specs/data-model.md` con las nuevas columnas de `employees` y `surcharge_rules`.

## 2. Modelos, casts y seeding por empresa

- [x] 2.1 `Employee`: agregar `monthly_base_salary` a `$fillable` y al cast (`decimal:2`); confirmar `salary_type` y `hourly_rate` presentes.
- [x] 2.2 `SurchargeRule`: agregar `default_monthly_salary` y `default_hourly_rate` a `$fillable` y casts (`decimal:2`).
- [x] 2.3 `CompanyObserver::created`: sembrar `default_monthly_salary` = SMLV y `default_hourly_rate` = `round(SMLV / 220)` al crear `SurchargeRule`.
- [x] 2.4 Factories: actualizar `EmployeeFactory` (estados `monthly`/`hourly`) y `SurchargeRuleFactory` (defaults de salario).
- [x] 2.5 Test del seeding por empresa: crear compañía → `surcharge_rules` tiene los defaults esperados.

## 3. Cálculo de costo (núcleo)

- [x] 3.1 `CalculateReportCosts`: extender la firma para recibir el modo de salario (`salary_type`) y el `baseAmount` prorrateado del periodo, manteniendo la Action pura (sin BD ni fechas).
- [x] 3.2 Implementar la rama `monthly`: `regular` subtotal 0; `night`/`sunday_holiday`/`night_sunday` solo el porcentaje; 4 overtime con valor completo; sumar `baseAmount` al `total`; conservar `payOvertime` (overtime en 0 + `compensated`).
- [x] 3.3 Conservar la rama `hourly` idéntica al comportamiento actual.
- [x] 3.4 `CalculateReportCostsTest`: casos por modo — recargo solo %, overtime completo, regular en 0, base sumado, modo hourly sin cambios, payOvertime=false en monthly, y febrero vs octubre con mismo base.
- [x] 3.5 Correr `php artisan test --compact --filter=CalculateReportCostsTest`.

## 4. Periodo de pago y prorrateo del base en reportes

- [x] 4.1 Resolver periodo en el flujo de reporte: el base se deriva de `[startDate, endDate]` vía `CalculatePeriodBaseSalary` (mes comercial 30 días); la fórmula colapsa a `salario × días_comerciales / 30`, cubriendo quincena/mes/rango parcial.
- [x] 4.2 `GenerateEmployeeReport`: calcular `base_periodo` con `CalculatePeriodBaseSalary` y pasarlo a `CalculateReportCosts`; exponer `base`, `salary_type` y `monthly_base_salary` en el payload.
- [x] 4.3 `GenerateCompanyReport`: igual, agregando empleados `monthly` y `hourly` mixtos (base agregado + base por empleado).
- [x] 4.4 `GenerateEmployeeReportTest` y `GenerateCompanyReportTest`: quincena/mes completos pagan base íntegro, rango parcial prorratea, mes calendario no altera el base, agregado mixto correcto.
- [x] 4.5 Correr los tests de los generadores con `--filter`.

## 5. Form Requests y controllers

- [x] 5.1 `StoreEmployeeRequest` y `UpdateEmployeeRequest`: validar `salary_type` (`in:monthly,hourly`), `monthly_base_salary` (requerido si monthly), `hourly_rate`; mensajes de error.
- [x] 5.2 `CreateEmployee`/`UpdateEmployee`: prellenar `monthly_base_salary` y `hourly_rate` desde los defaults de la compañía cuando no se envían.
- [x] 5.3 `UpdateSurchargeRuleRequest`: validar `default_monthly_salary` y `default_hourly_rate`; mensajes de error.
- [x] 5.4 Controller de reporte: el periodo se resuelve a `[startDate, endDate]` (presets + custom) y el generador deriva el base de esas fechas.
- [x] 5.5 `php artisan wayfinder:generate`.
- [x] 5.6 Tests de controller por rol (admin/super-admin happy path; employee 403; cross-company errores de sesión) en `ReportControllerTest` y los de empleado/surcharge.

## 6. Frontend

- [x] 6.1 Revisar `components/ui/` (select, input, label) antes de crear componentes nuevos.
- [x] 6.2 `EmployeeForm.vue` (+ `Create.vue`/`Edit.vue`): campos `salary_type` (select monthly/hourly), `monthly_base_salary` (condicional a monthly), `hourly_rate`; prellenar defaults.
- [x] 6.3 `Reports/partials/DateRangeFilter.vue`: presets ya incluyen quincena (`biweekly`) y mes; el rango libre (`custom`) cubre quincenas específicas y retiros a mitad de periodo. Sin cambio funcional necesario.
- [x] 6.4 `Reports/Employee.vue` y `Reports/Company.vue`: mostrar el salario base como línea separada de recargos y horas extra.
- [x] 6.5 `settings/SurchargeRules.vue`: campos para `default_monthly_salary` y `default_hourly_rate`.
- [x] 6.6 i18n: agregar claves nuevas en `resources/js/locales/en.json` y `es.json`.
- [x] 6.7 `npm run build` exitoso.

## 7. Exports y cierre

- [x] 7.1 `EmployeeReportExport` y `CompanyReportExport`: incluir el salario base del periodo como línea/columna (+ blade PDF de empleado).
- [x] 7.2 `ReportExportTest` y `OvertimePaymentDecisionTest`: actualizar para el modo monthly (overtime en 0 sigue válido; base presente).
- [x] 7.3 `vendor/bin/pint --dirty --format agent`.
- [x] 7.4 Correr la suite afectada con `php artisan test --compact` y confirmar verde (412 passed, 2 skipped).
