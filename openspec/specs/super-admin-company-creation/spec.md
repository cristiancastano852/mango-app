## ADDED Requirements

### Requirement: Super-admin puede crear una nueva empresa con su primer administrador
El sistema SHALL proveer un formulario en `GET /super-admin/companies/create` accesible solo para el rol `super-admin`. Al enviarlo, SHALL crear la Company y el primer User admin en una transacción atómica y mostrar la contraseña generada una sola vez. El `slug` de la empresa SHALL servir como su subdominio público, por lo que SHALL ser una etiqueta DNS-safe, única y estable.

**Business Rules del slug/subdominio:**
- El super-admin PUEDE indicar un subdominio explícito en el formulario; si lo omite, se autogenera desde el nombre de la empresa SIN sufijo random, agregando un sufijo numérico solo en caso de colisión (`elmango`, `elmango-2`).
- El slug SHALL validar el patrón DNS-safe `^[a-z0-9]([a-z0-9-]*[a-z0-9])?$` con longitud ≤ 63.
- El slug NO SHALL ser un subdominio reservado (al menos `www`).
- El slug SHALL ser único en `companies.slug`.
- El slug es inmutable tras la creación (la edición de subdominio queda fuera de alcance).

#### Scenario: Super-admin accede al formulario de creación
- **WHEN** super-admin accede a `GET /super-admin/companies/create`
- **THEN** la respuesta es 200 con el formulario de creación

#### Scenario: Creación exitosa de empresa con admin
- **WHEN** super-admin envía `POST /super-admin/companies` con `company_name`, `admin_name`, `admin_email` válidos
- **THEN** se crea un registro en `companies` con el nombre de la empresa, `timezone = "America/Bogota"` y `country = "CO"` por defecto
- **THEN** el `slug` generado es DNS-safe y único (sin sufijo random; sufijo numérico solo si colisiona)
- **THEN** se crea un registro en `users` con `company_id` de la empresa recién creada y rol `admin`
- **THEN** el `CompanyObserver` siembra `SurchargeRule` y festivos colombianos automáticamente
- **THEN** la respuesta redirige a `GET /super-admin/companies/{id}/edit`
- **THEN** la sesión contiene `created_password` con la contraseña generada

#### Scenario: Super-admin indica un subdominio explícito válido
- **WHEN** super-admin envía `POST /super-admin/companies` con un subdominio explícito `elmango` que no existe y es DNS-safe
- **THEN** la empresa se crea con `slug = "elmango"`

#### Scenario: Subdominio explícito ya en uso es rechazado
- **WHEN** super-admin envía un subdominio que ya existe como `slug` de otra empresa
- **THEN** la respuesta tiene errores de sesión para el subdominio
- **THEN** no se crea ninguna empresa ni usuario

#### Scenario: Subdominio con formato inválido es rechazado
- **WHEN** super-admin envía un subdominio con caracteres inválidos (p. ej. `El Mango!`)
- **THEN** la respuesta tiene errores de sesión para el subdominio

#### Scenario: Subdominio reservado es rechazado
- **WHEN** super-admin envía un subdominio reservado (p. ej. `www`)
- **THEN** la respuesta tiene errores de sesión para el subdominio

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
