## Context

`RecalculateTimeEntry` recomputa las horas derivadas de un turno cerrado. Hoy calcula `break_hours` con un único query agregado:

```php
$breakHours = round(
    $timeEntry->breaks()
        ->whereNotNull('ended_at')
        ->whereHas('breakType', fn ($q) => $q->where('is_paid', false))
        ->sum('duration_minutes') / 60,
    2,
);
$netHours = round(max(0, $grossHours - $breakHours), 2);
```

Solo las pausas no pagadas restan; las pagadas se ignoran por completo, así que `max_duration_minutes` no tiene efecto sobre la nómina. Este Action es el único punto que produce `break_hours`/`net_hours`, y se invoca tanto en clock-out (employee) como al crear/editar un registro (admin). El resto del pipeline de clasificación (`CalculateWorkHours`, 8 buckets, `net_ratio`) deriva de `net_hours` y no requiere cambios.

## Goals / Non-Goals

**Goals:**
- Que el exceso de una pausa pagada sobre su `max_duration_minutes` se descuente del tiempo trabajado.
- Mantener intacto el comportamiento de pausas no pagadas y pausas pagadas sin tope.
- Que el admin pueda ver cuántos minutos se descontaron por exceso en el detalle del turno.

**Non-Goals:**
- Recalcular datos históricos en producción (se quedan como están).
- Cambiar `max_per_day` (ya enforced en `StartBreak`, regla independiente).
- Enforzar el tope al iniciar/finalizar la pausa (no se corta la pausa; solo se descuenta al calcular horas).
- Tocar el pipeline de clasificación de los 8 buckets.

## Decisions

**Decisión 1: El cálculo de `break_hours` deja de ser un `sum()` agregado y pasa a iterar las pausas finalizadas en memoria.**

Para aplicar `max(0, duration − tope)` por pausa pagada necesitamos lógica por fila, imposible en un único `sum()` SQL legible. Se cargan las pausas finalizadas con su `breakType` (eager load para evitar N+1) y se acumula:

```
por cada pausa finalizada:
  is_paid = false                        → aporta duration_minutes
  is_paid = true, max_duration = null    → aporta 0
  is_paid = true, max_duration = N       → aporta max(0, duration_minutes − N)
```

*Alternativa considerada*: expresión SQL `CASE WHEN`. Rechazada por ilegibilidad y porque el número de pausas por turno es pequeño (max_per_day acota el total); iterar en PHP es claro y suficiente.

**Decisión 2: La regla vive dentro de `RecalculateTimeEntry`, no en un Action nuevo.**

Es un solo punto de cálculo ya existente y todos los flujos (clock-out, crear/editar admin) pasan por él. Extraer un Action separado añadiría indirección sin beneficio. El cálculo del exceso se encapsula en un método privado del mismo Action para mantenerlo testeable y legible.

*Alternativa considerada*: un `CalculateBreakHours` Action dedicado. Rechazada por sobre-ingeniería para una sola fórmula; se puede extraer después si crece.

**Decisión 3: Visibilidad del exceso = derivada en el frontend, sin columna nueva.**

El detalle del turno (`TimeEntries/Edit.vue`) ya recibe las pausas con su `breakType` (incluido `is_paid` y `max_duration_minutes`) y `duration_minutes`. El exceso descontado por pausa es `max(0, duration − tope)` cuando `is_paid && tope`, calculable en la vista sin persistir nada nuevo.

*Alternativa considerada*: persistir `discounted_minutes` en `breaks`. Rechazada: dato derivable, evita migración y riesgo de desincronización.

## Risks / Trade-offs

- **[Inconsistencia histórico vs nuevo]** Turnos viejos no se recalculan, así que dos turnos idénticos pueden tener `net_hours` distinto según la fecha de cálculo → *Mitigación*: es una decisión explícita del proposal; documentar en specs que aplica solo a recálculos a partir del cambio. No se promete idempotencia retroactiva.
- **[`duration_minutes` negativos en prod]** Existe deuda conocida de duraciones negativas. `max(0, …)` del exceso y el `max(0, gross − break)` del neto absorben el caso, pero no se corrige el origen aquí → *Mitigación*: fuera de alcance; queda en la deuda existente.
- **[Cambio de comportamiento silencioso para empresas con topes ya configurados]** Empresas que ya tenían pausas pagadas con `max_duration_minutes` verán bajar el neto en turnos nuevos → *Mitigación*: es el comportamiento deseado del proposal; la visibilidad en el detalle del turno lo hace auditable.
