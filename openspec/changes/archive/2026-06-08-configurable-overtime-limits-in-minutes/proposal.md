## Why

Hoy los límites de horas ordinarias diario (`max_daily_hours`) y semanal (`max_weekly_hours`) se configuran solo en **horas enteras** (entero 1–24 / 1–168). Empresas con jornadas como **7 horas 20 minutos** no pueden representarlas: la validación rechaza decimales y el cast los trunca. El motor de cálculo (`CalculateWorkHours`) ya opera internamente en minutos (multiplica los límites por 60), así que la limitación es solo de captura y almacenamiento, no del cálculo.

## What Changes

- **BREAKING** (interno de datos): renombrar/convertir las columnas `surcharge_rules.max_daily_hours` → `max_daily_minutes` y `max_weekly_hours` → `max_weekly_minutes`, almacenando el límite en **minutos** (enteros), con backfill `× 60` de los valores actuales.
- Defaults en minutos: diario `480` (8 h), semanal `2520` (42 h).
- `CalculateWorkHours` usa los nuevos campos directamente, **sin** el `× 60` (líneas que hoy convierten horas→minutos).
- Validación: enteros en minutos — diario `min:1 max:1440`, semanal `min:1 max:10080`.
- UI en `Configuración → Reglas de recargo`: cada límite se captura con **dos inputs separados (Horas + Minutos 0–59)** que se combinan a minutos totales al guardar, y se descomponen al mostrar.
- Actualizar `SurchargeRuleFactory`, el seed/observer de defaults y los casts.

## Capabilities

### New Capabilities
- `overtime-weekly-limit`: Almacenamiento, validación, edición (UI horas+minutos) y uso en `CalculateWorkHours` del límite **semanal** de horas ordinarias en minutos. Formaliza el límite semanal, que antes solo existía implícito dentro de `overtime-daily-limit`.

### Modified Capabilities
- `overtime-daily-limit`: el límite **diario** pasa a almacenarse en minutos (`max_daily_minutes`), con validación en minutos, captura por horas+minutos en la UI y uso directo (sin `× 60`) en `CalculateWorkHours`. La lógica de clasificación de overtime no cambia, solo la unidad y la forma de capturar el límite.

## Impact

- **Dominio:** Company (`SurchargeRule`, `CompanyObserver`/seed de defaults), TimeTracking (`CalculateWorkHours`).
- **Backend:** migración con backfill (`max_daily_minutes`, `max_weekly_minutes`); `SurchargeRule` (`$fillable`, `casts()`); `CalculateWorkHours` (uso directo en minutos); `UpdateSurchargeRuleRequest` (reglas y mensajes); `SurchargeRuleFactory`.
- **Frontend:** `settings/SurchargeRules.vue` — dos inputs (Horas + Minutos) por límite, combinación/descomposición a minutos; tipos TS.
- **Multi-tenancy:** los límites son `company_id`-scoped vía `SurchargeRule`; sin impacto en `super-admin` (que puede editar cualquier empresa, como hoy).
- **Roles:** sin cambios de autorización; `admin`/`super-admin` editan según reglas actuales.
- **Migración de BD:** sí (renombre/conversión de dos columnas + backfill de datos en producción).

## Non-goals

- No cambia la lógica de clasificación de overtime (diario/semanal como triggers independientes, sin doble cobro).
- No agrega precisión de minutos a otros campos (recargos, horario nocturno ya usa H:i).
- No expone los límites por empleado; siguen siendo configuración por empresa.
