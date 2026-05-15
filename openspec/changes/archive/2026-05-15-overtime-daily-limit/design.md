## Context

`CalculateWorkHours` clasifica minutos en 4 buckets (regular, night, sunday_holiday, overtime) iterando sobre segmentos entre breakpoints. Hoy el overtime solo se dispara cuando el acumulado semanal supera `max_weekly_hours`. Esto ignora días de trabajo largos cuando el total semanal aún está por debajo del límite.

La acción ya tiene infraestructura para cargar horas previas (`priorNetMinutes`) y calcular el breakpoint exacto donde cambia el estado. La extensión natural es agregar un segundo trigger diario con su propio acumulador y sus propios breakpoints.

## Goals / Non-Goals

**Goals:**
- Agregar `max_daily_hours` a `surcharge_rules` como configuración por empresa (default 8).
- Detectar overtime cuando se supera el límite diario **o** el semanal (lo que ocurra primero).
- Los dos triggers no se acumulan: un minuto ya contado como overtime no cuenta doble.
- Campo editable en la UI de Reglas de recargo.

**Non-Goals:**
- Recalcular automáticamente turnos históricos al cambiar la configuración.
- Manejo especial de turnos que cruzan medianoche con días laborales configurados distinto (pendiente revisión futura).
- Cambiar el comportamiento de night/sunday_holiday.

## Decisions

### 1. Dos acumuladores independientes en el loop

El loop principal mantiene dos contadores en paralelo:

```
accumulatedDailyNetMinutes   ← se reinicia al pasar cada midnight breakpoint
accumulatedWeeklyNetMinutes  ← corre toda la semana (comportamiento actual)

isOvertime = daily >= dailyLimit || weekly >= weeklyLimit
```

El acumulador diario se carga con `priorDailyNetMinutes` al inicio del turno (suma de net_hours del mismo empleado, mismo día calendario, otros TimeEntries completos). Al cruzar un breakpoint de medianoche, se resetea cargando las horas previas del nuevo día (si existen — normalmente 0 para el segundo día de un turno nocturno).

**Alternativa descartada**: Un solo acumulador combinado no funciona porque el diario debe reiniciarse por día mientras el semanal corre continuo.

### 2. Breakpoints diarios en buildBreakpoints()

Se agrega un breakpoint por cada día calendario dentro del turno, en el momento exacto donde `priorDailyNetMinutes + acumulado_del_tramo == dailyLimitMinutes`. Puede haber hasta N breakpoints diarios (uno por día en un turno multi-día).

El cálculo es análogo al breakpoint semanal existente pero relativo al inicio de cada día.

### 3. La migración no toca datos existentes

`max_daily_hours integer default 8 not null` — todas las empresas existentes heredan el default de 8h sin necesidad de backfill manual. El comportamiento de overtime cambia al recalcular turnos.

### 4. max_weekly_hours se mantiene como trigger activo

Ambos límites siguen siendo configurables y activos. El weekly actúa como safety net cuando los días son uniformes y cortos (ej: 7h × 7 días = 49h → overtime en el día 7 aunque ningún día superó 8h individualmente).

## Risks / Trade-offs

- [Turnos cruzando medianoche] El contador diario del segundo día arranca con las horas previas de ese día en otros turnos, pero si ese segundo día NO tiene entradas previas, arranca en 0 aunque el empleado haya trabajado horas del día anterior en este mismo turno. Esto es correcto por diseño (reinicio a 00:00) pero puede sentirse contraintuitivo. → Documentado como limitación conocida; revisión futura.

- [Performance] El nuevo query `priorDailyNetMinutes` es barato (misma estructura que el semanal, filtrado por date). Sin impacto esperado.

- [Tests existentes] Los tests de `CalculateWorkHours` usan entradas en días que no superan 8h diarias — no deberían romperse por el nuevo daily trigger. Verificar que todos pasan antes de mergear.

## Migration Plan

1. Nueva migración: `php artisan make:migration add_max_daily_hours_to_surcharge_rules`
2. Columna: `$table->integer('max_daily_hours')->default(8)->after('max_weekly_hours');`
3. Sin rollback de datos necesario (down: dropColumn).
4. Actualizar seeder/factory si setean columnas de surcharge_rules.

## Open Questions

- ¿Debería el acumulador diario del día 2 (en turno nocturno) incluir las horas que ya se contaron del mismo turno antes de la medianoche? Actualmente no (se cuentan como día 1). Marcar para revisión cuando haya casos reales de turnos nocturnos largos.
