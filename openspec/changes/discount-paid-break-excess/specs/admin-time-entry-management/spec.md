## MODIFIED Requirements

### Requirement: Crear registro manual

El sistema SHALL permitir al admin crear manualmente un registro de tiempo para un empleado de su empresa en una fecha dada, indicando hora de entrada y hora de salida. Al calcular `break_hours`, las pausas no pagadas finalizadas SHALL aportar su duración completa y las pausas pagadas finalizadas con tope (`max_duration_minutes` no nulo) SHALL aportar solo su exceso `max(0, duration_minutes − max_duration_minutes)`; las pausas pagadas sin tope no aportan.

#### Scenario: Creación exitosa

- **WHEN** el admin envía empleado, fecha, `clock_in` y `clock_out` válidos (con `clock_out` posterior a `clock_in`) para un día sin registro
- **THEN** el sistema crea el `TimeEntry`, calcula `gross_hours`/`break_hours`/`net_hours`, ejecuta la clasificación de horas y marca `status = 'edited'`

#### Scenario: Día con registro existente

- **WHEN** el admin intenta crear un registro para un empleado en un día que ya tiene un registro activo (no eliminado)
- **THEN** el sistema rechaza la creación con un error de validación indicando que ya existe un registro para ese día

#### Scenario: Recrear tras eliminar

- **WHEN** el admin crea un registro para un empleado/día cuyo único registro previo está soft-deleted
- **THEN** el sistema permite la creación del nuevo registro

#### Scenario: clock_out anterior a clock_in

- **WHEN** el admin envía un `clock_out` que no es posterior al `clock_in`
- **THEN** el sistema rechaza la creación con error de validación

### Requirement: Editar horas del registro

El sistema SHALL permitir editar la hora de entrada y la hora de salida de un registro existente, exigiendo un motivo de edición, y SHALL recalcular las horas tras el cambio.

#### Scenario: Edición exitosa de horas

- **WHEN** el admin actualiza `clock_in`/`clock_out` (con `clock_out` posterior a `clock_in`) y proporciona un motivo
- **THEN** el sistema recomputa `gross_hours`, `break_hours` (pausas no pagadas aportan su duración completa; pausas pagadas con tope aportan solo su exceso; pausas pagadas sin tope no aportan), `net_hours = max(0, gross_hours − break_hours)`, ejecuta la clasificación de horas, marca `status = 'edited'` y registra `edited_by` y `edit_reason`

#### Scenario: Motivo requerido

- **WHEN** el admin intenta guardar la edición sin motivo
- **THEN** el sistema rechaza la operación con error de validación

### Requirement: Gestión de pausas del registro

El sistema SHALL permitir al admin agregar, editar, eliminar y cambiar el tipo de las pausas (`BreakEntry`) asociadas a un registro, y SHALL recomputar las horas del registro tras cualquier cambio en sus pausas.

#### Scenario: Agregar pausa

- **WHEN** el admin agrega una pausa con tipo, `started_at` y `ended_at` válidos dentro del rango del turno
- **THEN** el sistema crea la pausa con su `duration_minutes`, recomputa `break_hours`/`net_hours`, reclasifica las horas y marca `status = 'edited'`

#### Scenario: Editar horas de una pausa

- **WHEN** el admin cambia el `started_at`/`ended_at` de una pausa existente
- **THEN** el sistema actualiza `duration_minutes` y recomputa las horas del registro

#### Scenario: Cambiar el tipo de una pausa

- **WHEN** el admin cambia el `break_type` de una pausa de un tipo pagado a uno no pagado (o viceversa)
- **THEN** el sistema recomputa `break_hours`/`net_hours` reflejando que las pausas no pagadas descuentan su duración completa y las pausas pagadas solo su exceso sobre el tope

#### Scenario: Eliminar pausa

- **WHEN** el admin elimina una pausa del registro
- **THEN** el sistema borra la pausa y recomputa las horas del registro

#### Scenario: Pausa fuera del rango del turno

- **WHEN** el admin agrega o edita una pausa cuyo `started_at`/`ended_at` cae fuera del rango `clock_in`–`clock_out`, o cuyo `ended_at` no es posterior a su `started_at`
- **THEN** el sistema rechaza la operación con error de validación

#### Scenario: Tipo de pausa de otra empresa

- **WHEN** el admin intenta asignar un `break_type` que no pertenece a su empresa
- **THEN** el sistema rechaza la operación con error de validación
