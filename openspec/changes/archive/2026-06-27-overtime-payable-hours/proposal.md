## Why

Hoy el pago de horas extra es todo-o-nada: el switch `pay_overtime` paga las 6 categorías de extra completas o las compensa a $0. Algunas empresas necesitan un punto intermedio: de las horas extra trabajadas, decidir **cuántas** pagar (igual que ya se hace con los dominicales vía `payable_count`). Cuando una empresa paga todo el overtime como una sola tarifa (sin distinguir dominical/festivo/nocturno), tiene sentido exponer un input de horas extra pagables y recalcular el total sobre ese número.

## What Changes

- **Nuevo input por reporte `overtime_payable_hours`** (decimal, nullable) en el reporte de empleado y de empresa, análogo a `dominical_payable_count`. Decide cuántas horas extra se pagan en el periodo.
- **Precondición de activación:** el input solo aplica cuando los 3 flags premium de overtime de la empresa están en OFF — `pay_overtime_dominical = false`, `pay_overtime_holiday = false`, `pay_overtime_night = false`. En ese escenario `CalculateReportCosts` ya colapsa las 6 categorías de overtime en una sola bolsa diurna (`overtime_day`) a una única tarifa, así que "pagar N de M horas" no tiene ambigüedad de asignación entre buckets. Cuando cualquiera de esos flags está en ON, el input no aplica (overtime sigue repartido en varios buckets con tarifas distintas).
- **Sin tope superior:** el admin puede ingresar más horas que las trabajadas (para saldar extra pendiente de otra quincena), igual que `dominical_payable_count` puede superar los días trabajados. `0` = no pagar ninguna; `null` = pagar todas (comportamiento actual).
- **Relación con `pay_overtime`:** el switch on/off existente se conserva. `overtime_payable_hours` opera dentro del caso "se pagan las horas extra"; cuando `pay_overtime` está en off, todo el overtime sigue compensado a $0 sin importar el input.
- **Persistencia al exportar:** la decisión efectiva de horas pagables se guarda junto a la decisión de overtime del periodo (tabla `overtime_payment_decisions`), con la misma precedencia request → guardado → default que ya usa `pay_overtime` y el conteo dominical.

## Capabilities

### New Capabilities
- `overtime-payable-hours`: Capacidad del administrador de definir, por empleado/empresa y periodo, cuántas horas extra se pagan (cap o sobre-pago sobre la bolsa única de overtime), activa solo cuando la empresa paga todo el overtime como una sola tarifa diurna. Incluye cálculo de costos, resolución/persistencia de la decisión y exposición en reportes.

### Modified Capabilities
- (ninguna) — el comportamiento de `overtime-payment-toggle` (switch on/off) se conserva; lo nuevo vive en la capability nueva para no escribir un delta sobre specs canónicos que aún no se sincronizan.

## Impact

- **Dominio afectado:** TimeTracking (cálculo de costos, generación de reportes, resolución/persistencia de la decisión) + Company (lectura de los 3 flags premium de `surcharge_rules` como precondición).
- **Backend:**
  - Migración: agregar `overtime_payable_hours` (decimal nullable) a `overtime_payment_decisions`.
  - `CalculateReportCosts` — aceptar `overtimePayableHours` y aplicar el cap/override sobre la bolsa única `effectiveOvertimeDayHours` cuando los 3 flags premium están en off.
  - `ResolveOvertimePayableHours` (nueva action, espejo de `ResolveDominicalPaymentDecision`) — precedencia request → guardado → default null.
  - `GenerateEmployeeReport` / `GenerateCompanyReport` — propagar el valor a `CalculateReportCosts`.
  - `ReportFilterRequest` — validar `overtime_payable_hours` (nullable, numeric, min:0).
  - `ReportController` — leer override y persistir al exportar (upsert en `overtime_payment_decisions`).
- **Frontend:** `Reports/Employee.vue` y `Reports/Company.vue` — input de horas extra pagables visible solo cuando los 3 flags premium están en off; reactivo sobre el total. i18n. Exports (Excel + Blade PDF) reflejan el número efectivo.
- **Multi-tenant:** todo vive en tablas con `company_id` (`overtime_payment_decisions`, `surcharge_rules`). Sin tabla nueva.
- **Roles:** reportes y exports son `admin` + `super-admin` (igual que hoy); `employee` no accede.
- **Migración de BD:** Sí — una columna nullable en `overtime_payment_decisions` (sin backfill; null = pagar todas).

## Non-goals

- No se toca la clasificación de horas ni los 12 buckets de `time_entries`; no hay recálculo histórico.
- No se implementa asignación/prioridad entre buckets de overtime: el input solo aplica cuando ya hay una sola bolsa (3 flags premium en off). Con flags premium en on, el input no se muestra.
- No se cambia el switch `pay_overtime` (sigue siendo el on/off global de compensar el overtime a $0).
- No se difiere overtime entre periodos (eso es competencia de `weekly-overtime-accrual`).
