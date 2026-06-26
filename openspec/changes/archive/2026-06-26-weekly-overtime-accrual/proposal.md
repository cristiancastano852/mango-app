## Why

Hoy las horas extra se disparan por **doble trigger** (límite diario O semanal, lo que llegue primero), clasificadas turno a turno. Eso impide compensar entre días: trabajar 10h un día y 5h otro genera extra el primer día aunque la semana no exceda el tope semanal. Varias empresas quieren un modelo alternativo: **pagar extra solo cuando se supera el tope semanal**, sin importar la distribución diaria. Además, como las quincenas cortan semanas por la mitad (p. ej. cierre miércoles 15), se necesita una regla clara de en qué periodo se liquida el extra de una semana partida.

## What Changes

- **Nuevo modo de acumulación de extra** configurable por empresa (`overtime_accrual_mode`: `daily` | `weekly`, default `daily` = comportamiento actual). En modo `weekly`, `CalculateWorkHours` ignora el tope diario y clasifica como overtime solo lo que supera el tope semanal de la semana ISO (lun–dom).
- **Liquidación por "dueño del domingo"**: el recargo extra de una semana se paga en la quincena que contiene el **domingo** de esa semana. Una semana de cierre incompleta difiere su extra al siguiente periodo; solo se difiere el **recargo extra**, el salario ordinario de esos días se paga normal por fecha.
- **Doble ventana en el reporte** (modo `weekly`): las horas base/noche/dominical/festivo se suman por el rango de la quincena `[inicio, fin]`; las horas **extra** se suman por la ventana de semanas completas cuyo domingo cae en el periodo. No requiere ledger ni recálculo nuevo: opera sobre los buckets ya horneados por turno.
- **Banner informativo** en el reporte que indica el rango/semanas de extra que se están liquidando, y avisa si una semana en curso se difiere al próximo periodo.
- **Desglose diario**: marcar las filas cuyo extra se difiere (no se paga en este periodo) para que no parezca un error de cuadre.
- `max_daily_minutes` queda **inerte** (no se borra) cuando el modo es `weekly`.

## Capabilities

### New Capabilities
- `weekly-overtime-accrual`: modo de acumulación de extra por empresa; clasificación solo-semanal en `CalculateWorkHours`; regla de liquidación "dueño del domingo" con doble ventana de fechas en los reportes de empleado y empresa; banner del rango de extra; marcado de extra diferido en el desglose diario.

### Modified Capabilities
- `overtime-daily-limit`: el trigger diario SOLO aplica cuando `overtime_accrual_mode = daily`; en modo `weekly` el tope diario no clasifica overtime.
- `overtime-weekly-limit`: en modo `weekly` el tope semanal es el **único** trigger de overtime (deja de ser trigger independiente del diario).

## Impact

- **Dominio**: TimeTracking (clasificación y reportes) + Company (configuración).
- **Migración BD**: nueva columna `overtime_accrual_mode` en `surcharge_rules` (string/enum, default `daily`); empresas existentes conservan el comportamiento actual.
- **Multi-tenant**: toca `surcharge_rules` y `time_entries` (ambos con `company_id`); sin cambios de aislamiento.
- **Roles**: configuración y reportes solo `admin`/`super-admin`; `employee` sin acceso.
- **Código afectado**: `SurchargeRule` (fillable/casts), `CalculateWorkHours` (trigger condicional), `GenerateEmployeeReport` y `GenerateCompanyReport` (ventana de extra), `SurchargeRuleController` + `UpdateSurchargeRuleRequest` (campo nuevo), formulario de Reglas de recargo (Vue), páginas de reporte (Vue) y exports PDF/Excel (banner + desglose).
- **Convivencia**: se apila sobre `pay_overtime_by_default` / `OvertimePaymentDecision` (primero se decide *si* se pagan extras, luego *qué semanas*).

## Non-goals

- No se implementa la **selección manual** del rango de semanas a pagar (variante manual); queda como posible fase futura sobre el mismo motor.
- No se cambia el cálculo de costos (`CalculateReportCosts`) ni los porcentajes de recargo.
- No se borra ni se deja de configurar `max_daily_minutes`.
- No se altera el prorrateo del salario base ni los presets de periodo de pago.
