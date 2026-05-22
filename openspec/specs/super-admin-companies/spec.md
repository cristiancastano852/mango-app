## ADDED Requirements

### Requirement: Super-admin puede navegar al formulario de creación desde el listado
El listado de empresas SHALL incluir un botón "Nueva empresa" que enlaza a `GET /super-admin/companies/create`.

#### Scenario: Botón Nueva empresa visible en el listado
- **WHEN** super-admin accede a `GET /super-admin/companies`
- **THEN** la página muestra un botón "Nueva empresa" que apunta a `/super-admin/companies/create`

---

### Requirement: Super-admin puede editar información de cualquier empresa
El sistema SHALL permitir al super-admin editar cualquier campo del modelo Company: `name`, `slug`, `logo`, `timezone`, `country`, `subscription_plan`, `trial_ends_at`.

#### Scenario: Super-admin accede al formulario de edición
- **WHEN** super-admin accede a `GET /super-admin/companies/{id}/edit`
- **THEN** la respuesta es 200 con los datos actuales de la empresa

#### Scenario: Super-admin actualiza nombre y slug
- **WHEN** super-admin envía `PUT /super-admin/companies/{id}` con `name: "Nueva Nombre"` y `slug: "nuevo-slug"`
- **THEN** la empresa se actualiza en base de datos con los nuevos valores
- **THEN** la respuesta redirige a `GET /super-admin/companies/{id}/edit` con mensaje de éxito

#### Scenario: Super-admin actualiza plan de suscripción
- **WHEN** super-admin envía `PUT /super-admin/companies/{id}` con `subscription_plan: "premium"`
- **THEN** `companies.subscription_plan` se actualiza a `"premium"`

#### Scenario: Slug duplicado es rechazado
- **WHEN** super-admin envía `slug` que ya pertenece a otra empresa
- **THEN** la respuesta tiene errores de sesión para `slug`

#### Scenario: Slug vacío es rechazado
- **WHEN** super-admin envía `slug` vacío
- **THEN** la respuesta tiene errores de sesión para `slug`

#### Scenario: Nombre vacío es rechazado
- **WHEN** super-admin envía `name` vacío
- **THEN** la respuesta tiene errores de sesión para `name`

#### Scenario: Admin de empresa no puede usar estos endpoints
- **WHEN** usuario con rol `admin` envía `PUT /super-admin/companies/{id}`
- **THEN** la respuesta es 403 Forbidden

---

### Requirement: Super-admin ve los administradores existentes de una empresa
El sistema SHALL mostrar en el formulario de edición de empresa la lista de usuarios con rol `admin` asociados a esa empresa.

#### Scenario: Empresa con admins existentes muestra la lista
- **WHEN** super-admin accede a `GET /super-admin/companies/{id}/edit` y la empresa tiene dos usuarios con rol `admin`
- **THEN** la respuesta incluye los dos admins con `id`, `name`, `email`, `is_active`

#### Scenario: Empresa sin admins muestra lista vacía
- **WHEN** super-admin accede a `GET /super-admin/companies/{id}/edit` y la empresa no tiene usuarios con rol `admin`
- **THEN** la respuesta incluye lista de admins vacía

---

### Requirement: Super-admin puede crear un usuario administrador para una empresa
El sistema SHALL permitir al super-admin crear un nuevo usuario con rol `admin` asociado a una empresa específica. La contraseña generada SHALL mostrarse una sola vez en pantalla.

#### Scenario: Super-admin crea admin con datos válidos
- **WHEN** super-admin envía `POST /super-admin/companies/{id}/admin-users` con `name: "Juan Pérez"` y `email: "juan@empresa.com"`
- **THEN** se crea un User con `company_id = {id}` y rol `admin`
- **THEN** la respuesta redirige a `GET /super-admin/companies/{id}/edit`
- **THEN** la sesión contiene `created_password` con la contraseña generada

#### Scenario: La contraseña generada no se almacena en texto plano
- **WHEN** se crea el admin
- **THEN** `users.password` contiene el hash bcrypt de la contraseña, no la contraseña en texto plano

#### Scenario: Email duplicado en la plataforma es rechazado
- **WHEN** super-admin envía `email` que ya existe en la tabla `users`
- **THEN** la respuesta tiene errores de sesión para `email`

#### Scenario: Email inválido es rechazado
- **WHEN** super-admin envía `email: "no-es-email"`
- **THEN** la respuesta tiene errores de sesión para `email`

#### Scenario: Nombre vacío es rechazado
- **WHEN** super-admin envía `name` vacío en `POST /super-admin/companies/{id}/admin-users`
- **THEN** la respuesta tiene errores de sesión para `name`

#### Scenario: Admin de empresa no puede crear admins en otras empresas
- **WHEN** usuario con rol `admin` envía `POST /super-admin/companies/{id}/admin-users`
- **THEN** la respuesta es 403 Forbidden

---

### Requirement: Dashboard redirige al super-admin al panel de empresas
El sistema SHALL redirigir al super-admin al panel `/super-admin/companies` al acceder a `/dashboard`, en lugar de mostrar el dashboard de métricas.

#### Scenario: Super-admin accede a /dashboard
- **WHEN** super-admin autenticado accede a `GET /dashboard`
- **THEN** la respuesta redirige a `GET /super-admin/companies`

#### Scenario: Admin de empresa ve el dashboard normalmente
- **WHEN** usuario con rol `admin` accede a `GET /dashboard`
- **THEN** la respuesta es 200 con el dashboard de métricas (sin cambios)

---

### Requirement: Autorización estricta en todos los endpoints de super-admin
Todos los endpoints bajo `/super-admin/` SHALL requerir exactamente el rol `super-admin`. Ningún otro rol SHALL tener acceso.

#### Scenario: Usuario no autenticado es redirigido al login
- **WHEN** usuario no autenticado accede a `GET /super-admin/companies`
- **THEN** la respuesta redirige a la página de login
