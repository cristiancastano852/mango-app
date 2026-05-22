## REMOVED Requirements

### Requirement: Visitante puede registrar una nueva empresa
**Reason**: El producto cambia a un modelo gestionado — solo el super-admin puede crear empresas. El registro público sin supervisión ya no es el flujo deseado.
**Migration**: El alta de empresas se realiza desde el panel super-admin en `GET /super-admin/companies/create`.

### Requirement: Validación del formulario de registro
**Reason**: El formulario público de registro ya no existe. La validación se traslada a `StoreCompanyRequest` en el panel super-admin.
**Migration**: Ver `super-admin-company-creation` spec.

### Requirement: Formulario de registro tiene honeypot anti-spam
**Reason**: La ruta pública ya no existe; el honeypot pierde sentido.
**Migration**: No aplica.

### Requirement: Acceso al formulario de registro cuando ya autenticado
**Reason**: La ruta pública ya no existe.
**Migration**: No aplica.

## ADDED Requirements

### Requirement: La ruta pública de registro de empresa devuelve 404
El sistema SHALL devolver 404 para cualquier acceso a `GET /register/company` o `POST /register/company`.

#### Scenario: Visitante intenta acceder al formulario de registro
- **WHEN** cualquier visitante accede a `GET /register/company`
- **THEN** la respuesta es 404

#### Scenario: Intento de POST al endpoint de registro
- **WHEN** cualquier cliente envía `POST /register/company`
- **THEN** la respuesta es 404 o 405
