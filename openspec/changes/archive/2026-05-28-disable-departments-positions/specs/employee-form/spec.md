## REMOVED Requirements

### Requirement: Asignar departamento al crear/editar empleado
**Reason**: Funcionalidad inhabilitada provisionalmente. Los departamentos no se usan activamente en el flujo actual.
**Migration**: Re-habilitar buscando `DEPARTMENTS & POSITIONS FEATURE DISABLED` en el proyecto y des-comentando el código marcado.

#### Scenario: Formulario de creación sin selector de departamento
- **WHEN** un admin accede a `/employees/create`
- **THEN** el formulario NO muestra el selector de departamento ni de cargo

#### Scenario: Formulario de edición sin selector de departamento
- **WHEN** un admin accede a `/employees/{id}/edit`
- **THEN** el formulario NO muestra el selector de departamento ni de cargo

### Requirement: Filtrar lista de empleados por departamento
**Reason**: Funcionalidad inhabilitada provisionalmente junto con los selectores del formulario.
**Migration**: Re-habilitar buscando `DEPARTMENTS & POSITIONS FEATURE DISABLED` en el proyecto.

#### Scenario: Lista de empleados sin filtro de departamento
- **WHEN** un admin accede a `/employees`
- **THEN** la interfaz NO muestra el selector de departamento como filtro
