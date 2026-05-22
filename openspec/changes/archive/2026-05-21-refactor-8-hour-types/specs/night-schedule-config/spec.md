## MODIFIED Requirements

### Requirement: CalculateWorkHours usa horario nocturno configurable
`CalculateWorkHours` SHALL leer `night_start_time` y `night_end_time` desde la `SurchargeRule` de la empresa. El algoritmo SHALL leer `max_daily_hours` y `max_weekly_hours` de la misma `SurchargeRule` para determinar cuándo un minuto es overtime. Un minuto es overtime si el acumulado diario neto supera `max_daily_hours` **o** el acumulado semanal neto supera `max_weekly_hours`, lo que ocurra primero.

Cuando un minuto es overtime, el sub-tipo SHALL determinarse por la combinación de `$isNight` y `$isSundayOrHoliday`, produciendo `overtime_night`, `overtime_day_sunday`, o `overtime_night_sunday` según corresponda, en lugar de colapsar todos a un único tipo de overtime.

#### Scenario: Minutos dentro del rango nocturno configurado se clasifican como nocturnos
- **WHEN** `night_start_time = '22:00'` y `night_end_time = '05:00'`
- **WHEN** un empleado trabaja entre 22:00 y 05:00 en día hábil dentro del límite
- **THEN** esos minutos se clasifican como `night_hours`

#### Scenario: Minutos fuera del rango nocturno no se clasifican como nocturnos
- **WHEN** `night_start_time = '22:00'` y `night_end_time = '05:00'`
- **WHEN** un empleado trabaja entre 21:00 y 22:00
- **THEN** esos minutos NO se clasifican como `night_hours`

#### Scenario: Overtime en horario nocturno → overtime_night (no overtime genérico)
- **WHEN** un empleado supera `max_daily_hours` durante un rango nocturno en día hábil
- **THEN** esos minutos se clasifican como `overtime_night_hours`, no como `overtime_day_hours`

#### Scenario: Overtime en horario nocturno en domingo → overtime_night_sunday
- **WHEN** un empleado supera `max_daily_hours` durante un rango nocturno en domingo o festivo
- **THEN** esos minutos se clasifican como `overtime_night_sunday_hours`
