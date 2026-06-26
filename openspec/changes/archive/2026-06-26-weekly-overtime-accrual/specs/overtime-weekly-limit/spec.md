## MODIFIED Requirements

### Requirement: CalculateWorkHours usa el límite semanal en minutos
`CalculateWorkHours` SHALL clasificar como overtime cualquier minuto neto que supere `max_weekly_minutes` acumulado en la semana ISO (lunes–domingo). En modo `overtime_accrual_mode = daily` el tope semanal actúa como trigger independiente del límite diario, sin doble cobro: un minuto ya clasificado como overtime por el trigger diario no vuelve a contarse por el semanal. En modo `overtime_accrual_mode = weekly` el tope semanal SHALL ser el **único** trigger de overtime. El límite SHALL usarse directamente en minutos (sin convertir desde horas).

#### Scenario: Límite semanal con minutos dispara overtime
- **WHEN** `max_weekly_minutes = 2520` (42 h) y `max_daily_minutes = 600` (10 h)
- **WHEN** un empleado acumula 42h en la semana sin disparar el límite diario y trabaja 30 min diurnos adicionales el mismo periodo
- **THEN** esos 30 min se clasifican como `overtime_day_hours = 0.5`

#### Scenario: No se cobra overtime doble entre triggers
- **WHEN** `overtime_accrual_mode = daily` y un minuto ya fue clasificado como overtime por el trigger diario
- **THEN** ese minuto NO vuelve a contabilizarse como overtime por el trigger semanal

#### Scenario: En modo semanal el tope semanal es el único trigger
- **WHEN** `overtime_accrual_mode = weekly`, `max_weekly_minutes = 2520` (42h) y `max_daily_minutes = 480` (8h)
- **WHEN** un empleado acumula 45h netas diurnas de semana en la semana ISO
- **THEN** `overtime_day_hours` totaliza 3.0 sin importar la distribución diaria
