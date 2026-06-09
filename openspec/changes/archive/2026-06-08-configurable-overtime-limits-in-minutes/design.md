## Context

Los límites de horas ordinarias diario y semanal viven en `SurchargeRule` como enteros de **horas** (`max_daily_hours` int 1–24, `max_weekly_hours` int 1–168). `CalculateWorkHours` los convierte a minutos para clasificar overtime:

```php
$weeklyLimitMinutes = ($rules?->max_weekly_hours ?? 42) * 60;   // CalculateWorkHours:61
$dailyLimitMinutes  = ($rules?->max_daily_hours  ?? 8)  * 60;   // CalculateWorkHours:62
```

El motor ya trabaja en minutos, así que el cálculo soporta cualquier granularidad; la restricción es solo de captura (validación entera de horas + UI `step="1"`). La UI (`settings/SurchargeRules.vue`) renderiza ambos límites en un array genérico `fields` marcado `isInt: true`.

## Goals / Non-Goals

**Goals:**
- Almacenar ambos límites en **minutos** (`max_daily_minutes`, `max_weekly_minutes`) con backfill `× 60`.
- Capturar con dos inputs Horas + Minutos que combinan/descomponen a minutos.
- Usar los límites directamente en `CalculateWorkHours` (sin `× 60`).
- Mantener intacta la lógica de clasificación de overtime y la cobertura de tests.

**Non-Goals:**
- Cambiar la lógica de triggers (diario/semanal independientes, sin doble cobro).
- Minutos en otros campos de configuración.
- Límites por empleado.

## Decisions

### 1. Almacenar en minutos enteros, no en horas decimales
7 h 20 min = 7,333… h; en `decimal(4,2)` daría 7,33 → `× 60 = 439,8` min (error ~12 s). En minutos enteros (`440`) es exacto y coincide con cómo el motor ya cuenta. **Decisión:** columnas `integer` en minutos.
- *Alternativa descartada:* `decimal` de horas — imprecisa para minutos y obliga a `× 60` con redondeo.

### 2. Renombrar columnas (no agregar nuevas)
**Decisión:** renombrar `max_daily_hours` → `max_daily_minutes` y `max_weekly_hours` → `max_weekly_minutes`, con backfill `valor × 60` en la misma migración. Evita arrastrar columnas legacy y mantener dos fuentes de verdad. Como el código y los tests se actualizan en el mismo cambio, el renombre es seguro (deploy de código + migración juntos).
- *Alternativa descartada:* columnas nuevas conviviendo con las viejas — deja datos duplicados y ambigüedad sobre cuál es la verdad.

### 3. Combinar/descomponer horas+minutos en el frontend
**Decisión:** la página Vue mantiene dos inputs por límite (`*_hours` 0–N, `*_minutes` 0–59) en estado local; al enviar calcula `horas × 60 + minutos` y manda solo `max_daily_minutes`/`max_weekly_minutes`. Al cargar, descompone `Math.floor(min/60)` y `min % 60`. El backend solo conoce minutos.
- *Alternativa descartada:* un único input de minutos totales — menos intuitivo para el modelo mental "7h 20m" (decisión del usuario).
- Estos dos límites salen del array genérico `fields` (que asume un input simple) y pasan a un bloque propio con dos inputs.

### 4. Validación en minutos
`max_daily_minutes`: `integer min:1 max:1440`. `max_weekly_minutes`: `integer min:1 max:10080`. Los minutos (0–59) se validan en el front; el backend valida el total combinado.

## Risks / Trade-offs

- **[Renombre de columnas en producción]** → Migración con backfill `× 60` y `down` que revierte (`/ 60` + renombre inverso). Deploy de código y migración juntos; sin ventana donde el código lea una columna inexistente.
- **[Tests existentes referencian `max_daily_hours`/`max_weekly_hours`]** → Hay que actualizar factory, requests de test y asserts a los nuevos campos en minutos (8h → 480, 42h → 2520). Es churn mecánico pero necesario; los tests de clasificación de overtime deben seguir verdes con los valores equivalentes.
- **[Pérdida de precisión sub-minuto]** → No aplica: el negocio razona en minutos; el breakpoint diario ya se calcula con precisión de segundos a partir del límite en minutos.
- **[Otros consumidores de los campos viejos]** → Buscar usos de `max_daily_hours`/`max_weekly_hours` en todo el código (modelo, factory, request, Vue, tests) antes de renombrar.

## Migration Plan

1. Migración: renombrar `max_daily_hours` → `max_daily_minutes` y `max_weekly_hours` → `max_weekly_minutes` (mantener `integer`); en el mismo `up`, `UPDATE` multiplicando por 60 los valores existentes. `down` divide por 60 y revierte el nombre.
2. Actualizar `SurchargeRule` (`$fillable`, `casts()` → `integer`), `SurchargeRuleFactory` (480 / 2520), y cualquier seed/default.
3. `CalculateWorkHours`: usar `$rules?->max_daily_minutes ?? 480` y `$rules?->max_weekly_minutes ?? 2520` directamente, sin `× 60`.
4. `UpdateSurchargeRuleRequest`: reglas y mensajes en minutos.
5. Frontend: bloque de dos inputs por límite + combinación/descomposición + tipos TS.
6. Sin downtime con deploy conjunto; rollback = revertir migración (y código).

## Open Questions

- Ninguna pendiente. Alcance (diario + semanal), almacenamiento (minutos) y UI (horas + minutos) confirmados con el usuario.
