## 1. Migración y modelo

- [x] 1.1 Crear migración: renombrar `surcharge_rules.max_daily_hours` → `max_daily_minutes` y `max_weekly_hours` → `max_weekly_minutes` (mantener `integer`, defaults 480 y 2520). En el mismo `up`, `UPDATE` multiplicando por 60 los valores existentes. `down` divide por 60 y revierte los nombres. Ejecutar `php artisan migrate`.
- [x] 1.2 Actualizar `SurchargeRule`: `$fillable` y `casts()` (`max_daily_minutes` y `max_weekly_minutes` → `integer`), quitar los nombres viejos.
- [x] 1.3 Actualizar `SurchargeRuleFactory` con `max_daily_minutes => 480` y `max_weekly_minutes => 2520`.

## 2. Cálculo de horas

- [x] 2.1 En `CalculateWorkHours`: usar `$rules?->max_daily_minutes ?? 480` y `$rules?->max_weekly_minutes ?? 2520` directamente (eliminar el `* 60` de ambas líneas). Verificar que la acumulación previa (`sum('net_hours') * 60`) sigue intacta.
- [x] 2.2 Actualizar `tests/Feature/WorkHourCalculationTest.php` y `tests/Feature/TimeTracking/NegativeBreakDurationRegressionTest.php` a los nuevos campos en minutos (8h → 480, 42h → 2520). Añadir un test con límite en minutos (ej. `max_daily_minutes = 440` → breakpoint a 7h20m). Correr ambos archivos y confirmar verde.
- [x] 2.3 Actualizar `tests/Unit/CalculateReportCostsTest.php` (la `SurchargeRule` de `setUp` usa `max_weekly_hours`) al nuevo campo. Correr y confirmar verde.

## 3. Validación (request)

- [x] 3.1 En `UpdateSurchargeRuleRequest`: reemplazar reglas por `max_daily_minutes` (`required, integer, min:1, max:1440`) y `max_weekly_minutes` (`required, integer, min:1, max:10080`), con mensajes en minutos.
- [x] 3.2 Actualizar `tests/Feature/Settings/SurchargeRuleControllerTest.php`: `validPayload` envía los nuevos campos en minutos; ajustar tests de happy path, rango (0/1441 diario, 10081 semanal), no-entero, cross-company y super-admin. Añadir test que guarda 440 (7h20m). Correr y confirmar verde.

## 4. Frontend

- [x] 4.1 En `settings/SurchargeRules.vue`: sacar `max_daily_hours`/`max_weekly_hours` del array genérico `fields`; agregar un bloque propio con dos inputs (Horas + Minutos 0–59) por cada límite. Estado local que descompone `max_*_minutes` al cargar (`Math.floor(min/60)`, `min % 60`) y combina al enviar (`horas*60 + minutos`). Enviar solo `max_daily_minutes` y `max_weekly_minutes`. Actualizar el tipo TS de la regla.
- [x] 4.2 Agregar/ajustar i18n (es/en) para las etiquetas "Horas" / "Minutos" de ambos límites. `php artisan wayfinder:generate` si aplica y `npm run build`.

## 5. Cierre

- [x] 5.1 Ejecutar `vendor/bin/pint --dirty --format agent`.
- [x] 5.2 Correr la suite afectada (`php artisan test --compact` filtrando por work-hour/surcharge/report) y confirmar verde; preguntar al usuario si desea correr la suite completa.
