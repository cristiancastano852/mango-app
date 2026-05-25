## 1. Migraciones y schema

- [x] 1.1 Crear migración que agregue `pay_overtime_by_default` (boolean, default `true`) a `surcharge_rules`, con `down()` que la elimine
- [x] 1.2 Crear migración para la tabla `overtime_payment_decisions` (`company_id` FK, `employee_id` FK nullable, `start_date`, `end_date`, `pay_overtime` boolean, `exported_by` FK users nullable, `exported_at` timestamp, timestamps) con índice único `(company_id, employee_id, start_date, end_date)`
- [x] 1.3 Ejecutar migraciones y actualizar `ai-specs/specs/data-model.md` con la nueva columna y la nueva tabla

## 2. Modelos y factories

- [x] 2.1 Agregar `pay_overtime_by_default` al `$fillable` y a `casts()` (boolean) de `SurchargeRule`; actualizar su factory/seeder y el `CompanyObserver` para sembrar el default
- [x] 2.2 Crear modelo `OvertimePaymentDecision` en `app/Domain/Company/Models/` con `BelongsToCompany`, fillable, casts (`pay_overtime` boolean, `start_date`/`end_date` date, `exported_at` datetime) y relaciones `employee()` / `exportedBy()` con return types
- [x] 2.3 Crear factory para `OvertimePaymentDecision` (con estados para decisión de empleado y decisión de empresa con `employee_id` null)
- [x] 2.4 Actualizar `ai-specs/specs/domain-model.md` con el nuevo modelo

## 3. Lógica de cálculo

- [x] 3.1 Agregar parámetro `bool $payOvertime = true` a `CalculateReportCosts::execute()`; cuando es `false`, forzar los 4 costos de overtime a `0`, excluirlos del `total` y marcar `compensated: true` en sus `details[]` (no-overtime → `compensated: false`)
- [x] 3.2 Propagar el flag desde `GenerateEmployeeReport::execute()` hasta `CalculateReportCosts`
- [x] 3.3 Propagar el flag desde `GenerateCompanyReport::execute()` a todos los cálculos de costo de la agregación
- [x] 3.4 Tests unitarios de `CalculateReportCosts`: pagar (actual), no pagar (overtime en 0 y excluido del total, horas intactas), no-overtime sin afectar
- [x] 3.5 Crear Action `ResolveOvertimePaymentDecision` (o método en controller) que aplique la precedencia request → decisión guardada → `pay_overtime_by_default`, scoped por compañía/empleado/periodo

## 4. Form Requests y controller de ajustes

- [x] 4.1 Agregar regla `pay_overtime_by_default` (`boolean`) a `UpdateSurchargeRuleRequest` con su mensaje; actualizar `Settings/SurchargeRuleController@update`
- [x] 4.2 Tests feature de ajustes: admin y super-admin actualizan el default; `employee` recibe 403; cross-company falla

## 5. ReportController y persistencia

- [x] 5.1 Agregar `pay_overtime` (nullable boolean) a `ReportFilterRequest`
- [x] 5.2 En `employee()` y `company()`: resolver el flag con la precedencia y pasarlo como prop a la vista (sin persistir)
- [x] 5.3 En `exportEmployeeExcel/Pdf`: hacer `updateOrCreate` en `overtime_payment_decisions` (con `employee_id`) antes de generar el archivo, usando el flag del request
- [x] 5.4 En `exportCompanyExcel/Pdf`: hacer `updateOrCreate` con `employee_id` null (filtrando `whereNull('employee_id')`) antes de generar el archivo
- [x] 5.5 Pasar el flag a `GenerateEmployeeReport`/`GenerateCompanyReport` en `buildEmployeeReport`/`buildCompanyReport`
- [x] 5.6 `php artisan wayfinder:generate`
- [x] 5.7 Tests feature: exportar guarda la decisión (empleado y empresa); ver no persiste; reexportar sobrescribe; aislamiento multi-tenant; precarga desde decisión guardada y desde default

## 6. Frontend — ajustes de recargos

- [x] 6.1 Agregar toggle "Pagar horas extra por defecto" en `SurchargeRules.vue` usando el componente `checkbox`/switch existente de `components/ui/`
- [x] 6.2 Añadir claves i18n en `es.json` y `en.json` (N/A: SurchargeRules.vue usa español hardcoded por convención del archivo; las claves i18n del reporte se añaden en 7.4)

## 7. Frontend — reportes

- [x] 7.1 Añadir el switch "Pagar horas extra" en `Reports/Employee.vue`, inicializado con la prop resuelta, e incluir `pay_overtime` en los requests de export
- [x] 7.2 Mostrar costo `$0` + etiqueta "Compensado con tiempo" en las filas de overtime cuando `detail.compensated` sea true (pantalla)
- [x] 7.3 Añadir el switch independiente en `Reports/Company.vue` con la misma UI de compensación
- [x] 7.4 Añadir claves i18n para el switch y la etiqueta "Compensado con tiempo" en `es.json`/`en.json`

## 8. Exports (Excel + PDF)

- [x] 8.1 Actualizar `EmployeeReportExport` y `CompanyReportExport` para mostrar costo `0` y texto "Compensado con tiempo" en las filas de overtime compensadas
- [x] 8.2 Actualizar las vistas Blade `exports.employee-report` y `exports.company-report` con el mismo tratamiento
- [x] 8.3 `npm run build`

## 9. Cierre

- [x] 9.1 `vendor/bin/pint --dirty --format agent`
- [x] 9.2 `php artisan test --compact` de los archivos afectados (cálculo, ajustes, reportes) y verificación final
