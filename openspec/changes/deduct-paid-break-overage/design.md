## Context

El cálculo de horas vive en el dominio `TimeTracking`. Hoy, al cerrar un turno o recalcularlo:

- `ClockOut::execute()` y `RecalculateTimeEntry::execute()` calculan `gross_hours` (clock_in → clock_out), `break_hours` (suma de pausas **no pagadas** finalizadas) y `net_hours = max(0, gross_hours − break_hours)`.
- `CalculateWorkHours::execute()` toma `net_hours`/`gross_hours`, deriva `netRatio = net/gross` y reparte minuto a minuto el tiempo en los 8 tipos de hora.
- Las pausas pagadas no descuentan nada. `TimeEntry::paidBreakHours()` solo expone el total pagado para la UI ("no descuentan tiempo trabajado").

`break_types` ya tiene `max_duration_minutes` (nullable). El requerimiento es descontar de `net_hours` el **exceso** de cada pausa pagada por encima de su límite, dejando la porción permitida como tiempo pagado, y mostrarlo en el panel admin.

Restricciones: multi-tenant (`company_id` en todas las tablas), reportes que agregan a nivel de BD (no iteran en PHP), y la invariante de `8-hour-type-classification`: la suma de los 8 tipos = `net_hours`.

## Goals / Non-Goals

**Goals:**
- Descontar de `net_hours` el exceso de pausas pagadas sobre `max_duration_minutes`.
- Persistir el exceso descontado para agregación eficiente y visualización.
- Que el descuento fluya a los 8 tipos de hora sin cambiar `CalculateWorkHours`.
- Mostrar el descuento de forma clara y comprensible en el panel administrativo.

**Non-Goals:**
- Cambiar pausas no pagadas, recargos, o la lógica de clasificación.
- Recalcular registros históricos.
- Impedir que el empleado se exceda; solo se descuenta.

## Decisions

### 1. Nueva columna `time_entries.paid_break_overage_hours` en vez de derivarla on-the-fly
`decimal(5,2) default 0.00`, después de `break_hours`. Persistirla mantiene la consistencia con `gross_hours`/`break_hours`/`net_hours` (que ya son columnas) y permite que `GenerateEmployeeReport` la agregue con `SUM(...)` a nivel de BD, igual que el resto de totales, sin N+1.
**Alternativa descartada:** calcularla siempre desde las pausas en PHP. Rompe el patrón de agregación por BD de los reportes y obliga a cargar pausas en cada agregación.

### 2. `break_hours` sigue significando solo pausas no pagadas; el exceso va en columna aparte
Mantener `break_hours` = pausas no pagadas evita reetiquetar la columna que la UI muestra como "descansos no pagados". El exceso pagado es un concepto distinto con su propia columna y su propia presentación. Fórmula final:
`net_hours = max(0, gross_hours − break_hours − paid_break_overage_hours)`.
**Alternativa descartada:** sumar el exceso a `break_hours`. Mezclaría dos conceptos y mostraría exceso pagado bajo la etiqueta "no pagadas".

### 3. Cálculo del exceso por pausa, en un único método `TimeEntry::paidBreakOverageHours()`
Por cada pausa **finalizada** cuyo tipo es **pagado** y tiene `max_duration_minutes` definido:
`overage = max(0, duration_minutes − max_duration_minutes)`. Suma de todos / 60, redondeado a 2.
Pausas pagadas sin límite (`null`) → 0. Pausas no pagadas → no aplican (ya descuentan completo vía `break_hours`).
El método consulta la relación `breaks()` con su `breakType` (robusto haya o no eager loading), reflejando el patrón de `paidBreakHours()`. Se llama desde `ClockOut` y `RecalculateTimeEntry`, que son los dos únicos puntos que calculan `net_hours` — así la regla queda en un solo lugar.
**Alternativa descartada:** un `CASE` SQL en cada acción. Duplica lógica y es más frágil que un método de modelo reutilizable.

### 4. El descuento fluye a los 8 tipos sin tocar `CalculateWorkHours`
Como `net_hours` ya entra menor, `netRatio` baja y la reducción se reparte proporcionalmente entre los tipos de hora del turno. Se preserva la invariante "suma de 8 = net_hours". No se modifica `CalculateWorkHours`.

### 5. Presentación: exceso explícito y pausa marcada
- `GenerateEmployeeReport` expone `paid_break_overage_hours` por día (en `daily_breakdown`) y en `totals`.
- `BreakEntry::toDisplayArray()` agrega `overage_minutes` (exceso de esa pausa, 0 si no aplica) para marcar la pausa concreta que se excedió.
- `DailyWorkTable.vue` / `DailyWorkDayDetail.vue` y el listado `/admin/time-entries` muestran el exceso descontado (formato `Xh Ym` / minutos) cuando es > 0, con la pausa excedida señalada. El tiempo trabajado mostrado (net) ya refleja el descuento.
**Alternativa descartada:** restar el exceso de la columna "descansos pagados" mostrada. Confundiría al admin (parecería que el empleado descansó menos); es más claro mostrar el descanso real y el exceso descontado por separado.

## Risks / Trade-offs

- [Registros históricos no se recalculan → muestran la regla antigua] → Se documenta como Non-goal; el admin puede recalcular un registro editándolo (dispara `RecalculateTimeEntry`). Opcionalmente un comando de backfill futuro.
- [Pausa pagada sin `max_duration_minutes` no descuenta nunca] → Es el comportamiento deseado (pausas pagadas ilimitadas como "Baño"/"Médica"); se cubre con escenario explícito.
- [`net_hours` puede llegar a 0 con muchos excesos] → `max(0, ...)` ya lo acota; mismo patrón que hoy con pausas no pagadas.
- [Redondeo: exceso y break_hours se redondean por separado antes de restar] → Se redondea cada componente a 2 decimales como ya hace el código; diferencias de centésimas de hora son aceptables y consistentes con el resto del sistema.

## Migration Plan

1. Migración: agregar `paid_break_overage_hours` decimal(5,2) default 0.00 a `time_entries` (después de `break_hours`).
2. Desplegar backend (modelo, acciones, reporte) — registros nuevos y recálculos aplican la regla; los existentes mantienen su `paid_break_overage_hours = 0` hasta ser recalculados.
3. Desplegar frontend (tipos, componentes, i18n) tras `php artisan wayfinder:generate` (si aplica) y `npm run build`.
4. Rollback: revertir código y `down()` de la migración elimina la columna; `net_hours` de registros nuevos volvería a la fórmula anterior solo al recalcularse.

## Open Questions

- ¿Se desea un comando de backfill para recalcular registros históricos? (Fuera de alcance ahora; se puede proponer aparte.)
