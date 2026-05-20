## MODIFIED Requirements

### Requirement: CalculateWorkHours usa horario nocturno configurable
`CalculateWorkHours` SHALL leer `night_start_time` y `night_end_time` desde la `SurchargeRule` de la empresa en lugar de constantes. El algoritmo SHALL también leer `max_daily_hours` y `max_weekly_hours` de la misma `SurchargeRule` para determinar cuándo un minuto es overtime. Un minuto es overtime si el acumulado diario neto supera `max_daily_hours` **o** el acumulado semanal neto supera `max_weekly_hours`, lo que ocurra primero.

#### Scenario: Minutos dentro del rango nocturno configurado se clasifican como nocturnos
- **WHEN** `night_start_time = '22:00'` y `night_end_time = '05:00'`
- **WHEN** un empleado trabaja entre 22:00 y 05:00
- **THEN** esos minutos se clasifican como `night_hours`

#### Scenario: Minutos fuera del rango nocturno no se clasifican como nocturnos
- **WHEN** `night_start_time = '22:00'` y `night_end_time = '05:00'`
- **WHEN** un empleado trabaja entre 21:00 y 22:00
- **THEN** esos minutos NO se clasifican como `night_hours`

#### Scenario: Overtime diario tiene prioridad sobre nocturno y dominical
- **WHEN** un empleado supera `max_daily_hours` durante un rango nocturno o en domingo
- **THEN** esos minutos se clasifican como `overtime_hours`, no como `night_hours` ni `sunday_holiday_hours`
