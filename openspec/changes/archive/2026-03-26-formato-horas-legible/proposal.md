## Why

Los valores de horas en el frontend se muestran como decimales (`7.99h`, `0.5h`) usando `.toFixed(1)` o sin formato. Este formato es confuso para usuarios finales colombianos: `7.99h` no comunica intuitivamente que son 7 horas y 59 minutos. El dato en BD es correcto; el problema es puramente de presentación.

## What Changes

- Se crea una función utilitaria `formatDecimalHours(hours)` en `resources/js/lib/utils.ts` que convierte horas decimales al formato `Xh Ym` (ej. `7.99 → 7h 59m`).
- Se reemplaza toda presentación de horas decimales en el frontend por esta función en los siguientes archivos:
  - `resources/js/pages/Dashboard.vue` — KPIs y lista de empleados
  - `resources/js/pages/TimeClock/Index.vue` — resumen del día (gross, break, net hours)
  - `resources/js/pages/Reports/Employee.vue` — totales y promedios
  - `resources/js/pages/Reports/Company.vue` — totales
  - `resources/js/pages/Admin/TimeEntries/Index.vue` — columna net_hours
  - `resources/js/pages/Calendar/Index.vue` — badge de horas por día
- El cronómetro en tiempo real (`HH:MM:SS`) de TimeClock **no cambia** — es para contadores activos, no valores estáticos.

## Capabilities

### New Capabilities
- `hour-display-format`: Función utilitaria `formatDecimalHours` y su aplicación consistente en toda la UI para mostrar horas en formato `Xh Ym`.

### Modified Capabilities
_(ninguna — no hay cambio de requisitos en specs existentes)_

## Impact

- **Solo frontend** — cero cambios de backend, BD, migraciones, o rutas.
- **Dominio:** `TimeTracking` (presentación de `time_entries` horas).
- **Roles afectados:** `admin` (Dashboard, TimeEntries, Calendar, Reports) y `employee` (TimeClock, Reports propios).
- **Multi-tenant:** No aplica — es cambio de presentación puro.
- **Migración de BD:** No requerida.
- **No breaking** — la función maneja `null`, `undefined`, `0`, y strings numéricos sin errores.
