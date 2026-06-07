## MODIFIED Requirements

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
- **THEN** el sistema muestra "Iniciar pausa" como acción primaria y "Finalizar jornada" como acción secundaria visualmente diferenciada y separada

#### Scenario: Empleado en pausa activa
- **WHEN** el empleado tiene `time_entry` con `status = on_break`
- **THEN** el sistema muestra únicamente el botón "Finalizar pausa" y NO MUST exponer la acción de finalizar jornada

#### Scenario: Empleado con jornada finalizada
- **WHEN** el empleado tiene `time_entry` con `status = clocked_out`
- **THEN** el sistema muestra un mensaje informativo indicando que la jornada ya fue finalizada, sin acciones disponibles

## ADDED Requirements

### Requirement: Distinción visual entre iniciar pausa y finalizar jornada en el kiosco
En el estado `clocked_in`, el kiosco SHALL diferenciar visualmente "Iniciar pausa" de "Finalizar jornada" para minimizar el riesgo de pulsar la acción equivocada.

**Business Rules:**
- "Iniciar pausa" MUST presentarse como acción primaria (estilo ámbar, tamaño grande).
- "Finalizar jornada" MUST presentarse como acción secundaria: estilo diferenciado (terracota/outline), tamaño menor, icono distinto, y separada de "Iniciar pausa" por un divisor con microcopy.
- "Finalizar jornada" NO MUST pintarse con un color de alarma fuerte (rojo intenso); la fricción de seguridad recae en la confirmación, no en el color.

#### Scenario: Botones diferenciados en estado clocked_in
- **WHEN** el empleado está en estado `clocked_in`
- **THEN** "Iniciar pausa" se muestra como acción primaria ámbar y "Finalizar jornada" como acción secundaria terracota separada por un divisor

### Requirement: Confirmación antes de finalizar jornada en el kiosco
El kiosco SHALL solicitar confirmación mediante un modal propio antes de ejecutar la finalización de jornada.

**Business Rules:**
- Al pulsar "Finalizar jornada", el sistema MUST mostrar un modal de confirmación antes de invocar `clock-out`; no MUST ejecutarse el clock-out directamente.
- El modal MUST mostrar la hora de entrada y el tiempo trabajado calculado en cliente desde `clock_in`.
- El modal MUST ofrecer un botón seguro ("No, volver") como acción primaria/fácil y un botón deliberado ("Sí, finalizar") para confirmar.
- El modal MUST usar el lenguaje visual del kiosco (no un `confirm()` nativo del navegador).
- Solo al confirmar "Sí, finalizar" el sistema MUST invocar `POST /kiosk/{company:slug}/clock-out`.

#### Scenario: Cancelar finalización
- **WHEN** el empleado pulsa "Finalizar jornada" y luego "No, volver"
- **THEN** el modal se cierra, no se ejecuta ningún clock-out y el empleado permanece en estado `clocked_in`

#### Scenario: Confirmar finalización
- **WHEN** el empleado pulsa "Finalizar jornada" y luego "Sí, finalizar"
- **THEN** el sistema ejecuta el clock-out y muestra la pantalla de confirmación con la hora de salida

#### Scenario: Contexto visible en el modal
- **WHEN** se muestra el modal de confirmación
- **THEN** el empleado ve la hora de entrada y el tiempo trabajado del día
