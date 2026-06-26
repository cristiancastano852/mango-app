## MODIFIED Requirements

### Requirement: CalculateWorkHours usa límite diario para detectar overtime
En modo `overtime_accrual_mode = daily`, `CalculateWorkHours` SHALL clasificar como overtime cualquier minuto neto que supere `max_daily_minutes` en el día calendario actual, incluso si el acumulado semanal no ha alcanzado `max_weekly_minutes`. En modo `overtime_accrual_mode = weekly`, el tope diario NO SHALL clasificar overtime. El límite SHALL usarse directamente en minutos (sin convertir desde horas). El sub-tipo de overtime SHALL determinarse por los atributos del minuto: diurno/nocturno y semana/dom-festivo, produciendo `overtime_day`, `overtime_night`, `overtime_day_sunday`, o `overtime_night_sunday`.

#### Scenario: Turno largo en día aislado dispara overtime diario (diurno)
- **WHEN** `overtime_accrual_mode = daily`, `max_daily_minutes = 480` y `max_weekly_minutes = 2520`
- **WHEN** un empleado trabaja 10h netas en un solo día hábil diurno sin horas previas esa semana
- **THEN** `regular_hours = 8.0` y `overtime_day_hours = 2.0`

#### Scenario: Límite con minutos define el breakpoint diario
- **WHEN** `overtime_accrual_mode = daily` y `max_daily_minutes = 440` (7 h 20 min)
- **WHEN** un empleado trabaja 8h netas diurnas en un solo día hábil sin horas previas esa semana
- **THEN** `regular_hours = 7.333…` (440 min) y `overtime_day_hours = 0.667…` (40 min)

#### Scenario: Overtime diario en horario nocturno → overtime_night
- **WHEN** `overtime_accrual_mode = daily`, `max_daily_minutes = 480` y el empleado ya acumuló 8h diurnas ese lunes
- **WHEN** trabaja adicionalmente de 21:00 a 23:00 el mismo lunes
- **THEN** `overtime_night_hours = 2.0` (no `night_hours`, no `overtime_day_hours`)

#### Scenario: Overtime diario en domingo diurno → overtime_day_sunday
- **WHEN** `overtime_accrual_mode = daily`, `max_daily_minutes = 480` y el empleado trabaja el domingo de 06:00 a 18:00
- **THEN** `sunday_holiday_hours = 8.0` y `overtime_day_sunday_hours = 4.0`

#### Scenario: Límite semanal sigue activo como trigger independiente
- **WHEN** `overtime_accrual_mode = daily`, `max_daily_minutes = 480` y `max_weekly_minutes = 2520`
- **WHEN** un empleado trabaja 7h por día durante 6 días (42h) y trabaja 1h diurna el séptimo día hábil
- **THEN** `overtime_day_hours = 1.0` (semanal agotado)

#### Scenario: No se cobra overtime doble
- **WHEN** un minuto ya fue clasificado como overtime por el trigger diario
- **THEN** ese minuto NO vuelve a contabilizarse como overtime por el trigger semanal

#### Scenario: El tope diario no aplica en modo semanal
- **WHEN** `overtime_accrual_mode = weekly`, `max_daily_minutes = 480` y `max_weekly_minutes = 2520`
- **WHEN** un empleado trabaja 10h netas diurnas en un solo día sin más horas esa semana
- **THEN** `overtime_day_hours = 0` y `regular_hours = 10.0`

#### Scenario: Turno que cruza medianoche reinicia el contador diario
- **WHEN** `overtime_accrual_mode = daily` y un turno va de 22:00 a 08:00 (10h brutas) con `max_daily_minutes = 480`
- **WHEN** el empleado no tiene horas previas ese día ni el siguiente
- **THEN** el contador diario se reinicia a las 00:00
- **THEN** el sistema calcula overtime por separado para cada día calendario
