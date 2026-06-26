## Context

Todo el dinero se calcula en `CalculateReportCosts` (cost-time); los 12 buckets de hora se guardan factuales en `time_entries` y el costo se arma por reporte. Ya existen dos colapsos configurables:

- `pay_overtime` (todo o nada): si OFF, las 6 categorías de overtime → $0 (compensadas).
- `pay_dominical_by_default`: en modo **hora**, si OFF → `dominical→regular`, `night_dominical→night`, `ot_*_dominical→ot_*` (tarifa de semana). Es decir, hoy un único switch colapsa **toda** la familia dominical.

Detalle del modo **por día** (relevante abajo): hoy `night_dominical` ya se paga a tarifa **nocturna normal** (el recargo dominical va en el valor plano del día), y `ot_*_dominical` usan la tarifa de overtime dominical.

Este change agrega control **granular** para colapsar recargos premium individuales hacia su base, manteniendo todo en cost-time.

## Goals / Non-Goals

**Goals:**
- 4 switches de empresa para colapsar `night_dominical`, `night_holiday`, overtime dominical y overtime festivo hacia sus bases.
- Aplicarlo en cost-time sin queries extra ni recálculo histórico.
- Preservar exactamente el pago actual de cada empresa al migrar.

**Non-Goals:**
- Tocar la clasificación / 12 buckets / recálculo histórico.
- Tocar el dominical/festivo **diurno** (modo hora/día intacto).
- Override por empleado o por reporte (solo empresa).

## Decisions

### 1. Colapso en cost-time (no en clasificación)

Las horas ya vienen agregadas; el colapso es aritmética trivial sobre los totales, sin query ni recálculo:

```
nocheEfectiva   = night
                + (pay_night_dominical ? 0 : night_dominical)
                + (pay_night_holiday   ? 0 : night_holiday)
nightCost       = nocheEfectiva × tarifa × (1 + nocturno%)

otDiaEfectiva   = overtime_day
                + (pay_overtime_dominical ? 0 : overtime_day_dominical)
                + (pay_overtime_holiday   ? 0 : overtime_day_holiday)
otNocheEfectiva = overtime_night
                + (pay_overtime_dominical ? 0 : overtime_night_dominical)
                + (pay_overtime_holiday   ? 0 : overtime_night_holiday)
```

No se calcula el premium para descartarlo: simplemente las horas se suman a la base **antes** de la única multiplicación. La clasificación queda factual; cambiar la config es instantáneo y los reportes viejos siguen correctos.

**Alternativa descartada:** colapsar en `CalculateWorkHours` (guardar horas ya fundidas). Reintroduce el problema de recálculo histórico al cambiar la config y acopla la clasificación a la política de pago.

### 2. Cuatro switches independientes

Nuevas columnas en `surcharge_rules` (boolean, default `true` = pagar el premium):

| Flag | Si OFF, esas horas se pagan como |
|---|---|
| `pay_night_dominical` | `night` (nocturno normal) |
| `pay_night_holiday` | `night` |
| `pay_overtime_dominical` | `overtime_day` / `overtime_night` |
| `pay_overtime_holiday` | `overtime_day` / `overtime_night` |

`pay_overtime_dominical` cubre diurna **y** nocturna dominical juntas (cada una colapsa a su base diurna/nocturna). Igual `pay_overtime_holiday`.

### 3. `pay_dominical_by_default` pasa a controlar solo el dominical diurno

Hoy el branch `! $payDominical` (modo hora) colapsa diurno + noche + extra. Se **parte**:

- `pay_dominical_by_default` → solo decide el **diurno** (`dominical` → `regular` si OFF / por hora-día si ON).
- `night_dominical` → lo decide `pay_night_dominical`.
- `ot_*_dominical` → lo decide `pay_overtime_dominical`.

Quedan independientes: una empresa puede pagar el diurno dominical pero colapsar la noche, o viceversa.

### 4. Migración que preserva el comportamiento (clave del cambio "breaking")

Si los flags nuevos nacieran en `true` a ciegas, una empresa con `pay_dominical_by_default = false` (que hoy NO paga noche/extra dominical) empezaría a pagar esos premiums. Para evitarlo, la migración **siembra**:

```
pay_night_dominical    = pay_dominical_by_default   (por empresa)
pay_overtime_dominical = pay_dominical_by_default   (por empresa)
pay_night_holiday      = true   (los festivos hoy siempre se pagan → sin cambio)
pay_overtime_holiday   = true
```

Así nadie ve subir su nómina por el cambio técnico; tras migrar, los 4 quedan editables e independientes.

### 5. Display: fundir en la base, premium en 0

Cuando un recargo se colapsa, en `details[]` y en los reportes/exports: sus horas y costo se suman al renglón base (`night` / `overtime_day` / `overtime_night`), y el renglón premium queda en `0h / $0`. El reporte refleja exactamente lo que se paga.

### 6. Interacciones

- **Modo por día (dominical):** `night_dominical` ya se paga a tarifa nocturna normal (decisión preexistente). El flag de noche aplica sobre todo al modo **hora**; en por-día el comportamiento de la noche no cambia (el premium dominical de ese día está en el valor plano). El flag de **overtime** sí aplica en ambos modos (el overtime es independiente del modo hora/día).
- **`pay_overtime` (global):** si OFF, todas las extras → $0; los flags `pay_overtime_*` no aplican (no hay nada que tarifar). Solo deciden la **tarifa** (premium vs base) cuando el overtime sí se paga.
- **Festivo diurno:** sigue pagándose siempre (sin switch); los flags festivos solo tocan noche y extra.

## Risks / Trade-offs

- **[Comportamiento al partir el switch dominical]** Mitigado por la siembra de la decisión 4. Riesgo si la siembra falla → documentar y verificar en migración con test.
- **[Display de renglones fundidos]** El desglose cambia de filas (premium desaparece, base infla). Hay que tocar Vue + Excel + Blade y cubrir con tests/snapshots de montos.
- **[Confusión con `pay_overtime`]** Dos conceptos sobre overtime: pagar o no (compensar) vs tarifa premium/base. Etiquetas claras en UI para no confundir al admin.
