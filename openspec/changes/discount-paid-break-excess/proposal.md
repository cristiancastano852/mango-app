## Why

Hoy una pausa marcada como pagada (`is_paid = true`) nunca se resta del tiempo trabajado, sin importar cuánto dure: el campo `max_duration_minutes` del tipo de pausa es puramente decorativo en el cálculo de nómina. Si un "Descanso" pagado tiene tope de 15 min y el empleado toma 25, los 10 minutos de exceso se pagan como tiempo trabajado. Las empresas necesitan que ese exceso se descuente para que el tope de la pausa pagada tenga efecto real sobre las horas netas.

## What Changes

- Al recomputar las horas de un turno, una pausa **pagada con tope** (`is_paid = true` y `max_duration_minutes` no nulo) aporta a `break_hours` solo su **exceso**: `max(0, duration_minutes − max_duration_minutes)`. Los minutos dentro del tope siguen pagados.
- Pausas **pagadas sin tope** (`max_duration_minutes = null`) siguen sin descontar nunca (comportamiento actual intacto).
- Pausas **no pagadas** siguen descontando su duración completa (comportamiento actual intacto).
- El detalle del turno en admin (`TimeEntries/Edit.vue`) muestra, por pausa pagada que excede su tope, cuántos minutos se descontaron por exceso.
- **Solo aplica a turnos nuevos / recalculados a partir de este cambio.** Los datos ya en producción se quedan como están (sin recálculo retroactivo).
- `max_per_day` no se toca: ya está enforced en `StartBreak` y es una regla independiente.

## Capabilities

### New Capabilities
- `paid-break-excess-discount`: Regla de negocio que define cómo el exceso de una pausa pagada sobre su `max_duration_minutes` se descuenta del tiempo trabajado al computar `break_hours`/`net_hours`, y cómo se visibiliza ese descuento al admin.

### Modified Capabilities
- `admin-time-entry-management`: Los escenarios de "Crear registro manual" y "Editar horas del registro" describen hoy `break_hours` como "solo pausas no pagadas finalizadas". Esa definición cambia para incluir el exceso de pausas pagadas con tope.

## Impact

- **Dominio**: TimeTracking.
- **Código**: `app/Domain/TimeTracking/Actions/RecalculateTimeEntry.php` (único punto donde cambia la fórmula de `break_hours`). El resto del pipeline (`CalculateWorkHours`, los 8 buckets, `net_ratio`) no se toca: todo fluye desde `net_hours`. Frontend: `resources/js/pages/admin/TimeEntries/Edit.vue`.
- **Multi-tenancy**: sin cambios; el cálculo opera por `TimeEntry` (ya scoped por `company_id`).
- **Roles**: el recálculo se dispara en clock-out (employee) y en crear/editar registro (admin); la visibilidad del exceso es admin/super-admin.
- **Migración de BD**: ninguna. `max_duration_minutes` ya existe en `break_types`.
- **Datos existentes**: sin migración de datos; producción permanece como está.
