## ADDED Requirements

### Requirement: Login restringido al subdominio de la empresa

El sistema SHALL permitir que un usuario inicie sesión únicamente en el subdominio correspondiente a su propia empresa. En un subdominio de tenant solo SHALL autenticarse un usuario cuyo `company_id` coincide con la `Company` del subdominio; cualquier otro intento SHALL fallar sin iniciar sesión.

**Business Rules:**
- El gate se evalúa durante la autenticación de Fortify, con acceso al tenant resuelto del subdominio.
- Credenciales correctas pero pertenecientes a otra empresa SHALL fallar como autenticación inválida (no iniciar sesión).
- El aislamiento de sesión entre subdominios se apoya en cookies host-only (`SESSION_DOMAIN` sin definir).

**Authorization:**
- `admin` / `employee`: pueden autenticarse solo en el subdominio de su `Company`.
- `super-admin`: NO puede autenticarse en un subdominio de tenant (ver requirement de `admin.webplena.com`).

#### Scenario: Usuario de la empresa inicia sesión en su subdominio
- **WHEN** un usuario de la empresa con `slug = "restaurantex"` envía credenciales válidas en `restaurantex.webplena.com`
- **THEN** la sesión se inicia correctamente

#### Scenario: Usuario de otra empresa no puede iniciar sesión
- **WHEN** un usuario cuyo `company_id` pertenece a la empresa B envía credenciales válidas en `restaurantex.webplena.com` (empresa A)
- **THEN** la autenticación falla y no se inicia sesión

#### Scenario: Super-admin no puede iniciar sesión en un subdominio de tenant
- **WHEN** el `super-admin` (`company_id = null`) envía credenciales válidas en `restaurantex.webplena.com`
- **THEN** la autenticación falla y no se inicia sesión

### Requirement: Login de administración de plataforma restringido a super-admin

El sistema SHALL permitir en el host de administración (`admin.webplena.com`) únicamente la autenticación del `super-admin`. Un usuario `admin` o `employee` NO SHALL poder iniciar sesión ahí. El host público (`webplena.com`/`www`) no ofrece login.

**Authorization:**
- `super-admin`: puede autenticarse en `admin.webplena.com`.
- `admin` / `employee`: no pueden autenticarse en `admin.webplena.com`; deben usar el subdominio de su empresa.

#### Scenario: Super-admin inicia sesión en el host de administración
- **WHEN** el `super-admin` envía credenciales válidas en `admin.webplena.com`
- **THEN** la sesión se inicia correctamente y accede al panel super-admin

#### Scenario: Admin no puede iniciar sesión en el host de administración
- **WHEN** un usuario `admin` envía credenciales válidas en `admin.webplena.com`
- **THEN** la autenticación falla y no se inicia sesión
