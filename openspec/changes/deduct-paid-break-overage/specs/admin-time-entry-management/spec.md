## MODIFIED Requirements

### Requirement: Editar horas del registro

El sistema SHALL permitir editar la hora de entrada y la hora de salida de un registro existente, exigiendo un motivo de edición, y SHALL recalcular las horas tras el cambio.

#### Scenario: Edición exitosa de horas

- **WHEN** el admin actualiza `clock_in`/`clock_out` (con `clock_out` posterior a `clock_in`) y proporciona un motivo
- **THEN** el sistema recomputa `gross_hours`, `break_hours` (solo pausas no pagadas finalizadas), `paid_break_overage_hours` (exceso de pausas pagadas sobre su `max_duration_minutes`), `net_hours = max(0, gross_hours − break_hours − paid_break_overage_hours)`, ejecuta la clasificación de horas, marca `status = 'edited'` y registra `edited_by` y `edit_reason`

#### Scenario: Motivo requerido

- **WHEN** el admin intenta guardar la edición sin motivo
- **THEN** el sistema rechaza la operación con error de validación

### Requirement: Gestión de pausas del registro

El sistema SHALL permitir al admin agregar, editar, eliminar y cambiar el tipo de las pausas (`BreakEntry`) asociadas a un registro, y SHALL recomputar las horas del registro tras cualquier cambio en sus pausas.

#### Scenario: Agregar pausa

- **WHEN** el admin agrega una pausa con tipo, `started_at` y `ended_at` válidos dentro del rango del turno
- **THEN** el sistema crea la pausa con su `duration_minutes`, recomputa `break_hours`/`paid_break_overage_hours`/`net_hours`, reclasifica las horas y marca `status = 'edited'`

#### Scenario: Editar horas de una pausa

- **WHEN** el admin cambia el `started_at`/`ended_at` de una pausa existente
- **THEN** el sistema actualiza `duration_minutes` y recomputa las horas del registro, incluyendo `paid_break_overage_hours` si la pausa es pagada y excede su límite

#### Scenario: Cambiar el tipo de una pausa

- **WHEN** el admin cambia el `break_type` de una pausa de un tipo pagado a uno no pagado (o viceversa)
- **THEN** el sistema recomputa `break_hours`/`paid_break_overage_hours`/`net_hours` reflejando que las pausas no pagadas descuentan su duración completa y las pagadas descuentan solo el exceso sobre su `max_duration_minutes`

#### Scenario: Pausa pagada excedida descuenta solo el exceso

- **WHEN** el admin agrega o edita una pausa de un tipo pagado con `max_duration_minutes` definido cuya duración supera ese límite
- **THEN** el sistema descuenta de `net_hours` únicamente el exceso (`duration_minutes − max_duration_minutes`) vía `paid_break_overage_hours`, dejando la porción dentro del límite como tiempo pagado

#### Scenario: Eliminar pausa

- **WHEN** el admin elimina una pausa del registro
- **THEN** el sistema borra la pausa y recomputa las horas del registro

#### Scenario: Pausa fuera del rango del turno

- **WHEN** el admin agrega o edita una pausa cuyo `started_at`/`ended_at` cae fuera del rango `clock_in`–`clock_out`, o cuyo `ended_at` no es posterior a su `started_at`
- **THEN** el sistema rechaza la operación con error de validación

#### Scenario: Tipo de pausa de otra empresa

- **WHEN** el admin intenta asignar un `break_type` que no pertenece a su empresa
- **THEN** el sistema rechaza la operación con error de validación
