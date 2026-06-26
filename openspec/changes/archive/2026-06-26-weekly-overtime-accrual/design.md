## Context

`CalculateWorkHours` clasifica cada `TimeEntry` minuto a minuto en 12 buckets al hacer clock-out (y al recalcular), anclando el acumulador semanal a la semana ISO (`startOfWeek(Carbon::MONDAY)`). El overtime se dispara hoy por **doble trigger**: `accumulatedDailyNetMinutes >= max_daily_minutes || accumulatedWeeklyNetMinutes >= max_weekly_minutes`. Los reportes (`GenerateEmployeeReport` / `GenerateCompanyReport`) solo **suman** esos buckets ya almacenados con `whereBetween('date', [inicio, fin])`; no recalculan nada.

Las empresas quieren un modo donde el extra solo se pague al superar el tope **semanal**, compensando días desbalanceados. Como las quincenas cortan semanas (cierre miércoles), hace falta una regla determinista de en qué periodo se liquida el extra de una semana partida.

## Goals / Non-Goals

**Goals:**
- Flag por empresa `overtime_accrual_mode` (`daily` default | `weekly`) sin romper el comportamiento actual.
- En modo `weekly`, clasificar overtime solo por el tope semanal (tope diario inerte).
- Liquidar el extra de cada semana en el periodo que contiene su domingo, sin ledger ni recálculo nuevo.
- Banner que comunique el rango de extra liquidado y el extra diferido.

**Non-Goals:**
- Selección manual del rango de semanas (variante manual): fase futura.
- Cambios en `CalculateReportCosts`, porcentajes de recargo, prorrateo de base o presets de periodo.
- Eliminar `max_daily_minutes`.

## Decisions

### 1. Flag de modo en `surcharge_rules` (string con default `daily`)
Columna `overtime_accrual_mode` string (no enum nativo de BD, por portabilidad SQLite/MySQL como el resto del proyecto), default `'daily'`, validada en el Form Request contra `['daily','weekly']`. Empresas existentes quedan en `daily` por el default → comportamiento idéntico.

**Alternativa descartada:** booleano `weekly_overtime_only`. Un string es más extensible si luego aparece un tercer modo (p. ej. `manual`).

### 2. Trigger condicional en `CalculateWorkHours`
Se reemplaza el cálculo de `$isOvertime` por uno dependiente del modo:
```php
$isOvertime = $mode === 'weekly'
    ? $accumulatedWeeklyNetMinutes >= $weeklyLimitMinutes
    : ($accumulatedDailyNetMinutes >= $dailyLimitMinutes
        || $accumulatedWeeklyNetMinutes >= $weeklyLimitMinutes);
```
En modo `weekly` se omite además agregar el breakpoint diario (los breakpoints semanales y de día/noche/medianoche siguen). El acumulador semanal ya es forward-only y el overtime cae en los últimos tramos de la semana, así que la clasificación por turno sigue siendo correcta sin reprocesar turnos anteriores.

**Alternativa descartada:** recalcular toda la semana al cierre. Innecesario: el orden cronológico de los acumuladores ya deja el excedente en la cola de la semana.

### 3. Liquidación "dueño del domingo" como doble ventana en el reporte
La clave: separar la ventana de suma de **horas base** (rango del periodo) de la de **horas extra** (semanas completas dueñas).

Dado el periodo `[S, E]` en modo `weekly`:
- `domingos_dueños` = domingos `d` con `S ≤ d ≤ E`.
- `ventana_extra` = `[ lunes(primer domingo dueño), último domingo dueño ]`.
- Horas extra del reporte = `SUM(overtime_* )` de `time_entries` con `date` en `ventana_extra`.
- Horas base/noche/dominical/festivo = `SUM(...)` con `date` en `[S, E]` (como hoy).
- Si no hay domingos dueños → overtime del periodo = 0.

Con quincenas contiguas las ventanas de extra de periodos vecinos no se solapan (partición exacta). La ventana de extra puede arrancar antes de `S`, capturando el extra diferido de la semana de cierre del periodo anterior — esto es intencional y suficiente para implementar "solo se difiere el recargo extra" (el salario base ya se pagó por fecha en su periodo).

**Implementación:** una acción/helper en `TimeTracking` (p. ej. `ResolveOvertimeSettlementWindow`) que dado `[S, E]` y el modo devuelve la ventana de extra. `GenerateEmployeeReport::aggregateTotals` y el agregado de empresa hacen **dos** sub-consultas: una para columnas base con `[S,E]` y otra para las 6 columnas de overtime con `ventana_extra`. En modo `daily`, la ventana de extra = `[S,E]` y el resultado es idéntico al actual (una sola consulta sirve, pero se mantiene la estructura).

**Alternativa descartada:** persistir un ledger de semanas liquidadas. Más complejo y propenso a inconsistencia; innecesario con periodos contiguos.

### 4. Banner y desglose diario (frontend)
El reporte expone en su payload: `overtime_accrual_mode`, las fechas de la `ventana_extra`, y si hay extra diferido (la semana de `E` no cierra en el periodo). El banner se renderiza en la página de reporte y en los exports PDF/Excel. El desglose diario marca como "diferido" los días cuya `date` cae después del `último domingo dueño` (pertenecen a la semana de cierre).

## Risks / Trade-offs

- **Rangos ad-hoc no contiguos** → el solapamiento de ventanas podría doble contar o saltar extra. Mitigación: el banner muestra el rango real de extra para que el admin lo detecte; documentar que la regla automática asume periodos contiguos.
- **Confusión base vs extra en la semana de cierre** (el día muestra horas extra que el resumen no paga) → Mitigación: marcado explícito "diferido" en el desglose + banner.
- **Reportes existentes en modo `daily`** → Mitigación: ventana de extra = `[S,E]`, comportamiento bit-a-bit idéntico; cubierto por tests de regresión.
- **Doble consulta de agregación** → impacto de performance mínimo (índice por `employee_id, date`); ambas consultas ya existían en forma de una sola.

## Migration Plan

1. Migración: agregar `overtime_accrual_mode` string default `'daily'` a `surcharge_rules`; actualizar `ai-specs/specs/data-model.md`.
2. Desplegar backend (motor + reporte) — sin efecto observable mientras todas las empresas estén en `daily`.
3. Habilitar el campo en el formulario de Reglas de recargo.
4. Rollback: revertir migración (drop column) y código; los buckets almacenados no cambian de forma, así que no hay datos que reparar.

## Open Questions

- Texto exacto del banner e i18n (resolver en implementación frontend).
- ¿El reporte de empresa muestra el mismo banner agregado? Asumido sí, con el rango común del periodo.
