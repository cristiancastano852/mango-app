## ADDED Requirements

### Requirement: Super-admin puede navegar al formulario de creación desde el listado
El listado de empresas SHALL incluir un botón "Nueva empresa" que enlaza a `GET /super-admin/companies/create`.

#### Scenario: Botón Nueva empresa visible en el listado
- **WHEN** super-admin accede a `GET /super-admin/companies`
- **THEN** la página muestra un botón "Nueva empresa" que apunta a `/super-admin/companies/create`
