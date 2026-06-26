## Why

Por ley colombiana, el empleado aporta a seguridad social un 4% para salud y un 4% para pensión (8% en total) sobre su Ingreso Base de Cotización (IBC). Hoy el reporte individual de empleado muestra solo el costo devengado (base + recargos + horas extras + auxilio), sin reflejar esta deducción, por lo que el "neto a pagar" real al empleado no aparece en ningún lado.

## What Changes

- Agregar al cálculo del reporte individual de empleado la deducción de seguridad social a cargo del empleado: salud (4%) y pensión (4%) sobre el IBC.
- Definir el IBC del periodo como `total_devengado − auxilio_transporte` (el auxilio de transporte no hace parte del IBC por ley). Esto cubre por igual los modos `monthly` y `hourly`.
- Exponer en `cost_summary` nuevos campos: `social_security_base` (IBC), `health_deduction`, `pension_deduction` y `net_pay` (neto a pagar = total − deducciones).
- Mostrar las nuevas filas ("Salud 4%", "Pensión 4%", "Neto a pagar") en la vista del reporte individual (`Reports/Employee.vue`) y en sus exports PDF y Excel.
- Hardcodear las tasas en `config/payroll.php` (env-overridable) bajo `social_security.health` y `social_security.pension`, hoy fijas en 4% cada una.

## Capabilities

### New Capabilities
- `social-security-deduction`: cálculo y presentación de la deducción de seguridad social del empleado (salud + pensión) sobre el IBC del periodo, en el reporte individual y sus exports.

### Modified Capabilities
<!-- Ninguna: el total devengado y la clasificación de horas no cambian; solo se añaden filas derivadas. -->

## Impact

- **Dominio afectado**: TimeTracking (cálculo de costos del reporte). Sin tocar Company/Employee/Organization.
- **Código**:
  - `config/payroll.php` — nueva sección `social_security` (tasas env-overridable).
  - `app/Domain/TimeTracking/Actions/CalculateReportCosts.php` — calcula IBC, deducciones y neto; recibe las tasas como parámetro.
  - `app/Domain/TimeTracking/Actions/GenerateEmployeeReport.php` — inyecta las tasas leídas de `config('payroll.social_security')`.
  - `resources/js/pages/Reports/Employee.vue` — nuevas filas y etiqueta de "Total devengado".
  - `resources/views/exports/employee-report.blade.php` — mismas filas en PDF.
  - `app/Exports/EmployeeReportExport.php` — mismas filas en Excel.
  - `resources/js/locales/es.json` y `en.json` — etiquetas i18n.
- **Multi-tenant**: sin cambios; reutiliza el flujo de reporte por empleado ya scopeado por `company_id`. No se persisten datos nuevos.
- **Roles**: igual que el reporte actual (admin/super-admin que ya consumen el reporte individual). Sin cambios de autorización.
- **Migración de BD**: NO. Las tasas viven en config y las deducciones se calculan en tiempo de reporte.

## Non-goals

- Reporte de empresa (`CompanyReportExport`, `Reports/Company.vue`) — fuera de alcance.
- Piso del IBC (1 SMLMV prorrateado) y techo del IBC (25 SMLMV).
- Fondo de solidaridad pensional (+1% para ingresos ≥ 4 SMLMV).
- Salario integral (IBC = 70% del salario).
- Toggle on/off por empresa: se asume que todas las empresas son colombianas y la deducción siempre aplica.
- Aportes a cargo del empleador (pensión 12%, salud 8.5%, ARL, parafiscales).
