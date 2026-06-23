## Context

El flujo actual separa horas y dinero:

1. `CalculateWorkHours` clasifica los minutos de cada turno en **8 buckets mutuamente excluyentes** y los guarda en `time_entries`. Hoy `$isSundayOrHoliday` fusiona domingo y festivo, y el domingo está hardcodeado (`Carbon::SUNDAY`).
2. `GenerateEmployeeReport` / `GenerateCompanyReport` agregan esas horas por rango (SQL puro) y llaman a `CalculateReportCosts`.
3. `CalculateReportCosts` calcula `costo = horas × tarifa × (1 + recargo%)` por tipo y devuelve `details[]`.
4. `ReportController` renderiza Vue o exporta a Excel/PDF.

Ya existe el feature `configurable-overtime-payment`: tabla `overtime_payment_decisions`, `ResolveOvertimePaymentDecision` (precedencia request → decisión guardada → default) y persistencia al exportar. Es el **molde exacto** del control "K de N dominicales".

El patrón salario/tarifa también existe: `surcharge_rules` guarda defaults (`default_hourly_rate`, `default_monthly_salary`) que **siembran** en `CreateEmployee`, pero el cálculo siempre lee el valor propio del empleado (`employees.hourly_rate`, `employees.salary_type`).

## Goals / Non-Goals

**Goals:**
- Día dominical configurable por empresa (default domingo), desacoplando el domingo del recargo.
- Festivo y dominical clasificados por separado, de modo que el festivo siempre se pague y el dominical sea configurable.
- Modo de pago dominical por hora (actual) o por día (recargo = valor del día normal × %), con defaults de empresa que siembran un valor propio por empleado.
- Control por reporte de cuántos dominicales pagar (K de N), solo en modo por-día, persistido al exportar.
- Dominical no pagado = día ordinario que conserva el recargo nocturno.

**Non-Goals:**
- Snapshot inmutable del reporte.
- Día dominical o conteo K por empleado (solo empresa).
- Reducir o desactivar festivos.
- "K de N" en modo por-hora.

## Decisions

### 1. Separar festivo de dominical en la clasificación (split de buckets 8 → 12)

`CalculateWorkHours` calcula dos flags independientes por segmento:

```php
$isDominical = $segStart->dayOfWeek === $dominicalWeekday;   // configurable
$isHoliday   = in_array($segStart->toDateString(), $holidayDates);
```

Los 4 buckets premium actuales se dividen en dos familias:

| Antes (fusionado) | Dominical | Festivo |
|---|---|---|
| `sunday_holiday_hours` | `dominical_hours` | `holiday_hours` |
| `night_sunday_hours` | `night_dominical_hours` | `night_holiday_hours` |
| `overtime_day_sunday_hours` | `overtime_day_dominical_hours` | `overtime_day_holiday_hours` |
| `overtime_night_sunday_hours` | `overtime_night_dominical_hours` | `overtime_night_holiday_hours` |

Más los 4 buckets de semana (`regular`, `night`, `overtime_day`, `overtime_night`) que no cambian → **12 buckets**.

**Regla de precedencia festivo > dominical:** si un día es festivo y dominical a la vez, las horas van a la familia `*_holiday` (siempre se paga). El recargo es el mismo % en ambas familias (en Colombia domingo y festivo no se acumulan); la diferencia es solo de pagabilidad.

```
            ┌─ festivo? ──sí──▶ familia *_holiday  (SIEMPRE paga)
segmento ───┤
            └─ no ─┬─ dominical? ─sí─▶ familia *_dominical (configurable)
                   └─ no ───────────▶ familia semana (regular/night/ot)
```

**Alternativa descartada (re-derivar en el reporte):** mantener buckets fusionados y deducir festivo vs dominical por fecha al generar el reporte. Se descarta porque no puede desagregar un turno que cruza de un festivo a un domingo dentro de la misma columna. El usuario eligió explícitamente el split correcto.

### 2. Migración de columnas (sin recálculo histórico)

- Migración que **renombra** `sunday_holiday_hours` → `dominical_hours` (y las otras 3 premium) y **agrega** las 4 columnas `*_holiday` (arrancan en 0).
- **El recálculo de datos históricos queda fuera de alcance de este change.** Solo los turnos nuevos (y los que se editen) se clasifican en los 12 buckets vía `CalculateWorkHours`.
- Comportamiento del histórico tras la migración: como el rename **preserva** los datos, las columnas `*_dominical` conservan el valor fusionado domingo+festivo de los turnos viejos, y las `*_holiday` quedan en 0. Es decir, los turnos antiguos siguen reportando como hoy (los festivos pasados aparecen como dominical), sin pérdida de dinero ni de horas; solo no están separados. Los reportes funcionan sin necesidad de recálculo.
- La reclasificación del histórico se hará con una **funcionalidad futura aparte**: un botón "Recalcular" para el admin (en la zona de registros) que re-ejecute `CalculateWorkHours` sobre los turnos elegidos cuando cambie la configuración. Ese feature es un change independiente (ver Non-goals). Cuando exista, deberá: scoping multi-tenant (`withoutGlobalScopes`), proceso por chunks/cola, idempotencia, y reasignar fechas en loops sin mutar (gotcha Carbon inmutable).

### 3. Día dominical configurable por empresa

Nueva columna `dominical_weekday` en `surcharge_rules` (tinyint 0–6, default `0` = domingo, mapeo `Carbon::SUNDAY=0`). `CalculateWorkHours` lee `$rules->dominical_weekday ?? 0`. Cuando es martes (2), el domingo cae en la familia semana (sin recargo) y el martes en `*_dominical`.

### 4. Switch "pagar dominicales" por empresa

Nueva columna `pay_dominical_by_default` (boolean, default `true`) en `surcharge_rules`, molde de `pay_overtime_by_default`. Cuando es `false`, `CalculateReportCosts` trata las horas dominicales como ordinarias (ver decisión 6).

### 5. Modo de pago y valor por día (default empresa → valor por empleado)

| Tabla | Columnas | Rol |
|---|---|---|
| `surcharge_rules` | `default_dominical_payment_mode` (`hour`\|`day`), `default_normal_day_value` (decimal COP, valor del día normal) | defaults de empresa, siembran |
| `employees` | `dominical_payment_mode` (`hour`\|`day`), `normal_day_value` (decimal COP, valor del día normal) | valor real usado en cálculo |

`CreateEmployee` siembra `dominical_payment_mode`/`normal_day_value` desde los defaults (igual que `hourly_rate ?? default_hourly_rate`). El cálculo siempre lee el valor del empleado.

- **`hour`**: comportamiento actual — `dominical_hours × tarifa × (1 + recargo%)` (hourly) o solo el % (monthly).
- **`day`**: el input es el **valor del día normal** (`normal_day_value`); el recargo por cada día dominical pagado = `normal_day_value × (sunday_holiday% / 100)` (el % configurable, 75% por defecto). La **base por horas siempre se paga**: las `dominical_hours` se costean como `regular` y las `night_dominical_hours` como `night` (conservando recargo nocturno), igual que un día ordinario; y **encima** se suma `min(K, N) × normal_day_value × %` por los dominicales pagados. Las `overtime_*_dominical` **no** se afectan por el modo: siguen pagándose por hora y gobernadas por el toggle de overtime. Así, reducir K solo quita recargos (no requiere desglose de horas por día).

  Fórmula modo `day`: `costo_dominical = [base de dominical_hours como regular] + [base de night_dominical_hours como night] + min(K, N) × normal_day_value × (sunday_holiday% / 100)`.

### 6. Dominical no pagado → día ordinario conservando nocturno

Cuando el dominical no se paga (switch off, modo por-día con K < N, o reclasificación), en `CalculateReportCosts`:

- `dominical_hours` se cobran como `regular` (tarifa base en hourly; 0 extra en monthly).
- `night_dominical_hours` se cobran como `night` (conservan el recargo **nocturno**, pierden solo el dominical).
- Los overtime dominicales siguen su propia regla de overtime (no se mezclan con esta decisión).

Esto difiere del overtime compensado (que va a $0): aquí el trabajo del día sí se paga, solo sin el plus dominical.

### 7. Conteo "K de N" por periodo (solo modo por-día)

El control "K de N" vive **solo en el reporte de empleado** (cada empleado tiene su propio N). El **reporte de empresa no ofrece control global**: resuelve, por cada empleado, su decisión guardada (o el default = todos), y muestra el total resultante. Así el total de empresa siempre cuadra con la suma de los desprendibles individuales (a diferencia del flag global e independiente de overtime).

Nueva tabla `dominical_payment_decisions`:

| columna | tipo | nota |
|---|---|---|
| id | bigint | |
| company_id | FK companies | `BelongsToCompany` |
| employee_id | FK employees, **NOT NULL** | siempre por empleado (no hay decisión a nivel empresa) |
| start_date / end_date | date | periodo resuelto |
| payable_count | unsignedInteger, nullable | cuántos dominicales pagar (K); NULL = todos |
| exported_by | FK users, nullable | |
| exported_at | timestamp | |

- Índice único `(company_id, employee_id, start_date, end_date)`. Como `employee_id` es NOT NULL, no aplica el gotcha de upsert contra NULL de overtime.
- `ResolveDominicalPaymentDecision` aplica precedencia: `request(dominical_payable_count)` → decisión guardada → default (todos los N).
- **Cálculo (solo modo `day`):** el reporte de empleado cuenta `N = días dominicales distintos trabajados`. Se pagan `min(K, N)` recargos de `normal_day_value × %`; los `(N − K)` no pagados simplemente no suman recargo (la base de sus horas ya se paga como ordinario, decisión 5).
- En modo por-hora el control se ignora (siempre se pagan todas las horas dominicales); la UI lo deshabilita.
- Persiste al exportar (upsert, gana el último), igual que overtime. Ver el reporte no persiste. Solo el reporte de empleado persiste (el de empresa no escribe decisiones).

### 8. Conteo de días dominicales en la agregación

El reporte de empleado cuenta `N = días dominicales distintos trabajados` como el número de `time_entries.date` distintos con `(dominical_hours + night_dominical_hours + overtime_day_dominical_hours + overtime_night_dominical_hours) > 0` en el periodo. Se cuenta en PHP sobre las fechas distintas (cross-DB, una query, sin N+1).

**Limitación aceptada (cruce de medianoche):** se cuenta por `entry.date`, no por la fecha calendario real de las horas dominicales. Un turno sábado 22:00 → dominical 06:00 tiene horas dominicales pero `entry.date = sábado`, así que ese dominical podría no contarse (o contarse en el día equivocado). Se acepta por simplicidad; el caso es poco frecuente y el modo por-día con plus plano lo hace de bajo impacto. Documentado para el usuario.

### 9. Inventario completo de consumidores del rename

El rename `*_sunday` → `*_dominical` y las 4 nuevas `*_holiday` tocan **todos** estos consumidores (el barrido debe cubrirlos o el código rompe):

- **Modelo:** `TimeEntry` `$fillable` (4→12) y `casts()` (4→12, con comentarios).
- **Reportes:** `GenerateEmployeeReport` (`aggregateTotals` selectRaw, array `totals`, `mapDay()` `$hours[]`, 3 PHPDoc shapes), `GenerateCompanyReport` (selectRaw, `includeMonthlyEmployeesWithoutEntries`, `sumEmployeeTotals`, mapeos), `CalculateReportCosts` (firma, `details[]` 8→12, PHPDoc).
- **Admin:** `Admin/TimeEntryController` (devuelve las cols al modal de edición; agregar las 4 holiday).
- **Exports:** `EmployeeReportExport`, `CompanyReportExport` (filas resumen + filas diarias), Blade `exports/employee-report` y `exports/company-report`.
- **Frontend:** `Reports/Employee.vue`, `Reports/Company.vue`, y **`DailyWorkTable.vue` / `DailyWorkDayDetail.vue`** — estas últimas suman las 4 cols dominicales para el **badge "festivo"** y el tipo de día; con el split deben separar festivo de dominical (es justo lo que el cliente quiere ver). i18n nuevas keys.
- **Seed/factory:** `SurchargeRuleFactory` (4 campos nuevos), `TimeEntryFactory`, `ReportDemoSeeder`.

**`down()` de la migración de rename:** no es trivialmente reversible (las horas movidas a `*_holiday` no se re-fusionan solas). Decisión: el `down()` SHALL re-sumar `holiday → dominical` (y nocturnas/overtime equivalentes) antes de dropear las columnas, dejando el estado fusionado previo.

## Risks / Trade-offs

- **[Histórico sin separar]** Sin recálculo, los turnos antiguos en festivo siguen contados como dominical (en la columna renombrada) y no se benefician de la separación ni de la regla "festivo siempre paga" retroactivamente. → Aceptado: el dinero/horas del histórico no cambian; la separación aplica hacia adelante. La reparación retroactiva (incluida la de festivos mal clasificados anotada en memoria) se cubrirá con la funcionalidad futura del botón "Recalcular".
- **[Renombrado de columnas]** Hay código (exports, vistas Blade, Vue, SQL en reportes) que referencia `sunday_holiday`. Renombrar obliga a un barrido completo. → Buscar todas las referencias antes de migrar; cubrir con tests.
- **[Coherencia empresa vs empleado]** El conteo K del reporte de empresa (employee_id NULL) puede no cuadrar con la suma de desprendibles individuales si difieren. → Intencional, igual que en overtime.
- **[Ambigüedad por-hora + K]** Resuelta por diseño: K solo aplica en por-día; por-hora paga todo. La UI debe dejar esto claro.
- **[Festivo en día dominical]** Gana festivo (siempre paga). Documentado para el usuario para evitar sorpresa de "mi domingo festivo no se redujo con K".
