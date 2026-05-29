## ADDED Requirements

### Requirement: Selección de periodo de pago en reportes

El sistema SHALL permitir, en los reportes de empleado y de empresa, elegir entre presets de periodo de pago — **1ª quincena**, **2ª quincena** y **mes completo** — además del rango de fechas libre existente.

**Business Rules:**
- 1ª quincena resuelve el rango del día 1 al 15 del mes; 2ª quincena del día 16 al último día del mes; mes completo del día 1 al último día.
- El rango de fechas libre se conserva para casos como retiro a mitad de quincena o anticipos.
- La selección de periodo determina el divisor del salario base (ver requirement de prorrateo).

**Authorization:**
- El acceso a los reportes mantiene las reglas de rol existentes; la selección de periodo no cambia quién puede ver qué.

#### Scenario: Admin selecciona la primera quincena
- **WHEN** un admin elige el preset "1ª quincena" para un mes
- **THEN** el reporte se calcula sobre el rango día 1 a día 15 de ese mes

#### Scenario: Admin selecciona la segunda quincena de febrero
- **WHEN** un admin elige el preset "2ª quincena" para febrero
- **THEN** el reporte se calcula sobre el rango día 16 al día 28 (o 29) de febrero

#### Scenario: Admin usa un rango libre
- **WHEN** un admin selecciona un rango de fechas personalizado
- **THEN** el reporte se calcula sobre ese rango exacto

### Requirement: Prorrateo del salario base por mes comercial de 30 días

En modo `monthly`, el sistema SHALL prorratear el salario base del periodo usando un denominador fijo de **mes comercial de 30 días** (15 por quincena), de modo que el monto base no dependa de los días calendario reales del mes.

**Business Rules:**
- `base_periodo = (monthly_base_salary / divisor) × (días_pagables / días_base)`.
- `divisor` = 2 para quincena, 1 para mes completo.
- `días_base` = 15 para quincena, 30 para mes completo.
- En esta fase, `días_pagables` = los días del rango seleccionado acotados al periodo (cubre ingreso/retiro o rango parcial). **No** se descuentan ausencias (queda fuera de alcance; ver `docs/novedades-y-prorrateo-por-ausencias.md`).
- Para un periodo completo (quincena o mes íntegro), `días_pagables = días_base` y el base se paga completo, sin importar que el mes tenga 28, 30 o 31 días.
- El salario base SHALL exponerse en el reporte como un concepto propio, separado de recargos y horas extra.

**Authorization:**
- Sin cambios respecto al acceso a reportes existente.

#### Scenario: Quincena completa paga base íntegro
- **WHEN** se calcula el reporte de un empleado `monthly` para una quincena completa
- **THEN** el salario base del periodo es `monthly_base_salary / 2`
- **AND** es el mismo monto en febrero (28 días) y en octubre (31 días)

#### Scenario: Mes completo paga base íntegro
- **WHEN** se calcula el reporte de un empleado `monthly` para un mes completo
- **THEN** el salario base del periodo es `monthly_base_salary`

#### Scenario: Rango parcial por retiro a mitad de quincena
- **WHEN** se calcula el reporte de un empleado `monthly` que trabajó del día 1 al día 8 de una quincena
- **THEN** el salario base del periodo es `(monthly_base_salary / 2) × (8 / 15)`

#### Scenario: El salario base se muestra como concepto separado
- **WHEN** se genera el reporte de un empleado `monthly`
- **THEN** el salario base del periodo aparece como una línea propia
- **AND** los recargos y las horas extra aparecen como conceptos separados del base

#### Scenario: Modo por horas ignora el salario base
- **WHEN** se calcula el reporte de un empleado `hourly`
- **THEN** no se suma ni se muestra salario base
- **AND** el costo es el cálculo por horas existente
