## Why

Cuando un empleado falta días no remunerados dentro del periodo de nómina (p. ej. una quincena), el admin necesita restar el valor de esos días del total a pagar. Hoy solo existen deducciones de monto fijo (`EmployeeAdjustment`), que obligan al admin a calcular a mano el valor de los días. Se necesita un input que reciba **días** y calcule el descuento automáticamente.

## What Changes

- Nuevo input **"Descontar días"** (entero, `≥ 0`) en el reporte de empleado, junto a las decisiones de periodo existentes (`pay_overtime`, `dominical_payable_count`, `overtime_payable_hours`).
- El descuento es una **resta plana después del neto**: `final_pay = net_pay + bonos − deducciones − (deducted_days × normal_day_value)`. No toca el IBC ni la seguridad social.
- El valor por día proviene de `employees.normal_day_value` (campo ya existente); aplica a cualquier `salary_type` (`monthly` y `hourly`) y a cualquier periodo.
- Sigue el patrón de decisiones existente: filtro en URL → acción `Resolve` (override request → decisión guardada → default `0`) → `CalculateReportCosts` → se persiste al **exportar** (PDF/Excel), no al ver en pantalla.
- Nueva tabla de decisión por periodo `(company_id, employee_id, start_date, end_date)` con `BelongsToCompany`.
- El resumen de costo (pantalla, PDF, Excel) muestra la línea de descuento por días.

## Capabilities

### New Capabilities
- `report-day-deduction`: Descuento de días del total a pagar en el reporte de empleado — input de días, cálculo `días × normal_day_value` como resta plana post-neto, resolución por periodo con precedencia request→guardado→default, y persistencia al exportar.

### Modified Capabilities
<!-- No cambian requisitos de capacidades existentes; report-cost-summary-display solo suma una línea de presentación cubierta por la nueva capability. -->

## Impact

- **Dominio afectado:** TimeTracking (cálculo de costos y reporte) + Employee (lectura de `normal_day_value`).
- **Backend:**
  - `ReportFilterRequest`: validar `deducted_days` (`nullable, integer, min:0`).
  - `ReportController`: leer/resolver/persistir el valor y pasarlo a los builders y a `filters`.
  - `CalculateReportCosts`: recibir `deductedDays` + `normalDayValue`, calcular `day_deduction`, restarlo de `final_pay`, exponerlo en el output.
  - `GenerateEmployeeReport`: pasar `normal_day_value` del empleado y `deductedDays`.
  - Nueva acción `ResolveDayDeductionDecision`.
- **Migración de BD:** SÍ — nueva tabla `day_deduction_decisions` (multi-tenant, `company_id`).
- **Frontend:** `Reports/Employee.vue` (+ partial) con el input y preview del monto; claves i18n en `en.json`/`es.json`; regenerar Wayfinder tras cambios de ruta si aplica.
- **Exports:** `EmployeeReportExport` (Excel) y vista `exports.employee-report` (PDF) muestran la línea.
- **Multi-tenant:** la tabla lleva `company_id`; un admin solo actúa sobre su compañía; `super-admin` (`company_id = null`) no requiere filtro `where('company_id')`.
- **Roles:** solo `admin` y `super-admin` acceden a reportes; `employee` → 403.

## Non-goals

- No se implementa el sistema completo de novedades/ausencias (justificada vs injustificada, detección de días esperados por horario, tipos de novedad) documentado en `docs/novedades-y-prorrateo-por-ausencias.md`. Esto es la versión manual/express.
- No se reduce el IBC ni la seguridad social por los días descontados (resta plana post-neto, por decisión de diseño).
- No se admiten días fraccionados (solo enteros).
- No se aplica al reporte de empresa en esta fase (solo reporte de empleado).
