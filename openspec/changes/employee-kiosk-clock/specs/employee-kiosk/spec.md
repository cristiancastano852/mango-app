## ADDED Requirements

### Requirement: Vista pública de kiosco accesible por slug de empresa
El sistema SHALL exponer una ruta pública `GET /kiosk/{company:slug}` que muestre la pantalla de ingreso de documento sin requerir autenticación.

**Authorization:** Sin autenticación requerida (guest). La empresa se resuelve por `slug`.

**Business Rules:**
- Si el `slug` no corresponde a ninguna empresa, el sistema MUST devolver 404.
- El kiosco MUST mostrar el nombre de la empresa en la cabecera.

#### Scenario: Empresa válida
- **WHEN** un usuario accede a `/kiosk/{slug}` con un slug existente
- **THEN** el sistema muestra la pantalla de ingreso de número de documento con el nombre de la empresa

#### Scenario: Empresa inexistente
- **WHEN** un usuario accede a `/kiosk/{slug}` con un slug que no existe
- **THEN** el sistema responde con 404

---

### Requirement: Lookup de empleado por número de documento
El sistema SHALL permitir buscar un empleado dentro de la empresa por su `document_number` mediante `POST /kiosk/{company:slug}/lookup`.

**Authorization:** Sin autenticación. El empleado se busca dentro del scope de la empresa del slug.

**Business Rules:**
- `document_number` es requerido y MUST coincidir con un empleado activo de esa empresa.
- Si se encuentra, el sistema MUST guardar el `employee_id` en sesión (`kiosk_employee_id`) y devolver nombre del empleado + estado del día actual.
- Si no se encuentra, el sistema MUST devolver un error de validación (no exponer si el documento no existe o si el empleado está inactivo).
- El estado del día actual incluye: `status` del `time_entry` de hoy (si existe), hora de `clock_in`, breaks del día con `started_at`, `ended_at` y nombre del tipo.

#### Scenario: Documento válido
- **WHEN** el empleado ingresa su número de documento correcto
- **THEN** el sistema muestra "¡Hola, [Nombre Apellido]!" y las acciones disponibles según el estado del día

#### Scenario: Documento no encontrado
- **WHEN** el empleado ingresa un número de documento que no existe en esa empresa
- **THEN** el sistema muestra un error de validación y no revela detalles del fallo

#### Scenario: Empleado sin actividad hoy
- **WHEN** el empleado encontrado no tiene `time_entry` para hoy
- **THEN** el sistema muestra el botón "Iniciar jornada" como única acción disponible

#### Scenario: Empleado en jornada activa
- **WHEN** el empleado tiene `time_entry` con `status = clocked_in`
- **THEN** el sistema muestra los botones "Iniciar pausa" (con tipos de pausa) y "Finalizar jornada"

#### Scenario: Empleado en pausa activa
- **WHEN** el empleado tiene `time_entry` con `status = on_break`
- **THEN** el sistema muestra el botón "Finalizar pausa"

#### Scenario: Empleado con jornada finalizada
- **WHEN** el empleado tiene `time_entry` con `status = clocked_out`
- **THEN** el sistema muestra un mensaje informativo indicando que la jornada ya fue finalizada, sin acciones disponibles

---

### Requirement: Acciones de registro desde el kiosco
El sistema SHALL permitir ejecutar las acciones de tiempo (clock-in, clock-out, start-break, end-break) desde el kiosco sin autenticación, usando el `employee_id` guardado en sesión.

**Authorization:** El `employee_id` en sesión MUST pertenecer a la empresa del slug. Si no coincide, el sistema MUST devolver 403.

**Business Rules:**
- `POST /kiosk/{company:slug}/clock-in` → invoca `ClockIn` action.
- `POST /kiosk/{company:slug}/clock-out` → invoca `ClockOut` action.
- `POST /kiosk/{company:slug}/break/start` → invoca `StartBreak` action; requiere `break_type_id` válido de esa empresa.
- `POST /kiosk/{company:slug}/break/end` → invoca `EndBreak` action.
- Tras ejecutar cualquier acción, el sistema MUST limpiar `kiosk_employee_id` de la sesión.
- Las rutas POST MUST tener `throttle:10,1` para limitar intentos por minuto.

#### Scenario: Clock-in exitoso
- **WHEN** el empleado pulsa "Iniciar jornada" con sesión kiosco activa
- **THEN** el sistema registra el `clock_in` y devuelve mensaje de confirmación con la hora de entrada

#### Scenario: Clock-out exitoso
- **WHEN** el empleado pulsa "Finalizar jornada" con `status = clocked_in`
- **THEN** el sistema registra el `clock_out` y devuelve mensaje de confirmación

#### Scenario: Inicio de pausa exitoso
- **WHEN** el empleado selecciona un tipo de pausa y pulsa "Iniciar pausa"
- **THEN** el sistema registra el inicio de pausa y devuelve mensaje de confirmación

#### Scenario: Fin de pausa exitoso
- **WHEN** el empleado pulsa "Finalizar pausa" con una pausa activa
- **THEN** el sistema registra el fin de pausa y devuelve mensaje de confirmación

#### Scenario: Acción con sesión expirada o manipulada
- **WHEN** se intenta una acción POST sin `kiosk_employee_id` válido en sesión o de otra empresa
- **THEN** el sistema devuelve 403

#### Scenario: Rate limit excedido
- **WHEN** se realizan más de 10 requests POST al kiosco en un minuto desde la misma IP
- **THEN** el sistema devuelve 429 (Too Many Requests)

---

### Requirement: Confirmación post-acción con auto-reset
El sistema SHALL mostrar una pantalla de confirmación tras cada acción exitosa, con un countdown de 5 segundos, y luego resetear automáticamente a la pantalla de ingreso de documento.

**Business Rules:**
- La confirmación MUST mostrar el nombre del empleado y la acción realizada con su hora.
- El countdown de 5 segundos MUST visualizarse con una barra de progreso.
- Al finalizar el countdown, la pantalla MUST navegar automáticamente a la vista inicial del kiosco (pantalla de ingreso de documento).
- El empleado MUST poder pulsar un botón para acelerar el reset manualmente.

#### Scenario: Auto-reset tras acción
- **WHEN** una acción se completa exitosamente
- **THEN** se muestra la confirmación con countdown y tras 5 segundos se vuelve a la pantalla de documento

#### Scenario: Reset manual
- **WHEN** el empleado pulsa el botón de reset durante el countdown
- **THEN** la pantalla vuelve inmediatamente a la pantalla de ingreso de documento

---

### Requirement: Vista del día actual en el kiosco
El sistema SHALL mostrar únicamente la actividad del día actual del empleado, sin históricos de días anteriores.

**Business Rules:**
- El estado del día MUST mostrar: hora de entrada (si existe), pausa activa actual (si existe) con tipo y hora de inicio, y hora de salida (si la jornada ya finalizó).
- El kiosco NO MUST mostrar el cronómetro en tiempo real ni el total de horas acumuladas.
- El kiosco NO MUST mostrar entradas de días anteriores.

#### Scenario: Empleado ve su actividad del día
- **WHEN** el empleado ingresa su documento y tiene actividad hoy
- **THEN** ve su hora de entrada y el estado actual (trabajando, en pausa, finalizado)

#### Scenario: Sin historial previo visible
- **WHEN** el empleado ingresa su documento
- **THEN** no se muestran entradas de días anteriores ni totales históricos
