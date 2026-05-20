## Why

El sistema actual solo detecta horas extra cuando el empleado supera el límite semanal (`max_weekly_hours`), ignorando que un día de 10h ya contiene 2h extra aunque la semana esté lejos del tope. Esto genera subregistro de overtime en turnos largos aislados y no refleja cómo funciona la legislación laboral colombiana.

## What Changes

- **Nueva columna** `max_daily_hours` en `surcharge_rules` (default 8h, configurable por empresa).
- **Nuevo trigger de overtime**: un minuto se clasifica como extra si el acumulado diario neto supera `max_daily_hours` **o** el acumulado semanal neto supera `max_weekly_hours`, lo que ocurra primero.
- **Nuevos breakpoints diarios** en `CalculateWorkHours::buildBreakpoints()`: el momento exacto donde se agota el cupo diario (puede haber uno por cada día calendario dentro de un turno).
- **Nuevo acumulador diario** en el loop de clasificación que se reinicia en cada medianoche.
- **Campo editable** `max_daily_hours` en la página `Configuración → Reglas de recargo`.

## Capabilities

### New Capabilities
- `overtime-daily-limit`: Configuración y cálculo de límite diario de horas ordinarias como trigger de overtime, complementario al límite semanal existente.

### Modified Capabilities
- `night-schedule-config`: El algoritmo `CalculateWorkHours` cambia su lógica de clasificación de overtime; los escenarios de cálculo existentes deben seguir pasando.

## Impact

- **BD**: Migración nueva que agrega `max_daily_hours integer default 8` a `surcharge_rules`.
- **Backend**: `SurchargeRule` (fillable/casts), `CalculateWorkHours` (buildBreakpoints + loop), `UpdateSurchargeRuleRequest` (validación), seeder si aplica.
- **Frontend**: `SurchargeRules.vue` — nuevo campo en el formulario.
- **Tests**: `WorkHourCalculationTest`, `SurchargeRuleControllerTest`, `CalculateReportCostsTest`.
- **Multi-tenant**: `max_daily_hours` vive en `surcharge_rules` que ya tiene `company_id` — sin impacto adicional.
- **Roles**: Solo admin/super-admin pueden editar (mismo acceso que `max_weekly_hours`).
- **Breaking**: No — los turnos existentes calculados se recalcularán si se dispara `CalculateWorkHours` de nuevo; los valores almacenados no cambian automáticamente.

### Non-goals
- Recalcular automáticamente turnos históricos al cambiar el límite diario.
- Gestionar el caso de turnos que cruzan medianoche con lógica de día laboral distinta al reinicio a las 00:00 (marcado para revisión futura).
