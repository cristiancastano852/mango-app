## Why

En Colombia la mayoría de los empleados tienen un **salario base mensual fijo**: se les paga el mismo monto cada mes (y la mitad cada quincena) sin importar si el mes tiene 28, 30 o 31 días, porque la hora ordinaria ya está incluida en ese salario. El sistema actual liquida **todo por horas trabajadas × tarifa** (`CalculateReportCosts`), así que paga de más en meses largos (octubre) y de menos en meses cortos (febrero), lo cual es incorrecto para nómina colombiana. Esta fase corrige el modelo de costo y separa el salario base de los recargos y las horas extra.

## What Changes

- **Modo de salario por empleado**: se reactiva `salary_type` (`monthly` | `hourly`). `monthly` usa el nuevo modelo de salario base; `hourly` conserva el cálculo actual por horas. `hourly_rate` pasa a ser el **valor hora editable** que usan recargos y extras en ambos modos.
- **Nuevo campo** `employees.monthly_base_salary` (salario base mensual).
- **Defaults por empresa** en `surcharge_rules`: `default_monthly_salary` y `default_hourly_rate`, sembrados con el salario mínimo legal vigente (SMLV) al crear la empresa y editables por el admin en la configuración de recargos — mismo patrón que los porcentajes de recargo.
- **Nuevo modelo de costo para modo `monthly`** en `CalculateReportCosts`:
  - Las horas `regular` ya **no** se cobran por hora (absorbidas en el salario base); quedan informativas.
  - Los recargos (`night`, `sunday_holiday`, `night_sunday`) suman **solo el porcentaje** (`horas × valor_hora × %`), porque la hora base ya está en el salario.
  - Las 4 categorías de hora extra suman el **valor completo** (`horas × valor_hora × (1 + %)`) — sin cambios respecto a hoy.
  - El **salario base del periodo** se suma como concepto propio.
- **Periodos de pago en reportes**: presets de **1ª quincena / 2ª quincena / mes completo**, además del rango de fechas libre actual (necesario para retiros a mitad de quincena o anticipos).
- **Prorrateo del salario base por fechas** contra denominador fijo de **mes comercial de 30 días** (15 por quincena): el base cubre ingreso/retiro/rango parcial, pero **no** depende de los días calendario reales del mes. (El prorrateo por ausencias justificadas/injustificadas queda documentado para una fase posterior en `docs/novedades-y-prorrateo-por-ausencias.md`, **fuera de alcance** aquí.)
- **Frontend**: `EmployeeForm` captura `salary_type` + `monthly_base_salary` + `hourly_rate`; los reportes de empleado y empresa muestran el salario base como línea separada de recargos y extras; el filtro de fechas ofrece los presets de periodo.

## Capabilities

### New Capabilities
- `monthly-base-salary`: modelo de salario por empleado (`salary_type` monthly/hourly, `monthly_base_salary`, `hourly_rate` como valor hora editable) y defaults por empresa sembrados con el SMLV y editables por el admin.
- `monthly-salary-cost-calculation`: cálculo de costo en modo `monthly` — `regular` absorbido en el base, recargos solo el porcentaje, horas extra completas, y suma del salario base del periodo. Compone con el flag `payOvertime` existente sin alterarlo.
- `payroll-pay-period`: selección de periodo de pago (quincena/mes) más rango libre en los reportes, y prorrateo del salario base por fechas usando mes comercial de 30 días.

### Modified Capabilities
<!-- Ninguna: los specs existentes (8-hour-type-classification, overtime-payment-toggle, overtime-daily-limit) conservan sus requirements. La clasificación en 8 buckets y la semántica del flag payOvertime no cambian. -->

## Impact

- **Dominios afectados**: `Employee` (campos de salario), `Company` (defaults en `surcharge_rules`, `CompanyObserver`), `TimeTracking` (`CalculateReportCosts`, `GenerateEmployeeReport`, `GenerateCompanyReport`, reportes UI).
- **Requiere migración de BD**: sí. `employees.monthly_base_salary` (decimal 10,2 nullable); `surcharge_rules.default_monthly_salary` y `default_hourly_rate` (decimal 10,2). `salary_type` ya existe.
- **Multi-tenant**: `employees` y `surcharge_rules` llevan `company_id`; los defaults son por empresa. Las empresas existentes reciben los defaults del SMLV vía migración; los empleados existentes conservan `salary_type = hourly` (sin cambio de comportamiento).
- **Roles**: `admin` y `super-admin` gestionan los defaults de empresa y el salario de cada empleado, y eligen el periodo en los reportes. `employee` no accede a la configuración de costos (igual que hoy con `surcharge_rules`).
- **Código backend**: `CalculateReportCosts` ramifica por `salary_type`; los generadores de reporte resuelven el periodo y pasan el base prorrateado. Form Requests de empleado validan los nuevos campos; `UpdateSurchargeRuleRequest` valida los defaults.
- **Frontend**: `EmployeeForm.vue`, `Reports/Employee.vue`, `Reports/Company.vue`, `Reports/partials/DateRangeFilter.vue`, `settings/SurchargeRules.vue`; exports `EmployeeReportExport`, `CompanyReportExport`.
- **Tests**: `CalculateReportCostsTest`, `GenerateEmployeeReportTest`, `GenerateCompanyReportTest`, `ReportControllerTest`, `ReportExportTest`, `OvertimePaymentDecisionTest`, más cobertura nueva de defaults de empresa y prellenado en creación de empleado.

## Non-goals

- Prorrateo del salario base por **ausencias** (vacaciones, incapacidad, permisos, faltas injustificadas) y el modelo de **novedades** — documentado en `docs/novedades-y-prorrateo-por-ausencias.md` para una fase posterior.
- Cálculo del **subsidio de incapacidad** (porcentajes EPS/ARL, días de carencia).
- Actualización **automática** del SMLV cada año: el default se siembra al crear la empresa y el admin lo actualiza manualmente.
- Deducciones de seguridad social, retención en la fuente, prestaciones sociales (cesantías, prima) — no son parte del reporte de costo actual.
