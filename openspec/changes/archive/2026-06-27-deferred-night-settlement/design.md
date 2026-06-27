## Context

La empresa paga la quincena en la mañana del día de corte (15 y 30). En ese momento el turno de ese día no ha terminado, así que el **tiempo nocturno** trabajado ese día se desconoce y su recargo no se puede pagar en esa quincena. Hoy `GenerateEmployeeReport`/`GenerateCompanyReport` suman el recargo nocturno por fecha exacta `[inicio, fin]` (vía `aggregateTotals` → `CalculateReportCosts`).

Ya existe un patrón análogo para overtime: `weekly-overtime-accrual` introdujo `ResolveOvertimeSettlementWindow` (resuelve una ventana de liquidación) y `overrideOvertimeTotals` (re-suma los buckets de overtime sobre esa ventana en lugar del rango del periodo), más un bloque `overtime_settlement` y marcado de filas `overtime_deferred`. Este cambio replica esa arquitectura para el recargo nocturno, con dos diferencias clave:

1. La ventana nocturna es `[inicio−1, fin−1]` (corrida un día), no la "regla del domingo".
2. A diferencia del overtime (100% premium), la hora nocturna tiene **base + remanente dominical/festivo** que NO se difieren; solo el componente `night_surcharge`% se difiere. Por eso no basta con re-sumar buckets enteros: hay que **separar el componente nocturno del resto**.

## Goals / Non-Goals

**Goals:**
- Diferir exclusivamente el componente `night_surcharge`% de las horas nocturnas del día de corte al periodo siguiente, en modo opt-in.
- Reusar la arquitectura de ventana/banner/marcado de `weekly-overtime-accrual`.
- Coexistir con `overtime_accrual_mode` sin lógica de coordinación.

**Non-Goals:**
- No diferir la base ni el recargo dominical/festivo (por hora o por día).
- No tocar la clasificación de horas ni los buckets de `time_entries`.
- No coordinar las dos ventanas (overtime semanal y nocturna) entre sí.

## Decisions

### Decisión 1: El componente diferible es siempre `night_surcharge`%
**Por qué:** lo que la empresa no conoce al pagar en la mañana es cuánto tiempo nocturno se trabajará; el recargo dominical/festivo (incluido el pagado por día completo) sí se conoce. Por eso solo el recargo por trabajar de noche (`night_surcharge`) se difiere, para los tres buckets nocturnos. El remanente premium (`bucketPct − night_surcharge`) y la base se quedan por fecha.
**Alternativas:** diferir el premium completo del bucket nocturno (más simple pero difiere también el dominical/festivo del día de corte) — descartada porque contradice el requerimiento del negocio.

### Decisión 2: Separar el costo nocturno en "por fecha" + "componente diferible por ventana"
**Por qué:** la base y el remanente dominical/festivo se liquidan sobre `[inicio, fin]`; el componente `night_surcharge`% se liquida sobre `[inicio−1, fin−1]`. Implementación:
- El costo nocturno "por fecha" paga cada bucket nocturno a `(bucketPct − night_surcharge)`% sobre las horas del rango del periodo (base + remanente premium).
- Un término aparte suma el componente `night_surcharge`% sobre las **horas nocturnas de la ventana corrida**.
- En modo `immediate`, ambas ventanas coinciden y el resultado es idéntico al actual.

Esto exige que los generadores de reporte calculen las horas nocturnas sobre **dos ventanas** (el rango del periodo y la ventana corrida), análogo a cómo `overrideOvertimeTotals` ya re-suma buckets sobre una ventana distinta.

**Dos capas de separación (y por qué por-día es el caso limpio):**

El problema tiene dos capas independientes:
- **Capa 1 — base vs componente nocturno (`night_surcharge`%):** existe SIEMPRE, en ambos modos (por hora y por día) y aun para una hora nocturna ordinaria sin dominical. Es el mecanismo irreducible del feature: la base se queda por fecha, el `night_surcharge`% se difiere.
- **Capa 2 — despegar el `night_surcharge`% del recargo dominical/festivo combinado:** solo aparece en **modo por hora**, donde `night_dominical`/`night_holiday` se pagan con un porcentaje combinado (`night_sunday`) que mezcla dominical + nocturno en un solo número. Ahí el remanente que se queda por fecha es `max(0, night_sunday − night_surcharge)`.

En **modo por día** la Capa 2 desaparece: `CalculateReportCosts` ya paga `night_dominical`/`night_holiday` al `night_surcharge`% plano (líneas ~197 y ~153), y el recargo dominical/festivo se liquida como un **plus plano por día** (`paidDays × day_value × dominicalPct/100`) que se conoce al pagar y se queda por fecha. Como `bucketPct = night_surcharge`, el remanente `max(0, night_surcharge − night_surcharge) = 0` **cae solo** con la misma fórmula: no se requiere código especial por modo. Por eso por-día es el caso limpio, y por-hora el que ejercita la decomposición completa.

### Decisión 3: `ResolveNightSettlementWindow`, hermana de `ResolveOvertimeSettlementWindow`
**Por qué:** misma forma (`{start, end, deferred}`), misma integración en los generadores. En `deferred` retorna `[inicio−1, fin−1]` y `deferred = true`; en `immediate` retorna `[inicio, fin]` y `deferred = false`.

### Decisión 4: Interacción con los flags de colapso nocturno
**Por qué:** si `pay_night_dominical`/`pay_night_holiday` está apagado, el bucket ya se paga como nocturno normal (`night_surcharge`%); el componente diferible sigue siendo `night_surcharge`% (todo su premium). El diferimiento se aplica DESPUÉS del colapso, de modo que las horas colapsadas difieren su recargo nocturno normal. Consistente con el orden de colapso actual en `CalculateReportCosts`.

### Decisión 5: Ortogonalidad con `overtime_accrual_mode`
**Por qué:** cada diferimiento resuelve su propia ventana y expone su propio bloque (`overtime_settlement` vs `night_settlement`) y banner. No se combinan. Una empresa con ambos activos ve dos banners independientes. Menos código, menos casos límite.

## Risks / Trade-offs

- **[Desfase horas mostradas vs costo en días de corte]** → En la fila del día de corte, las horas nocturnas se muestran por fecha pero su recargo nocturno no se paga ahí. Mitigación: marcar la fila como "recargo nocturno diferido" (igual que `overtime_deferred`) y banner explicativo.
- **[Descomposición depende de configuración coherente]** → Asume `bucketPct ≥ night_surcharge` para que el remanente sea no-negativo. Mitigación: acotar el remanente a `max(0, bucketPct − night_surcharge)`; documentar el supuesto.
- **[Doble conteo si los periodos no son contiguos]** → Con rango libre no contiguo podría perderse/duplicarse un día de recargo. Mitigación: el modelo es exacto para periodos contiguos (quincena/mes); el rango libre hereda la ventana corrida sin garantía de cuadre perfecto (documentado, igual que el overtime semanal).
- **[Complejidad de cálculo mayor que overtime]** → Requiere dos sumas de horas nocturnas por bucket. Mitigación: encapsular en una sola query extra sobre la ventana (espejo de `overrideOvertimeTotals`), sin N+1.

## Migration Plan

1. Migración: `ALTER TABLE surcharge_rules ADD COLUMN night_settlement_mode` (string, default `immediate`). Empresas existentes quedan en `immediate` (sin cambio de comportamiento).
2. Backend: `ResolveNightSettlementWindow`, separación base/recargo en `CalculateReportCosts`, doble ventana en los generadores, validación en `UpdateSurchargeRuleRequest`, exposición de `night_settlement` en el payload del reporte.
3. Frontend: selector del modo en `SurchargeRules.vue`, banner + marcado en reportes y exports, i18n.
4. Rollback: la columna con default `immediate` y la ventana que colapsa a `[inicio, fin]` dejan el sistema igual que hoy; revertir frontend/backend no rompe datos.

## Open Questions

- (ninguna) — la descomposición (solo `night_surcharge`% se difiere; base y dominical/festivo se quedan) quedó cerrada en exploración.
