## 1. Configuración y migración

- [x] 1.1 Añadir `transport_allowance_monthly` (default `249095`, env `PAYROLL_TRANSPORT_ALLOWANCE_MONTHLY`) a `config/payroll.php` y actualizar el comentario del SMLV que decía que el auxilio estaba fuera de alcance.
- [x] 1.2 Crear migración: `surcharge_rules.transport_allowance` (decimal:2, default 0) y `employees.receives_transport_allowance` (boolean, default true). En el mismo `up`, backfill: `transport_allowance` = `config('payroll.transport_allowance_monthly')` en `surcharge_rules` existentes y `receives_transport_allowance = true` en empleados `monthly` existentes. `down` elimina ambas columnas. Ejecutar `php artisan migrate`.

## 2. Modelos, factories y siembra

- [x] 2.1 Añadir `transport_allowance` a `$fillable` y al `casts()` (`decimal:2`) de `SurchargeRule`; actualizar su factory.
- [x] 2.2 Añadir `receives_transport_allowance` a `$fillable` y `casts()` (`boolean`) de `Employee`; actualizar su factory (default true).
- [x] 2.3 Actualizar `CompanyObserver` para sembrar `transport_allowance` desde `config('payroll.transport_allowance_monthly')` al crear `SurchargeRule`. Test del observer pasando.

## 3. Cálculo de costo y prorrateo

- [x] 3.1 Añadir parámetro `float $transportAllowance = 0.0` a `CalculateReportCosts::execute()`: sumarlo al `total`, incluir clave `transport_allowance` en el retorno y una entrada `type: 'transport_allowance'` en `details[]`. Actualizar el PHPDoc de array shape. Tests unitarios: suma al total, no multiplica por horas, no afecta recargos/extras, no se ve afectado por `payOvertime`.
- [x] 3.2 En `GenerateEmployeeReport`: calcular el auxilio del periodo reutilizando `CalculatePeriodBaseSalary::execute(transport_allowance, start, end)` solo si `salary_type === 'monthly'` y `receives_transport_allowance`; pasarlo a `CalculateReportCosts`. Exponer `transport_allowance` en `cost_summary`. Tests: monthly con flag on, flag off, hourly excluido, prorrateo quincena = ½.
- [x] 3.3 En `GenerateCompanyReport`: agregar la suma del auxilio de los empleados que lo reciben en el periodo y exponerla en el `cost_summary` agregado. Test del agregado.

## 4. Reportes PDF y Excel

- [x] 4.1 Añadir fila "Auxilio de transporte" condicional (`transport_allowance > 0`) en `resources/views/exports/employee-report.blade.php` y `company-report.blade.php`, junto a la fila de salario base.
- [x] 4.2 Añadir row "Auxilio de transporte" condicional en `EmployeeReportExport` y `CompanyReportExport`. Tests de export verificando la fila y el total.

## 5. Edición del valor (configuración de recargos)

- [x] 5.1 Añadir `transport_allowance` a `UpdateSurchargeRuleRequest` (regla numérica ≥ 0 + mensaje) y a la action/controller que persiste la config de recargos. Tests: admin actualiza (happy path), cross-company rechazado, validación de valor negativo.
- [x] 5.2 Frontend: añadir el campo "Auxilio de transporte" en la página Vue de configuración de recargos, con i18n. `php artisan wayfinder:generate` si aplica y `npm run build`.

## 6. Flag por empleado

- [x] 6.1 Añadir `receives_transport_allowance` a `StoreEmployeeRequest` y `UpdateEmployeeRequest` (boolean, default true al crear) y a `CreateEmployee`/`UpdateEmployee`. Tests: creación monthly default true, admin edita el flag, cross-company rechazado.
- [x] 6.2 Frontend: añadir el switch "Recibe auxilio de transporte" en el formulario de empleado (visible/aplicable solo en modo monthly), con i18n. `npm run build`.

## 7. Cierre

- [x] 7.1 Ejecutar `vendor/bin/pint --dirty --format agent`.
- [x] 7.2 Correr la suite afectada (`php artisan test --compact` filtrando por payroll/report/employee/surcharge) y confirmar verde; preguntar al usuario si desea correr la suite completa.
