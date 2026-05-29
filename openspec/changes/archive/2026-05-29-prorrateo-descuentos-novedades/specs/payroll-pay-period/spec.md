## MODIFIED Requirements

### Requirement: Prorrateo del salario base por mes comercial de 30 días

En modo `monthly`, el sistema SHALL prorratear el salario base del periodo usando un denominador fijo de **mes comercial de 30 días** (15 por quincena), de modo que el monto base no dependa de los días calendario reales del mes, y SHALL restar los días de descuento por novedad del periodo.

**Business Rules:**
- `base_periodo = max(0, monthly_base_salary × (días_pagables − días_descontados) / 30)`.
- `días_pagables` = los días comerciales del rango seleccionado acotados al periodo (cubre ingreso/retiro o rango parcial); para una quincena completa son 15 y para un mes completo 30, sin importar que el mes tenga 28, 30 o 31 días.
- `días_descontados` = suma de los días de descuento por novedad cuya fecha efectiva cae en el rango del reporte (ver capability `payroll-deductions`). Cada día descontado vale `monthly_base_salary / 30`.
- Para un periodo completo sin descuentos, `días_descontados = 0` y el base se paga completo.
- Cuando los días descontados superan los días pagables, el `base_periodo` SHALL ser `0` (sin valor negativo).
- El salario base SHALL exponerse en el reporte como un concepto propio, separado de recargos, horas extra y descuentos.

**Authorization:**
- Sin cambios respecto al acceso a reportes existente.

#### Scenario: Quincena completa paga base íntegro
- **WHEN** se calcula el reporte de un empleado `monthly` para una quincena completa sin descuentos
- **THEN** el salario base del periodo es `monthly_base_salary / 2`
- **AND** es el mismo monto en febrero (28 días) y en octubre (31 días)

#### Scenario: Mes completo paga base íntegro
- **WHEN** se calcula el reporte de un empleado `monthly` para un mes completo sin descuentos
- **THEN** el salario base del periodo es `monthly_base_salary`

#### Scenario: Rango parcial por retiro a mitad de quincena
- **WHEN** se calcula el reporte de un empleado `monthly` que trabajó del día 1 al día 8 de una quincena
- **THEN** el salario base del periodo es `(monthly_base_salary / 2) × (8 / 15)`

#### Scenario: Quincena con descuento por novedad
- **WHEN** se calcula el reporte de un empleado `monthly` para una quincena completa con 2 días de descuento
- **THEN** el salario base del periodo es `monthly_base_salary × (15 − 2) / 30`

#### Scenario: El salario base se muestra como concepto separado
- **WHEN** se genera el reporte de un empleado `monthly`
- **THEN** el salario base del periodo aparece como una línea propia
- **AND** los recargos, las horas extra y los descuentos aparecen como conceptos separados del base

#### Scenario: Modo por horas ignora el salario base
- **WHEN** se calcula el reporte de un empleado `hourly`
- **THEN** no se suma ni se muestra salario base
- **AND** el costo es el cálculo por horas existente
