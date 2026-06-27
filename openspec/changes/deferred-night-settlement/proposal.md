## Why

Algunas empresas pagan la quincena en la mañana del día de corte (el 15 y el 30). En ese momento el turno de ese día aún no termina, así que el recargo nocturno del día de corte no se conoce y no se puede pagar en esa quincena. Hoy el reporte suma el recargo nocturno por fecha exacta `[inicio, fin]`, incluyendo el día de corte. Se necesita poder **diferir el recargo nocturno del día de corte al periodo siguiente**, de forma simétrica a como `weekly-overtime-accrual` ya difiere el recargo extra de la semana de cierre.

## What Changes

- **Nuevo flag opt-in por empresa `night_settlement_mode`** en `surcharge_rules` (`immediate` default | `deferred`). En `immediate` el comportamiento es el actual. En `deferred` se activa el diferimiento del recargo nocturno del día de corte.
- **Ventana de recargo nocturno `[inicio−1, fin−1]`** en modo `deferred`: el **recargo** (premium) de las horas nocturnas se liquida sobre el periodo corrido un día hacia atrás. Esto excluye el día de corte (su recargo se va al periodo siguiente) e incluye el día de corte del periodo anterior (su recargo diferido entra aquí). Con periodos contiguos no hay solapamiento ni doble conteo.
- **Solo se difiere el componente nocturno (`night_surcharge`%), no las horas ni los otros recargos:** lo que se difiere al periodo siguiente es exclusivamente el recargo por trabajar de noche (`night_surcharge`, ~35%), porque al pagar en la mañana la empresa aún no sabe cuánto tiempo nocturno se trabajará ese día. Se siguen pagando por fecha en `[inicio, fin]`: la **base** de la hora, **y** el recargo dominical/festivo (incluido el dominical pagado **por día completo**, que sí se conoce al pagar). Espejo de la regla "solo se difiere el recargo" de `weekly-overtime-accrual`.
- **Aplica a los tres buckets nocturnos:** `night`, `night_dominical` y `night_holiday`. El componente diferible es siempre `night_surcharge`%; el remanente premium (dominical/festivo, por hora o por día) se queda por fecha. Si `pay_night_dominical`/`pay_night_holiday` está apagado (el bucket ya colapsó a nocturno normal), lo que se difiere es el recargo nocturno normal.
- **Indicadores en el reporte** (pantalla, PDF y Excel): banner con el rango de noches cuyo recargo se liquida en el periodo, aviso de que el recargo del día de corte se difiere al próximo periodo, y marca en la fila del día de corte en el desglose diario. Mismo patrón visual que el banner de overtime semanal.
- **Independiente de `overtime_accrual_mode`:** una empresa puede tener ambos diferimientos activos (overtime por semana + nocturno por día de corte), uno solo, o ninguno. Cada uno calcula su ventana por separado, sin lógica de coordinación entre ellos.

## Capabilities

### New Capabilities
- `deferred-night-settlement`: Capacidad de la empresa de diferir el recargo nocturno del día de corte de un periodo al periodo siguiente (modo opt-in), liquidando el recargo nocturno sobre una ventana corrida un día, mientras la base de las horas se paga por fecha. Incluye el flag de configuración, el resolver de ventana, el cálculo de costos separando base de recargo nocturno, y los indicadores en los reportes.

### Modified Capabilities
- (ninguna) — el comportamiento `immediate` preserva lo actual; lo nuevo vive en la capability nueva.

## Impact

- **Dominio afectado:** Company (config `night_settlement_mode` en `surcharge_rules`) + TimeTracking (resolver de ventana, cálculo de costos, generación de reportes, indicadores).
- **Backend:**
  - Migración: agregar `night_settlement_mode` (string/enum, default `immediate`) a `surcharge_rules`; las empresas existentes quedan en `immediate`.
  - `SurchargeRule` — fillable + cast.
  - `ResolveNightSettlementWindow` (nueva action, hermana de `ResolveOvertimeSettlementWindow`) — retorna `{start, end, deferred}` con la ventana `[inicio−1, fin−1]` en modo `deferred`, o `[inicio, fin]` en `immediate`.
  - `CalculateReportCosts` — separar el costo nocturno en **base** (por fecha) y **recargo** (sobre la ventana), para los tres buckets nocturnos.
  - `GenerateEmployeeReport` / `GenerateCompanyReport` — calcular el recargo nocturno sobre la ventana corrida y exponer el rango liquidado (espejo de `overrideOvertimeTotals` y del bloque `overtime_settlement`).
  - `UpdateSurchargeRuleRequest` + `Settings/SurchargeRuleController` — aceptar y validar el flag.
- **Frontend:** `SurchargeRules.vue` (selector del modo), reportes (`Reports/Employee.vue`, `Reports/Company.vue`) y exports (Excel + Blade PDF) — banner del rango nocturno liquidado y marca de día de corte diferido. i18n.
- **Multi-tenant:** todo vive en `surcharge_rules` (ya `company_id`). Sin tabla nueva.
- **Roles:** configuración y reportes son `admin` + `super-admin`; `employee` no accede (403).
- **Migración de BD:** Sí — una columna con default `immediate` (sin backfill destructivo).

## Non-goals

- No se toca la clasificación de horas ni los 12 buckets de `time_entries`; opera sobre los buckets ya almacenados, sin recálculo histórico.
- No se difiere la **base** de las horas (siempre por fecha); solo el recargo nocturno.
- No se difiere ningún otro recargo (dominical/festivo diurno, overtime): eso es competencia de sus propias reglas.
- No se coordina con `overtime_accrual_mode`: son diferimientos ortogonales e independientes.
