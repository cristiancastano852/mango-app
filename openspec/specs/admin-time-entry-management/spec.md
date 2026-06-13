## ADDED Requirements

### Requirement: Authorization and tenancy

La gestión administrativa de registros de tiempo SHALL estar disponible únicamente para los roles `admin` y `super-admin`, y SHALL operar exclusivamente sobre registros de la empresa del usuario autenticado.

#### Scenario: Admin accede a la sección

- **WHEN** un usuario con rol `admin` navega a la sección de registros
- **THEN** el sistema muestra el listado de registros de su empresa

#### Scenario: Empleado sin acceso

- **WHEN** un usuario con rol `employee` intenta acceder a cualquier ruta de gestión de registros
- **THEN** el sistema responde 403

#### Scenario: Cross-company bloqueado

- **WHEN** un `admin` intenta ver, editar o eliminar un `TimeEntry` que pertenece a otra empresa
- **THEN** el sistema rechaza la operación con error de sesión (no expone el recurso)

### Requirement: Listado con filtros

El sistema SHALL listar los registros de tiempo de la empresa, paginados y ordenados del más reciente al más antiguo, con capacidad de filtrar por empleado y por rango de fechas.

#### Scenario: Filtrar por empleado

- **WHEN** el admin selecciona un empleado en el filtro
- **THEN** el listado muestra solo los registros de ese empleado

#### Scenario: Filtrar por rango de fechas

- **WHEN** el admin indica una fecha inicial y una fecha final
- **THEN** el listado muestra solo los registros cuya `date` cae dentro del rango (inclusive)

#### Scenario: Sin filtros

- **WHEN** el admin entra al listado sin filtros
- **THEN** el sistema muestra todos los registros de la empresa, paginados, del más reciente al más antiguo

#### Scenario: Registros eliminados ocultos

- **WHEN** existe un registro con soft-delete
- **THEN** el registro no aparece en el listado

### Requirement: Crear registro manual

El sistema SHALL permitir al admin crear manualmente un registro de tiempo para un empleado de su empresa en una fecha dada, indicando hora de entrada y hora de salida.

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
- **THEN** el sistema recomputa `gross_hours`, `break_hours` (solo pausas no pagadas finalizadas), `net_hours = max(0, gross_hours − break_hours)`, ejecuta la clasificación de horas, marca `status = 'edited'` y registra `edited_by` y `edit_reason`

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
- **THEN** el sistema recomputa `break_hours`/`net_hours` reflejando que solo las pausas no pagadas descuentan tiempo

#### Scenario: Eliminar pausa

- **WHEN** el admin elimina una pausa del registro
- **THEN** el sistema borra la pausa y recomputa las horas del registro

#### Scenario: Pausa fuera del rango del turno

- **WHEN** el admin agrega o edita una pausa cuyo `started_at`/`ended_at` cae fuera del rango `clock_in`–`clock_out`, o cuyo `ended_at` no es posterior a su `started_at`
- **THEN** el sistema rechaza la operación con error de validación

#### Scenario: Tipo de pausa de otra empresa

- **WHEN** el admin intenta asignar un `break_type` que no pertenece a su empresa
- **THEN** el sistema rechaza la operación con error de validación

### Requirement: Eliminar registro (soft-delete)

El sistema SHALL eliminar registros mediante soft-delete, preservando los datos para auditoría y recuperación futura, y SHALL excluir los registros eliminados de listados, KPIs y reportes.

#### Scenario: Eliminación exitosa

- **WHEN** el admin elimina un registro de su empresa
- **THEN** el sistema marca el registro como eliminado (`deleted_at`) sin borrarlo físicamente y lo retira del listado

#### Scenario: Recálculo de un día queda disponible

- **WHEN** un registro queda soft-deleted
- **THEN** el empleado/día queda libre para crear un nuevo registro sin violar la unicidad

### Requirement: Un registro activo por empleado y día

El sistema SHALL garantizar que un empleado tenga como máximo un registro de tiempo activo (no eliminado) por día.

#### Scenario: Unicidad respetada con soft-delete

- **WHEN** existe un registro activo para un empleado en una fecha
- **THEN** el sistema impide crear otro registro activo para el mismo empleado y fecha, pero permite la coexistencia con registros soft-deleted de esa misma fecha

### Requirement: Detalle visual de cada registro en el listado

El listado de registros SHALL incluir, por cada registro y sin queries adicionales por fila: las horas brutas y de descanso (columnas ya persistidas del registro), las horas de entrada/salida en formato ISO 8601 para su presentación en 12h AM/PM, y las pausas del registro (nombre, icono, color, pagada, inicio, fin, duración) cargadas con eager loading por página. El front SHALL presentar trabajado y descansos en formato `Xh Ym` y SHALL ofrecer un detalle expandible de pausas por fila.

#### Scenario: Props del listado incluyen el detalle

- **WHEN** el admin abre el listado de registros
- **THEN** cada registro del prop paginado incluye `gross_hours`, `break_hours`, `clock_in`/`clock_out` en ISO 8601 y el arreglo `breaks` con los datos de cada pausa

#### Scenario: Pausas con eager loading

- **WHEN** se sirve una página de 20 registros con pausas
- **THEN** las pausas y sus tipos se resuelven con un número constante de queries (sin N+1)

#### Scenario: Registro en curso en el listado

- **WHEN** un registro no tiene `clock_out`
- **THEN** la fila muestra la hora de entrada en 12h y el registro permanece visible en el listado (comportamiento existente, ahora con el nuevo formato)
