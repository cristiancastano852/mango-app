## Why

En modo `monthly` el salario base se prorratea por mes comercial de 30 días, pero hoy asume que **todos** los días del periodo son pagables: no hay forma de descontar ausencias no remuneradas (faltas, licencias/permisos sin goce de sueldo). Como los **horarios por empleado están deshabilitados**, el sistema no puede deducir automáticamente qué día "tocaba" trabajar; por eso el descuento debe ser **dirigido por el administrador** sobre el resumen de la quincena, con cada día descontado valiendo `salario_mensual / 30`, independiente de los días calendario del mes.

## What Changes

- Nueva tabla `payroll_deductions` (multi-tenant) que registra descuentos por novedad: `días`, `motivo`, `fecha efectiva` y notas, con auditoría (`created_by`).
- El administrador registra descuentos **desde el resumen de quincena** (Reports/Employee), indicando número de días + motivo; el reporte recalcula al vuelo.
- El salario base del periodo pasa a ser `prorrateo_actual − (Σ días_descontados × salario/30)`, con **clamp en 0** (sin deuda arrastrada).
- `GenerateEmployeeReport` y `GenerateCompanyReport` restan los descuentos cuya fecha efectiva cae en el rango del reporte; el desglose expone el descuento como línea propia.
- `CalculatePeriodBaseSalary` y `CalculateReportCosts` se mantienen **puros**: reciben el base ya ajustado / los días a descontar; no consultan novedades.
- Solo aplica a empleados `monthly`. En modo `hourly` no hay base que descontar; la UI no ofrece descuentos.

## Capabilities

### New Capabilities
- `payroll-deductions`: registro de descuentos por novedad (días + motivo + fecha efectiva, multi-tenant, admin-driven) y su efecto sobre el salario base prorrateado del periodo, con valor por día = `salario/30` y clamp en 0.

### Modified Capabilities
- `payroll-pay-period`: el prorrateo del salario base deja de asumir que todos los días son pagables; ahora resta los días de descuento del periodo. Se elimina la cláusula "no se descuentan ausencias (fuera de alcance)" y se corrige la redacción de la fórmula al modelo comercial `salario × días_pagables / 30` (febrero = octubre se preserva).

## Impact

- **Dominio:** TimeTracking (donde viven el prorrateo, los costos y los reportes).
- **Migración de BD:** SÍ — nueva tabla `payroll_deductions` (+ actualizar `ai-specs/specs/data-model.md` y `domain-model.md`).
- **Backend:** nuevo `Models/PayrollDeduction` + factory + seeder; nueva Action para sumar días del periodo; ajuste en `GenerateEmployeeReport` / `GenerateCompanyReport`; Form Request + métodos de controller (store/destroy) + rutas; `wayfinder:generate`.
- **Frontend:** acción "agregar/quitar descuento" en `Reports/Employee.vue` (y visualización del efecto en el base); i18n en/es. Posible línea de descuento en `Reports/Company.vue`.
- **Exports:** el descuento debe aparecer como línea propia (base bruto + descuento) en los desprendibles PDF y Excel de empleado y empresa (`EmployeeReportExport`, `CompanyReportExport`, blades `exports.*`), no solo en la pantalla.
- **Multi-tenant:** la tabla lleva `company_id` con `BelongsToCompany`; todas las consultas con scope de tenant.
- **Roles:** gestionar descuentos → `admin` y `super-admin` (con company resuelta); `employee` sin acceso. Cross-company → `assertSessionHasErrors`.

## Non-goals

- Detección automática de días esperados vía `Schedule.days_of_week` / festivos (los horarios están deshabilitados).
- Registrar ausencias **pagadas** (vacaciones, incapacidad, licencia remunerada) como informativas en el reporte → fase futura.
- Subsidio de incapacidad EPS/ARL (66.67%, días de carencia) → fase futura.
- Descansos compensatorios por trabajo dominical → fase futura.
- Cierre / bloqueo de un periodo de nómina ya liquidado → fase futura (por ahora basta la auditoría con `created_by`/timestamps).
- Afectar recargos u horas extra: el descuento toca **solo** el salario base.
