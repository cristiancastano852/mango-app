## ADDED Requirements

### Requirement: Super-admin puede crear una nueva empresa con su primer administrador
El sistema SHALL proveer un formulario en `GET /super-admin/companies/create` accesible solo para el rol `super-admin`. Al enviarlo, SHALL crear la Company y el primer User admin en una transacción atómica y mostrar la contraseña generada una sola vez.

#### Scenario: Super-admin accede al formulario de creación
- **WHEN** super-admin accede a `GET /super-admin/companies/create`
- **THEN** la respuesta es 200 con el formulario de creación

#### Scenario: Creación exitosa de empresa con admin
- **WHEN** super-admin envía `POST /super-admin/companies` con `company_name`, `admin_name`, `admin_email` válidos
- **THEN** se crea un registro en `companies` con el nombre de la empresa, `timezone = "America/Bogota"` y `country = "CO"` por defecto
- **THEN** se crea un registro en `users` con `company_id` de la empresa recién creada y rol `admin`
- **THEN** el `CompanyObserver` siembra `SurchargeRule` y festivos colombianos automáticamente
- **THEN** la respuesta redirige a `GET /super-admin/companies/{id}/edit`
- **THEN** la sesión contiene `created_password` con la contraseña generada

#### Scenario: Email de admin ya registrado en otra empresa
- **WHEN** super-admin envía `admin_email` que ya existe en la tabla `users`
- **THEN** la respuesta tiene errores de sesión para `admin_email`
- **THEN** no se crea ninguna empresa ni usuario

#### Scenario: Nombre de empresa vacío es rechazado
- **WHEN** super-admin envía `company_name` vacío
- **THEN** la respuesta tiene errores de sesión para `company_name`

#### Scenario: Email de admin inválido es rechazado
- **WHEN** super-admin envía `admin_email: "no-es-email"`
- **THEN** la respuesta tiene errores de sesión para `admin_email`

#### Scenario: Nombre de admin vacío es rechazado
- **WHEN** super-admin envía `admin_name` vacío
- **THEN** la respuesta tiene errores de sesión para `admin_name`

#### Scenario: Admin de empresa no puede acceder al formulario de creación
- **WHEN** usuario con rol `admin` accede a `GET /super-admin/companies/create`
- **THEN** la respuesta es 403 Forbidden

#### Scenario: Admin de empresa no puede crear empresas
- **WHEN** usuario con rol `admin` envía `POST /super-admin/companies`
- **THEN** la respuesta es 403 Forbidden

#### Scenario: Usuario no autenticado es redirigido al login
- **WHEN** usuario no autenticado accede a `GET /super-admin/companies/create`
- **THEN** la respuesta redirige a la página de login
