## 1. Configuración de tasas

- [x] 1.1 Agregar la sección `social_security` a `config/payroll.php` con `health` (env `PAYROLL_SS_HEALTH_RATE`, default 4) y `pension` (env `PAYROLL_SS_PENSION_RATE`, default 4), incluyendo bloque de comentario que aclare que es el aporte del empleado sobre el IBC y que el auxilio de transporte se excluye.

## 2. Cálculo de la deducción (núcleo)

- [x] 2.1 En `CalculateReportCosts::execute()` agregar el parámetro `array $socialSecurity = []` (porcentajes `health`/`pension`) y calcular `social_security_base = total − transport_allowance`, `health_deduction`, `pension_deduction` y `net_pay`, redondeados a 2 decimales. Exponerlos como claves planas en el `cost_summary` sin alterar `total` ni `details`. Actualizar el PHPDoc.
- [x] 2.2 En `GenerateEmployeeReport::execute()` inyectar las tasas leídas de `config('payroll.social_security')` al llamar a `CalculateReportCosts::execute()`. Actualizar el array-shape del PHPDoc de retorno (`cost_summary`).
- [x] 2.3 Escribir/actualizar tests de `CalculateReportCosts`: IBC monthly excluye auxilio, IBC hourly = total, 0 horas → 0 deducción, tasas desde parámetro dan 8%, `net_pay = total − deducciones`. Ejecutar `php artisan test --compact --filter=CalculateReportCosts`.
- [x] 2.4 Actualizar/añadir test de feature de `GenerateEmployeeReport` que verifique que el `cost_summary` retornado incluye `social_security_base`, `health_deduction`, `pension_deduction` y `net_pay` correctos. Ejecutar el filtro correspondiente.
- [x] 2.5 `vendor/bin/pint --dirty --format agent`.

## 3. Vista del reporte individual (frontend)

- [x] 3.1 En `resources/js/pages/Reports/Employee.vue` extender el tipo de `cost_summary` con los cuatro campos nuevos y renderizar, debajo del total, las filas "Salud (4%)", "Pensión (4%)" y "Neto a pagar".
- [x] 3.2 Ajustar la etiqueta del total para denotar "Total devengado" (antes de deducción) sin cambiar su valor.
- [x] 3.3 Agregar las claves i18n nuevas en `resources/js/locales/es.json` y `resources/js/locales/en.json` (salud, pensión, neto a pagar, total devengado).
- [x] 3.4 `npm run build` y verificar que compila sin errores.

## 4. Exports (PDF y Excel)

- [x] 4.1 En `resources/views/exports/employee-report.blade.php` agregar las filas de salud, pensión y neto a pagar debajo del total, leyendo del mismo `cost_summary`.
- [x] 4.2 En `app/Exports/EmployeeReportExport.php` agregar las filas de salud, pensión y neto a pagar con los mismos valores.
- [x] 4.3 Añadir/actualizar tests de export (Excel y PDF del reporte individual) que verifiquen la presencia y el valor de las filas de deducción y neto. Ejecutar el filtro correspondiente.
- [x] 4.4 `vendor/bin/pint --dirty --format agent`.

## 5. Verificación final

- [x] 5.1 Ejecutar la suite de tests afectada (`CalculateReportCosts`, `GenerateEmployeeReport`, exports de empleado) en modo `--compact` y confirmar que pasa.
- [x] 5.2 Confirmar que el reporte de empresa y sus exports NO muestran la deducción (fuera de alcance).
