# monthly-salary-cost-calculation Specification

## Purpose
Define cómo `CalculateReportCosts` liquida el costo según el `salary_type`: en modo mensual la hora ordinaria se absorbe en el salario base, los recargos suman solo el porcentaje y las horas extra el valor completo; en modo por hora conserva el cálculo por horas. Compone con el flag `payOvertime` sin alterarlo.
## Requirements
### Requirement: Cálculo de costo en modo salario mensual

`CalculateReportCosts` SHALL ramificar el cálculo según el `salary_type` del empleado. En modo `monthly`, el costo SHALL componerse del salario base del periodo más solo los porcentajes de recargo y el valor completo de las horas extra; en modo `hourly`, el comportamiento SHALL ser idéntico al actual.

**Business Rules (modo `monthly`):**
- Las horas `regular` NO suman costo por hora: están absorbidas en el salario base. Las horas se conservan en `totals` para información.
- Los recargos `night`, `sunday_holiday` y `night_sunday` suman **solo el porcentaje**: `horas × valor_hora × (% / 100)`, porque la hora base ya está incluida en el salario.
- Las 4 categorías de hora extra (`overtime_day`, `overtime_night`, `overtime_day_sunday`, `overtime_night_sunday`) suman el **valor completo**: `horas × valor_hora × (1 + % / 100)`, porque están fuera de la jornada que cubre el salario base.
- El `total` SHALL incluir el salario base del periodo (prorrateado, ver capability `payroll-pay-period`) más los recargos y las horas extra.
- `valor_hora` es el `hourly_rate` del empleado.

**Business Rules (modo `hourly`):**
- El cálculo SHALL ser idéntico al actual: cada bucket suma `horas × valor_hora × (1 + % / 100)`, con `regular` al 0% de recargo. No se suma salario base.

**Composición con `payOvertime`:**
- El flag `payOvertime` existente SHALL seguir aplicando: cuando es `false`, las 4 categorías de hora extra se calculan en `0`, se excluyen del `total` y se marcan `compensated: true`, en ambos modos de salario. El salario base y los recargos no se ven afectados por el flag.

**Authorization:**
- El cálculo se ejecuta dentro de los reportes; el acceso a los reportes mantiene las reglas de rol existentes (`admin`/`super-admin` por compañía; `employee` solo lo permitido hoy).

#### Scenario: Recargo nocturno suma solo el porcentaje en modo mensual
- **WHEN** se calcula el costo de un empleado `monthly` con 10 horas nocturnas y recargo nocturno del 35%
- **THEN** el subtotal nocturno es `10 × valor_hora × 0.35`
- **AND** NO incluye el valor base de esas 10 horas (ya está en el salario base)

#### Scenario: Horas extra suman valor completo en modo mensual
- **WHEN** se calcula el costo de un empleado `monthly` con 5 horas extra diurnas y recargo del 25%
- **THEN** el subtotal de extras diurnas es `5 × valor_hora × 1.25`

#### Scenario: Horas regulares no suman costo por hora en modo mensual
- **WHEN** se calcula el costo de un empleado `monthly` que solo trabajó horas ordinarias diurnas
- **THEN** el subtotal de horas `regular` es `0`
- **AND** las horas `regular` siguen visibles en `totals`
- **AND** el `total` es el salario base del periodo

#### Scenario: Modo por horas conserva el cálculo actual
- **WHEN** se calcula el costo de un empleado `hourly`
- **THEN** cada bucket suma `horas × valor_hora × (1 + recargo%)` con `regular` al 0%
- **AND** no se suma ningún salario base

#### Scenario: Mismo salario en meses de distinta duración
- **WHEN** un empleado `monthly` trabaja únicamente su jornada ordinaria toda una quincena de febrero (menos días calendario) y otra de octubre (más días calendario), sin recargos ni extras
- **THEN** el `total` de ambas quincenas es el mismo salario base del periodo
- **AND** la diferencia de días calendario no altera el `total`

#### Scenario: Horas extra no pagadas en modo mensual
- **WHEN** se calcula el costo de un empleado `monthly` con `payOvertime = false` y horas extra registradas
- **THEN** los subtotales de las 4 categorías de overtime son `0` y se marcan `compensated: true`
- **AND** el salario base y los recargos del periodo sí suman al `total`
