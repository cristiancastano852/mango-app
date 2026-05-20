## 1. Migración y modelo

- [x] 1.1 Crear migración `add_max_daily_hours_to_surcharge_rules` con columna `integer default 8 not null` en `surcharge_rules`
- [x] 1.2 Agregar `max_daily_hours` a `SurchargeRule::$fillable` y a `casts()` como `integer`
- [x] 1.3 Actualizar `SurchargeRuleFactory` para incluir `max_daily_hours`
- [x] 1.4 Ejecutar la migración y verificar con `database-schema`

## 2. Algoritmo CalculateWorkHours

- [x] 2.1 Agregar query `priorDailyNetMinutes`: suma de `net_hours` del mismo empleado, mismo `date`, excluyendo el entry actual (análogo a `priorNetMinutes`)
- [x] 2.2 Leer `dailyLimitMinutes` desde `$rules->max_daily_hours ?? 8` multiplicado por 60
- [x] 2.3 Extender `buildBreakpoints()` para calcular breakpoints diarios: por cada día calendario dentro del turno, calcular el momento exacto donde `priorDailyNetMinutes + acumulado == dailyLimitMinutes`
- [x] 2.4 Agregar `accumulatedDailyNetMinutes` como segundo acumulador en el loop principal; reiniciar al cruzar cada breakpoint de medianoche
- [x] 2.5 Cambiar condición `$isOvertime` a: `$accumulatedDailyNetMinutes >= $dailyLimitMinutes || $accumulatedWeeklyNetMinutes >= $weeklyLimitMinutes`
- [x] 2.6 Asegurar que `accumulatedWeeklyNetMinutes` sube con cada `$netContrib` independientemente de si es overtime o no

## 3. Validación y controlador

- [x] 3.1 Agregar regla `max_daily_hours: ['required', 'integer', 'min:1', 'max:24']` a `UpdateSurchargeRuleRequest`
- [x] 3.2 Agregar mensaje de error personalizado en español para `max_daily_hours`

## 4. Frontend

- [x] 4.1 Agregar campo `max_daily_hours` en `SurchargeRules.vue` junto a `max_weekly_hours`
- [x] 4.2 Ejecutar `php artisan wayfinder:generate` y `npm run build`

## 5. Tests

- [x] 5.1 Agregar escenarios en `WorkHourCalculationTest`: turno largo dispara overtime diario, semanal actúa como fallback, sin doble cobro
- [x] 5.2 Agregar escenario con acumulado previo del mismo día que acorta el tramo ordinario
- [x] 5.3 Actualizar `SurchargeRuleControllerTest`: incluir `max_daily_hours` en requests de actualización, validar rechazo de valores fuera de rango
- [x] 5.4 Ejecutar suite completa `php artisan test --compact` y verificar que pasan todos

## 6. Formateo y limpieza

- [x] 6.1 Ejecutar `vendor/bin/pint --dirty --format agent` sobre los archivos PHP modificados
- [x] 6.2 Actualizar `ai-specs/specs/base-standards.mdc` para reflejar que overtime tiene doble trigger (diario + semanal)
