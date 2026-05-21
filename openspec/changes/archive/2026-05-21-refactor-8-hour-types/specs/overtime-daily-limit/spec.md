## MODIFIED Requirements

### Requirement: CalculateWorkHours usa límite diario para detectar overtime
`CalculateWorkHours` SHALL clasificar como overtime cualquier minuto neto que supere `max_daily_hours` en el día calendario actual, incluso si el acumulado semanal no ha alcanzado `max_weekly_hours`. El sub-tipo de overtime SHALL determinarse por los atributos del minuto: diurno/nocturno y semana/dom-festivo, produciendo `overtime_day`, `overtime_night`, `overtime_day_sunday`, o `overtime_night_sunday`.

#### Scenario: Turno largo en día aislado dispara overtime diario (diurno)
- **WHEN** `max_daily_hours = 8` y `max_weekly_hours = 42`
- **WHEN** un empleado trabaja 10h netas en un solo día hábil diurno sin horas previas esa semana
- **THEN** `regular_hours = 8.0` y `overtime_day_hours = 2.0`

#### Scenario: Overtime diario en horario nocturno → overtime_night
- **WHEN** `max_daily_hours = 8` y el empleado ya acumuló 8h diurnas ese lunes
- **WHEN** trabaja adicionalmente de 21:00 a 23:00 el mismo lunes
- **THEN** `overtime_night_hours = 2.0` (no `night_hours`, no `overtime_day_hours`)

#### Scenario: Overtime diario en domingo diurno → overtime_day_sunday
- **WHEN** `max_daily_hours = 8` y el empleado trabaja el domingo de 06:00 a 18:00
- **THEN** `sunday_holiday_hours = 8.0` y `overtime_day_sunday_hours = 4.0`

#### Scenario: Overtime diario en domingo nocturno → overtime_night_sunday
- **WHEN** `max_daily_hours = 8` y el empleado trabaja el domingo de 06:00 a 23:00
- **THEN** `overtime_night_sunday_hours = 2.0` (21:00–23:00, límite ya excedido)

#### Scenario: Límite semanal sigue activo como trigger independiente
- **WHEN** `max_daily_hours = 8` y `max_weekly_hours = 42`
- **WHEN** un empleado trabaja 7h por día durante 6 días (42h) y trabaja 1h diurna el séptimo día hábil
- **THEN** `overtime_day_hours = 1.0` (semanal agotado)

#### Scenario: No se cobra overtime doble
- **WHEN** un minuto ya fue clasificado como overtime por el trigger diario
- **THEN** ese minuto NO vuelve a contabilizarse como overtime por el trigger semanal

#### Scenario: El breakpoint diario se calcula con precisión de segundos
- **WHEN** el empleado tiene 6h previas ese día y `max_daily_hours = 8`
- **WHEN** trabaja un turno de 4h diurnas en ese día hábil
- **THEN** las primeras 2h del turno son `regular_hours` y las últimas 2h son `overtime_day_hours`

#### Scenario: Turno que cruza medianoche reinicia el contador diario
- **WHEN** un turno va de 22:00 a 08:00 (10h brutas) con `max_daily_hours = 8`
- **WHEN** el empleado no tiene horas previas ese día ni el siguiente
- **THEN** el contador diario se reinicia a las 00:00
- **THEN** el sistema calcula overtime por separado para cada día calendario
