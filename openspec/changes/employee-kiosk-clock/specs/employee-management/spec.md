## ADDED Requirements

### Requirement: Campo document_number en empleados
El sistema SHALL añadir el campo `document_number` (string) al modelo `Employee`, representando el número de cédula o documento de identidad del trabajador.

**Authorization:** Solo roles `admin` y `super-admin` pueden establecer o modificar el `document_number`.

**Business Rules:**
- `document_number` es nullable — empleados existentes no se ven afectados.
- `document_number` MUST ser único dentro de la misma empresa (`unique` scoped por `company_id`).
- `document_number` acepta letras y números (string máx. 50 caracteres).
- El campo MUST estar disponible en los formularios de creación y edición de empleados en el panel admin.
- Si se intenta guardar un `document_number` que ya existe en otro empleado de la misma empresa, el sistema MUST devolver un error de validación.

#### Scenario: Admin crea empleado con document_number
- **WHEN** el admin completa el campo `document_number` con un valor válido al crear un empleado
- **THEN** el empleado se crea con ese `document_number` guardado

#### Scenario: Admin crea empleado sin document_number
- **WHEN** el admin deja el campo `document_number` vacío al crear un empleado
- **THEN** el empleado se crea con `document_number = null` sin error

#### Scenario: document_number duplicado en la misma empresa
- **WHEN** el admin intenta crear o editar un empleado con un `document_number` que ya usa otro empleado de la misma empresa
- **THEN** el sistema devuelve un error de validación en el campo `document_number`

#### Scenario: document_number igual en diferentes empresas
- **WHEN** dos empleados de empresas distintas tienen el mismo `document_number`
- **THEN** ambos se guardan sin error (la unicidad es por empresa)

#### Scenario: Admin edita document_number de empleado existente
- **WHEN** el admin actualiza el `document_number` de un empleado con un valor válido y único en su empresa
- **THEN** el campo se actualiza correctamente
