## Context

Hoy `CalculateReportCosts` recibe un flag `payOvertime` (on/off) que paga o compensa las 6 categorías de overtime. El cambio `configurable-premium-surcharge-toggles` introdujo 3 flags premium (`pay_overtime_dominical`, `pay_overtime_holiday`, `pay_overtime_night`) que, cuando están en off, **colapsan** las 6 categorías de overtime en una sola bolsa diurna `effectiveOvertimeDayHours` a tarifa `overtime_day` (ver `CalculateReportCosts.php:125-134`).

Por otro lado, el dominical ya tiene un patrón completo de "cuántos pagar": `dominical_payable_count` (request) → `ResolveDominicalPaymentDecision` (precedencia) → `DominicalPaymentDecision` (persistencia al exportar) → `CalculateReportCosts` (`payable_count`). Este cambio replica ese patrón para horas extra, apoyándose en que con los 3 flags premium en off el overtime es una sola bolsa, así que limitar horas no requiere repartir entre buckets.

La decisión de overtime ya se persiste en `overtime_payment_decisions` (columna `pay_overtime`). Reusamos esa tabla en vez de crear una nueva.

## Goals / Non-Goals

**Goals:**
- Permitir definir, por empleado/empresa y periodo, cuántas horas extra se pagan, recalculando el total.
- Reusar el patrón existente de `dominical_payable_count` (resolución, persistencia, frontend) para minimizar superficie nueva.
- No introducir lógica de asignación entre buckets de overtime: el input solo aplica con overtime ya unificado.

**Non-Goals:**
- No tocar la clasificación de horas ni los buckets de `time_entries`.
- No soportar el input cuando hay varios buckets de overtime con tarifas distintas.
- No cambiar el switch `pay_overtime` ni el diferimiento semanal (`weekly-overtime-accrual`).

## Decisions

### Decisión 1: El input solo aplica con overtime unificado (3 flags premium en off)
**Por qué:** "pagar 5 de 10 horas" es ambiguo si las 10 están repartidas en buckets de distinta tarifa (¿cuáles 5?). Cuando `pay_overtime_dominical`, `pay_overtime_holiday` y `pay_overtime_night` están en off, el cálculo ya colapsa todo en `overtime_day` a una sola tarifa, así que el cap es un simple recorte de horas sobre una bolsa homogénea.
**Alternativas consideradas:** (a) cap único con orden de prioridad entre buckets — añade reglas de asignación opacas; (b) input por categoría (hasta 6) — UI pesada y poco usable. Ambas descartadas frente a la simplicidad de exigir la precondición.

### Decisión 2: Reusar `overtime_payment_decisions` con una columna nullable nueva
**Por qué:** la decisión de overtime del periodo ya vive ahí (`pay_overtime`). Agregar `overtime_payable_hours` (decimal nullable) mantiene una sola fila por `(company_id, employee_id, start_date, end_date)` y un solo upsert al exportar.
**Alternativas:** tabla propia tipo `DominicalPaymentDecision` — innecesaria, duplicaría llaves y upserts.

### Decisión 3: Nueva action `ResolveOvertimePayableHours`, espejo de `ResolveDominicalPaymentDecision`
**Por qué:** misma precedencia (request → guardado → default null), misma forma de normalizar (`max(0, valor)`), misma firma. Consistencia con el código existente.

### Decisión 4: Aplicar el cap en cost-time, no recalcular horas
**Por qué:** igual que el dominical y que el colapso premium, las horas trabajadas en `totals` no se tocan; solo el costo de overtime se calcula sobre el número pagable. Cero queries nuevas, sin recálculo histórico, idempotente al regenerar.

### Decisión 5: Tipo decimal (horas), no entero
**Por qué:** las horas extra son fraccionarias (p. ej. 5.5h). `dominical_payable_count` es entero porque cuenta días; aquí contamos horas. Validación `nullable|numeric|min:0`.

## Risks / Trade-offs

- **[El admin no entiende por qué a veces no aparece el input]** → El input se oculta cuando algún flag premium está en on; mostrar copy/i18n que explique la precondición ("disponible cuando el overtime se paga como una sola tarifa").
- **[Sobre-pago silencioso por error de digitación]** → Es intencional (saldar pendientes), igual que el dominical; se acota a `>= 0` y el total visible refleja el costo, dando feedback inmediato.
- **[Confusión entre `pay_overtime` off y `overtime_payable_hours = 0`]** → Ambos dan $0 pero por vías distintas; `pay_overtime` manda (compensado), el input solo opera dentro del caso "se pagan". Documentado en el spec.

## Migration Plan

1. Migración: `ALTER TABLE overtime_payment_decisions ADD COLUMN overtime_payable_hours DECIMAL nullable`. Sin backfill (null = pagar todas = comportamiento actual).
2. Backend: action de resolución, parámetro en `CalculateReportCosts`, propagación en generadores de reporte, validación en `ReportFilterRequest`, persistencia en `ReportController`.
3. Frontend: input condicionado a los 3 flags + i18n + exports.
4. Rollback: la columna nullable y el parámetro con default `null` no alteran el comportamiento existente; revertir frontend/backend deja el sistema como hoy.

## Open Questions

- (ninguna) — el alcance quedó cerrado en la fase de exploración.
