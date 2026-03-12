## Why

El horario nocturno (`21:00–06:00`) está hardcodeado en `CalculateWorkHours`, impidiendo que cada empresa lo ajuste según su convenio colectivo o regulación interna. Esta configuración debe residir en `SurchargeRule` (ya existente por empresa) para mantener coherencia con el modelo multi-tenant.

## What Changes

- Agregar columnas `night_start_time` y `night_end_time` a `surcharge_rules` (migración, default `21:00`/`06:00`)
- Actualizar `SurchargeRule` model: casts + factory defaults
- Modificar `CalculateWorkHours` para leer `night_start_time`/`night_end_time` desde `SurchargeRule` en lugar de constantes hardcodeadas
- Actualizar `UpdateSurchargeRuleRequest` con validación `date_format:H:i` para los nuevos campos
- Actualizar `SurchargeRuleController` (o su Action) para persistir los nuevos campos
- Extender la página Vue `Settings/SurchargeRule.vue` con dos campos `<Input type="time">`
- Agregar claves i18n en `lang/en/messages.php` y `lang/es/messages.php`

## Capabilities

### New Capabilities

- `night-schedule-config`: Configuración del rango horario nocturno por empresa — almacenamiento, validación, edición UI y uso en el cálculo de horas

### Modified Capabilities

_(ninguna — no hay specs existentes que cambien de requisitos)_

## Impact

- **BD:** `surcharge_rules` — 2 columnas nuevas (migración requerida)
- **Backend:** `app/Domain/Company/` (SurchargeRule model + Action + FormRequest + Controller) y `app/Domain/TimeTracking/CalculateWorkHours`
- **Frontend:** `resources/js/pages/Settings/SurchargeRule.vue`, archivos de traducción
- **Multi-tenant:** `surcharge_rules` tiene `company_id` único por empresa; admin solo modifica su propia empresa
- **Roles:** `admin` y `super-admin` tienen acceso; admin está restringido a su empresa (cross-company → error)
- **Sin breaking changes** — los valores default preservan el comportamiento actual
