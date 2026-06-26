# Mejoras pendientes — Recargo dominical y festivo configurable

> Origen: code review del PR #40 (`feature/configurable-dominical-surcharge`).
> Estado: **documentado, sin decidir**. Ninguna de estas mejoras está implementada.
> Fecha: 2026-06-25.

Contexto: el PR hace configurable el recargo dominical/festivo (por hora o por día,
sobre el "valor del día normal"), separa festivos de domingos en la clasificación de
horas (12 buckets) y agrega el control "K de N dominicales" en el reporte de empleado.
Lo siguiente son oportunidades de mejora detectadas en la revisión; **no son bloqueantes**.

---

## 1. Inconsistencia de tarifa de overtime en modo "por día" (severidad: media)

**Dónde:** `app/Domain/TimeTracking/Actions/CalculateReportCosts.php` (rama `dominicalMode === 'day'` vs `! $payDominical`).

En modo **por día**, el overtime dominical siempre se cobra con la tarifa de OT dominical
(`overtime_day_sunday` / `overtime_night_sunday`), **incluso** cuando el switch
"pagar dominicales" está en OFF y K = 0. En modo **por hora**, cuando no se paga el
dominical, el OT cae a la tarifa de OT de semana (`overtime_day` / `overtime_night`).

Es decir: en por-día el switch "no pagar dominical" **no apaga** el recargo del overtime
dominical.

**Por qué no se arregló de una:** es en parte una limitación inherente. No guardamos el
desglose de horas extra por día calendario, así que no se puede saber a cuáles de los K
días pagados corresponde el OT. Por eso en por-día el OT dominical se cobra uniforme.

**Decisión pendiente:** ¿en por-día el OT dominical debería seguir el switch global
(apagarse cuando no se pagan dominicales) o mantenerse siempre como OT dominical?
Si se quiere lo primero, habría que decidir una regla simple (p. ej. si K = 0 → todo el
OT dominical pasa a OT de semana).

---

## 2. Dos COUNT extra por reporte de empleado (severidad: baja — performance) — ✅ APLICADO (2026-06-25)

**Dónde:** `app/Domain/TimeTracking/Actions/GenerateEmployeeReport.php`.

Eran **2 queries adicionales** (`countWorkedDominicalDays`, `countWorkedHolidayDays`) por
cada reporte de empleado. Se plegaron dentro de `aggregateTotals` con
`COUNT(DISTINCT CASE WHEN … THEN time_entries.date END)` (igual a como ya lo hace
`GenerateCompanyReport` inline). Los dos métodos privados se eliminaron y `execute()` lee
`dominical_worked_days` / `holiday_worked_days` del mismo objeto de totales.

Resultado verificado: de **3 → 1** query de agregación sobre `time_entries` por reporte.
Cross-DB OK (MySQL y SQLite). 510 tests verdes; el conteo es idéntico (mismos filtros base;
el `where hours > 0` pasó al `CASE`).

---

## 3. `dominical_payable_count` sin tope (severidad: baja — hardening)

**Dónde:** `app/Http/Requests/ReportFilterRequest.php`.

Regla actual: `['nullable', 'integer', 'min:0']`. Sin `max`. Un admin podría enviar un
número absurdo (p. ej. 999999) e inflar el total. Sugerencia: agregar un `max` razonable
(p. ej. 366) para acotar el caso de "pagar dominicales pendientes de otros periodos"
sin permitir valores irreales.

---

## 4. Día por-día con `normal_day_value = 0` → recargo $0 silencioso (severidad: baja — UX)

Si un empleado queda en modo `day` con `normal_day_value = 0` (el campo en el formulario
de empleado es `nullable`), el recargo sale en **$0 sin ningún aviso**. En ajustes de
empresa el campo es `required`, pero a nivel empleado no.

**Opciones:** validar que `normal_day_value > 0` cuando el modo es `day`, o mostrar un
aviso en la UI / reporte cuando el valor sea 0.

---

## 5. Detalle de costos en por-día muestra "0%" con monto (severidad: baja — presentación)

En modo por día, las filas `dominical` / `holiday` del desglose reportan `surcharge = 0`
pero con subtotal ≠ 0 (el plus plano va plegado en la fila diurna). En el reporte se ve
"Recargo 0% → $X", que confunde.

**Opciones:** una fila/etiqueta dedicada "Recargo por día" separada de la base, o mostrar
explícitamente el modo y el monto por día.

---

## 6. Nits / menores

- **Festivos sin info en el reporte:** a diferencia del dominical (que muestra "N
  trabajados"), el festivo por-día no muestra cuántos son ni el modo. Para transparencia,
  mostrar "Festivos: N (por día)".
- **`in_array` sin `strict`:** en `CalculateWorkHours` la comparación de fechas festivas
  usa `in_array($fecha, $holidayDates)` sin el flag `strict: true`. Funciona (ambos son
  strings), pero estricto es marginalmente más seguro.
- **Índice de `dominical_payment_decisions`:** las lecturas del reporte de empresa filtran
  por `(company_id, start_date, end_date)`; el índice existente es `(company_id,
  employee_id)` + el único `(company_id, employee_id, start_date, end_date)`. Cubre por
  prefijo `company_id`; revisar si a volumen conviene un índice por `(company_id,
  start_date, end_date)`.

---

## Verificado como correcto (no son problemas)

- El conteo N incluye días con solo OT dominical/festivo → correcto (sí trabajó ese día).
- El OT festivo respeta el toggle de overtime → intencional (OT es eje aparte del "festivo
  siempre paga").
- Multi-tenant y soft-deletes bien filtrados (`company_id`, `deleted_at`, `clock_out`).
- Sin código de debug olvidado; decimales casteados (`decimal:2`).

---

## Relacionado

- Recálculo histórico (festivos viejos clasificados como dominical): queda para la
  funcionalidad futura del botón "Recalcular" (ver memoria del proyecto / Non-goals del
  change `configurable-dominical-surcharge`).
