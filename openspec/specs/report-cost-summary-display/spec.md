# report-cost-summary-display Specification

## Purpose
Define las reglas de presentación del resumen de costos del reporte individual de empleado: qué filas de recargo se muestran u ocultan según los toggles de pago de la empresa, y cómo se expresan las cantidades (horas vs. días) según el modo de pago dominical/festivo. No cambia el cálculo de costos; solo su presentación, de forma consistente en la vista, el PDF y el Excel.

## Requirements

### Requirement: Ocultar filas de recargo premium con pago desactivado

El resumen de costos del reporte individual SHALL ocultar la fila de un recargo premium cuando el toggle de pago correspondiente de la empresa está desactivado, porque esas horas ya se reflejan en las filas base (regular, nocturno o extra). El mapeo SHALL ser:

- `dominical` → `pay_dominical` (pero ver excepción de modo `hourly` abajo)
- `night_dominical` → `pay_night_dominical`
- `night_holiday` → `pay_night_holiday`
- `overtime_day_dominical` y `overtime_night_dominical` → `pay_overtime_dominical`
- `overtime_day_holiday` y `overtime_night_holiday` → `pay_overtime_holiday`

La fila de festivo diurno (`holiday`) SHALL mostrarse siempre, ya que se paga por ley y no tiene toggle.

El sistema NO SHALL ocultar una fila que represente pago real: en modo `hourly` con dominical desactivado, las horas dominicales se pagan a tarifa ordinaria en su propia fila y esa fila SHALL permanecer visible.

Estas reglas SHALL aplicarse de forma consistente en la vista, el export PDF y el export Excel.

#### Scenario: Nocturno dominical desactivado se oculta

- **WHEN** la empresa tiene `pay_night_dominical` desactivado y se genera el reporte
- **THEN** la fila "nocturno dominical" no aparece en la vista, el PDF ni el Excel
- **AND** las horas correspondientes siguen reflejadas en la fila de recargo nocturno

#### Scenario: Extra festivo desactivado se oculta

- **WHEN** la empresa tiene `pay_overtime_holiday` desactivado
- **THEN** las filas "extra diurna festivo" y "extra nocturna festivo" no aparecen en el reporte

#### Scenario: Festivo diurno siempre visible

- **WHEN** existen horas festivas diurnas en el periodo
- **THEN** la fila de festivo diurno aparece siempre, sin importar configuración

#### Scenario: Dominical en modo hourly desactivado permanece visible

- **WHEN** la empresa tiene `pay_dominical` desactivado, el empleado es `hourly` y trabajó horas dominicales
- **THEN** la fila dominical permanece visible mostrando esas horas pagadas a tarifa ordinaria (no se oculta)

### Requirement: Mostrar días en lugar de horas en modo de pago por día

Cuando el modo de pago dominical es por día (`dominical_mode === 'day'`), la fila de recargo dominical del resumen de costos SHALL mostrar la cantidad de días pagados (`dominical_paid_days`) en lugar de las horas. Cuando el modo de pago festivo es por día (`holiday_mode === 'day'`), la fila de recargo festivo SHALL mostrar la cantidad de días festivos trabajados (`holiday_worked_days`) en lugar de las horas.

En modo por hora (`hour`) el comportamiento SHALL ser el actual: se muestran las horas.

Esta regla SHALL aplicarse de forma consistente en la vista, el export PDF y el export Excel.

#### Scenario: Recargo dominical por día muestra días

- **WHEN** `dominical_mode` es `day` y se pagaron 2 días dominicales
- **THEN** la fila de recargo dominical muestra "2 días" en lugar de las horas

#### Scenario: Recargo festivo por día muestra días

- **WHEN** `holiday_mode` es `day` y se trabajó 1 día festivo
- **THEN** la fila de recargo festivo muestra "1 día" en lugar de las horas

#### Scenario: Modo por hora conserva las horas

- **WHEN** `dominical_mode` es `hour`
- **THEN** la fila de recargo dominical muestra la cantidad de horas como hasta ahora

### Requirement: Autorización y multi-tenancy

Los ajustes de presentación SHALL reutilizar la autorización existente del reporte individual (accesible para `admin` sobre empleados de su empresa y `super-admin`) y operar sobre datos ya scopeados por `company_id`, sin persistir nada nuevo.

#### Scenario: Reglas aplican con los datos del reporte actual

- **WHEN** un admin genera el reporte individual de un empleado de su empresa
- **THEN** las filas se muestran/ocultan y se formatean según la configuración de esa empresa, sin afectar el acceso ni el cálculo
