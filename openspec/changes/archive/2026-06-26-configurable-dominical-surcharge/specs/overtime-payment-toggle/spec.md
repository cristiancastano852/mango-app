## MODIFIED Requirements

### Requirement: Cálculo de costos con horas extra compensadas

`CalculateReportCosts` SHALL aceptar un flag `payOvertime`. Cuando es `false`, los costos de las **6 categorías de hora extra** resultantes del split dominical/festivo (`overtime_day`, `overtime_night`, `overtime_day_dominical`, `overtime_night_dominical`, `overtime_day_holiday`, `overtime_night_holiday`) SHALL calcularse en `0`, excluirse del `total`, y marcarse con `compensated: true` en `details[]`, conservando las horas y el porcentaje de recargo originales.

**Business Rules:**
- Las horas extra (`*_hours` en `totals`) nunca se modifican: siempre reflejan lo trabajado.
- El flag es único y cubre las 6 categorías de overtime a la vez; las horas no-overtime nunca se afectan.
- Cuando `payOvertime` es `true`, el comportamiento es idéntico al actual (sobre el nuevo set de columnas).

#### Scenario: No se pagan las horas extra
- **WHEN** se calculan los costos con `payOvertime = false` para un empleado con 8 horas extra nocturnas de semana
- **THEN** el reporte muestra 8 horas extra nocturnas
- **AND** el subtotal de esas horas es `0`
- **AND** el `details[]` de overtime nocturno tiene `compensated: true`
- **AND** el `total` no incluye el costo de las horas extra

#### Scenario: Overtime dominical y festivo también se compensan
- **WHEN** se calculan los costos con `payOvertime = false` para un empleado con horas `overtime_day_dominical` y `overtime_night_holiday`
- **THEN** ambos subtotales son `0` y se marcan `compensated: true`
- **AND** sus horas siguen visibles

#### Scenario: Se pagan las horas extra (comportamiento por defecto)
- **WHEN** se calculan los costos con `payOvertime = true`
- **THEN** cada subtotal de overtime es `horas × tarifa × (1 + recargo%)`
- **AND** el `total` incluye esos subtotales
- **AND** ningún `details[]` queda marcado como `compensated`

#### Scenario: Las horas no-overtime no se afectan
- **WHEN** se calculan los costos con `payOvertime = false`
- **THEN** los costos de horas ordinarias, nocturnas, dominicales y festivas se calculan normalmente y suman al `total`
