## ADDED Requirements

### Requirement: Los tipos de pausa sembrados son editables por el admin
El sistema SHALL permitir al admin modificar, desactivar o eliminar los tipos de pausa sembrados automáticamente, igual que cualquier otro tipo de pausa. No existe distinción de "protegido" para los tipos sembrados.

#### Scenario: Admin puede editar un tipo sembrado
- **WHEN** admin accede a `PUT /settings/break-types/{id}` para un tipo de pausa sembrado
- **THEN** el tipo se actualiza correctamente
- **THEN** los demás tipos sembrados no se modifican
