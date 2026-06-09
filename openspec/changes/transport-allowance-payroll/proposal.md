## Why

En Colombia el auxilio de transporte ($249.095/mes en 2026, Decretos 1469/1470 de 2025) es un pago obligatorio que se suma al salario de los trabajadores y hace parte del costo laboral real. Hoy el cálculo de nómina lo deja explícitamente fuera de alcance (`config/payroll.php`), por lo que el `total` de los reportes subestima el costo de los empleados con salario mensual. Este cambio incorpora el auxilio al cálculo y a los reportes (PDF y Excel).

## What Changes

- Nuevo valor por defecto global `config('payroll.transport_allowance_monthly')` = `249095` (override por `PAYROLL_TRANSPORT_ALLOWANCE_MONTHLY`), análogo al SMLV.
- Nuevo campo por empresa `surcharge_rules.transport_allowance`, sembrado del default global por `CompanyObserver` al crear la empresa, editable desde la configuración de recargos.
- Nuevo flag por empleado `employees.receives_transport_allowance` que actúa como interruptor final de quién recibe el auxilio. Default **ON** para empleados nuevos en modo `monthly`; los `hourly` nunca lo reciben.
- El auxilio se **prorratea** con el mismo mes comercial de 30 días del salario base (quincena = ½, rango parcial proporcional), reutilizando la lógica de `payroll-pay-period`.
- El auxilio se **suma plano al `total`** del costo: NO es base de recargos ni de horas extra, y nunca se multiplica por horas.
- El auxilio se muestra como **línea propia "Auxilio de transporte"** en los reportes de empleado y de empresa (PDF y Excel), junto al salario base.
- Migración de datos: backfill `receives_transport_allowance = true` para todos los empleados `monthly` existentes.

## Capabilities

### New Capabilities
- `transport-allowance`: Configuración (default global + valor por empresa), elegibilidad por flag de empleado restringida a modo `monthly`, prorrateo por mes comercial y exposición como concepto propio en los reportes.

### Modified Capabilities
- `monthly-salary-cost-calculation`: el `total` en modo `monthly` SHALL incluir además el auxilio de transporte prorrateado cuando el empleado lo recibe; el auxilio se expone como concepto separado y no afecta el cálculo de recargos ni de horas extra.

## Impact

- **Dominio:** Company (`SurchargeRule`, `CompanyObserver`), Employee (modelo, flag, requests/actions de creación y edición), TimeTracking (cálculo y reportes).
- **Backend:** `config/payroll.php`; migración (`surcharge_rules.transport_allowance`, `employees.receives_transport_allowance` + backfill); `CompanyObserver`; `CalculateReportCosts` (nuevo parámetro y línea de detalle); `GenerateEmployeeReport` y `GenerateCompanyReport` (cálculo y agregado del auxilio); `CalculatePeriodBaseSalary`/prorrateador reutilizado.
- **Reportes:** `resources/views/exports/employee-report.blade.php`, `company-report.blade.php`; `EmployeeReportExport`, `CompanyReportExport`.
- **Frontend:** UI de empleado (switch del flag) y UI de configuración de recargos (campo del valor) vía Inertia/Vue + Wayfinder.
- **Multi-tenancy:** el valor del auxilio es `company_id`-scoped vía `SurchargeRule`; el flag vive en `employees` (ya scoped). Sin impacto en `super-admin`.
- **Roles:** sin cambios de autorización; `admin`/`super-admin` configuran y ven reportes según las reglas actuales.
- **Migración de BD:** sí (dos columnas nuevas + backfill de datos en producción).

## Non-goals

- No se implementa la verificación automática del tope de 2 SMLV; la elegibilidad la decide el flag por empleado.
- No se descuenta el auxilio por ausencias/incapacidades (sigue la misma simplificación del salario base; ver `docs/novedades-y-prorrateo-por-ausencias.md`).
- No se calcula el impacto del auxilio en prestaciones (cesantías, prima); este cambio cubre solo el costo del periodo en los reportes.
- No aplica a empleados en modo `hourly`.
