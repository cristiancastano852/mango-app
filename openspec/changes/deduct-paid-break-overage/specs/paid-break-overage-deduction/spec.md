## ADDED Requirements

### Requirement: El exceso de una pausa pagada se descuenta del tiempo trabajado

El sistema SHALL descontar de `net_hours` el tiempo de cada pausa **pagada** finalizada que exceda su `max_duration_minutes`. Por cada pausa pagada finalizada con límite definido, el exceso SHALL ser `max(0, duration_minutes − max_duration_minutes)`. La porción dentro del límite SHALL seguir contando como tiempo trabajado pagado. Las pausas pagadas **sin** límite (`max_duration_minutes = null`) MUST NOT generar exceso. Las pausas **no pagadas** quedan fuera de esta regla (siguen descontando su duración completa mediante `break_hours`).

La fórmula resultante SHALL ser: `net_hours = max(0, gross_hours − break_hours − paid_break_overage_hours)`, donde `break_hours` es la suma de pausas no pagadas finalizadas y `paid_break_overage_hours` es la suma de los excesos de pausas pagadas finalizadas.

#### Scenario: Pausa pagada que excede su límite descuenta el exceso

- **WHEN** un empleado entra 12:00 p.m. y sale 8:00 p.m. (8h brutas, sin pausas no pagadas) y toma una pausa pagada con `max_duration_minutes = 15` de 2:00 a 2:25 p.m. (25 min)
- **THEN** `paid_break_overage_hours = 0.17` (10 min) y `net_hours = 7.83` (7h 50m)

#### Scenario: Pausa pagada dentro del límite no descuenta nada

- **WHEN** un empleado toma una pausa pagada con `max_duration_minutes = 15` de exactamente 15 min (o menos)
- **THEN** `paid_break_overage_hours = 0.00` y `net_hours = gross_hours` (sin otras pausas)

#### Scenario: Pausa pagada sin límite nunca genera exceso

- **WHEN** un empleado toma una pausa pagada cuyo tipo tiene `max_duration_minutes = null` durante 40 min
- **THEN** `paid_break_overage_hours = 0.00` y esa pausa no descuenta tiempo trabajado

#### Scenario: Coexisten pausa no pagada y exceso de pausa pagada

- **WHEN** un turno tiene una pausa no pagada de 60 min y una pausa pagada de 25 min con `max_duration_minutes = 15`
- **THEN** `break_hours = 1.00` (la no pagada completa), `paid_break_overage_hours = 0.17` (10 min) y `net_hours = max(0, gross_hours − 1.00 − 0.17)`

#### Scenario: Varias pausas pagadas excedidas suman su exceso

- **WHEN** un turno tiene dos pausas pagadas con `max_duration_minutes = 15`, una de 25 min y otra de 20 min
- **THEN** `paid_break_overage_hours` corresponde a 10 + 5 = 15 min (`0.25`)

#### Scenario: Pausa en curso no genera exceso

- **WHEN** una pausa pagada no tiene `ended_at` (en curso) al momento del cálculo
- **THEN** esa pausa no aporta a `paid_break_overage_hours`

### Requirement: `time_entries` persiste el exceso de pausas pagadas

La tabla `time_entries` SHALL tener una columna `paid_break_overage_hours` decimal(5,2) default 0.00 que almacena el total de exceso de pausas pagadas descontado del turno. El valor SHALL recalcularse cada vez que se recalculan las horas del registro (cierre de turno, edición de horas, alta manual o cambios en sus pausas).

#### Scenario: La columna se persiste al cerrar el turno

- **WHEN** un empleado hace check-out de un turno con una pausa pagada excedida
- **THEN** el registro guarda `paid_break_overage_hours` con el exceso correspondiente y `net_hours` ya descontado

#### Scenario: La invariante de los 8 tipos se mantiene

- **WHEN** `CalculateWorkHours` procesa un turno con exceso de pausa pagada descontado en `net_hours`
- **THEN** la suma de los 8 tipos de hora sigue siendo igual a `net_hours`

#### Scenario: Recalcular tras cambiar el tipo de pausa actualiza el exceso

- **WHEN** una pausa de 25 min pasa de un tipo no pagado a un tipo pagado con `max_duration_minutes = 15` (o viceversa)
- **THEN** el recálculo del registro ajusta `break_hours`, `paid_break_overage_hours` y `net_hours` acordemente
