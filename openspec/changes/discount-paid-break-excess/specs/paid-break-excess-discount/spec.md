## ADDED Requirements

### Requirement: El exceso de una pausa pagada con tope se descuenta del tiempo trabajado

Al recomputar las horas de un turno cerrado, cada pausa finalizada (`ended_at` no nulo) cuyo tipo es pagado (`is_paid = true`) y tiene tope (`max_duration_minutes` no nulo) SHALL aportar a `break_hours` únicamente su exceso: `max(0, duration_minutes − max_duration_minutes)`. Los minutos dentro del tope SHALL permanecer pagados (no se restan).

Esta regla SHALL aplicarse en todo flujo que recompute las horas de un turno (clock-out del empleado, y creación o edición de un registro por el admin), y SHALL aplicar únicamente a turnos calculados a partir de la entrada en vigor de esta regla; los registros previos no SHALL recalcularse retroactivamente.

#### Scenario: Pausa pagada con tope que se excede

- **WHEN** un turno tiene una pausa pagada finalizada con `max_duration_minutes: 15` y `duration_minutes: 25`
- **THEN** esa pausa aporta `10` minutos a `break_hours`
- **THEN** `net_hours = max(0, gross_hours − break_hours)` refleja el descuento de esos 10 minutos

#### Scenario: Pausa pagada con tope dentro del límite

- **WHEN** un turno tiene una pausa pagada finalizada con `max_duration_minutes: 15` y `duration_minutes: 15` (o menor)
- **THEN** esa pausa aporta `0` minutos a `break_hours`

### Requirement: Pausas pagadas sin tope nunca descuentan

Una pausa pagada (`is_paid = true`) cuyo tipo no tiene tope (`max_duration_minutes = null`) SHALL aportar `0` a `break_hours` sin importar su duración, preservando el comportamiento previo.

#### Scenario: Pausa pagada sin tope de duración larga

- **WHEN** un turno tiene una pausa pagada finalizada con `max_duration_minutes: null` y `duration_minutes: 40`
- **THEN** esa pausa aporta `0` minutos a `break_hours`

### Requirement: Pausas no pagadas descuentan su duración completa

Una pausa finalizada cuyo tipo no es pagado (`is_paid = false`) SHALL aportar su `duration_minutes` completo a `break_hours`, independientemente de `max_duration_minutes`, preservando el comportamiento previo.

#### Scenario: Pausa no pagada con tope

- **WHEN** un turno tiene una pausa no pagada finalizada con `max_duration_minutes: 60` y `duration_minutes: 45`
- **THEN** esa pausa aporta `45` minutos a `break_hours`

#### Scenario: Combinación de pausas en un mismo turno

- **WHEN** un turno tiene una pausa no pagada de `30` min, una pausa pagada con tope `15` que duró `25` min, y una pausa pagada sin tope de `20` min
- **THEN** `break_hours` acumula `30 + 10 + 0 = 40` minutos

### Requirement: El admin ve el exceso descontado en el detalle del turno

El detalle administrativo de un registro de tiempo SHALL mostrar, por cada pausa pagada cuyo tipo tiene tope y cuya `duration_minutes` lo supera, la cantidad de minutos descontados por exceso (`duration_minutes − max_duration_minutes`). Esta información SHALL estar disponible para roles `admin` y `super-admin`. Los empleados no SHALL tener acceso a esta vista administrativa.

#### Scenario: Admin ve los minutos descontados por exceso

- **WHEN** un `admin` abre el detalle de un turno con una pausa pagada de tope `15` min que duró `25` min
- **THEN** la vista indica que se descontaron `10` minutos por exceso en esa pausa

#### Scenario: Pausa sin exceso no muestra descuento

- **WHEN** un `admin` abre el detalle de un turno con una pausa pagada dentro de su tope, o una pausa pagada sin tope
- **THEN** la vista no muestra minutos descontados por exceso para esa pausa
