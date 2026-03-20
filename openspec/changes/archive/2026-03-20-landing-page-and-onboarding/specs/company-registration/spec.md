## ADDED Requirements

### Requirement: Visitante puede registrar una nueva empresa
El sistema SHALL proveer un formulario en `GET /register/company` accesible sin autenticación. Al enviar el formulario, el sistema SHALL crear la Company y el User admin en una transacción atómica, iniciar sesión automáticamente y redirigir al wizard de onboarding.

#### Scenario: Registro exitoso de empresa
- **WHEN** visitante envía `POST /register/company` con `company_name`, `name`, `email`, `password`, `password_confirmation` válidos
- **THEN** se crea un registro en `companies` con el nombre de la empresa y `timezone = "America/Bogota"` por defecto
- **THEN** se crea un registro en `users` con `company_id` de la empresa recién creada y rol `admin`
- **THEN** el `CompanyObserver` siembra `SurchargeRule` y festivos colombianos automáticamente
- **THEN** el usuario queda autenticado en la sesión
- **THEN** la respuesta redirige a `/onboarding/company`

#### Scenario: Email ya registrado
- **WHEN** visitante envía `POST /register/company` con un email que ya existe en `users`
- **THEN** la respuesta tiene errores de sesión para `email`
- **THEN** no se crea ninguna empresa ni usuario

#### Scenario: Contraseñas no coinciden
- **WHEN** visitante envía `password` y `password_confirmation` distintos
- **THEN** la respuesta tiene errores de sesión para `password`

#### Scenario: Campos requeridos vacíos
- **WHEN** visitante envía el formulario con `company_name` vacío
- **THEN** la respuesta tiene errores de sesión para `company_name`

#### Scenario: Contraseña muy corta
- **WHEN** visitante envía `password` con menos de 8 caracteres
- **THEN** la respuesta tiene errores de sesión para `password`

---

### Requirement: Validación del formulario de registro
El Form Request SHALL validar: `company_name` (required, max 255), `name` (required, max 255), `email` (required, email, unique:users), `password` (required, min 8, confirmed).

#### Scenario: Nombre de empresa demasiado largo
- **WHEN** visitante envía `company_name` con más de 255 caracteres
- **THEN** la respuesta tiene errores de sesión para `company_name`

#### Scenario: Email con formato inválido
- **WHEN** visitante envía `email: "no-es-un-email"`
- **THEN** la respuesta tiene errores de sesión para `email`

---

### Requirement: Formulario de registro tiene honeypot anti-spam
El formulario SHALL incluir un campo honeypot oculto. Si el campo viene con valor, el sistema SHALL rechazar silenciosamente el registro sin mostrar error (simular éxito).

#### Scenario: Bot llena el campo honeypot
- **WHEN** se envía el formulario con el campo honeypot no vacío
- **THEN** la respuesta simula éxito pero no crea ningún registro en BD

---

### Requirement: Acceso al formulario de registro cuando ya autenticado
Si un usuario ya autenticado accede a `GET /register/company`, SHALL ser redirigido al dashboard.

#### Scenario: Usuario autenticado intenta acceder al registro
- **WHEN** usuario con sesión activa accede a `GET /register/company`
- **THEN** la respuesta redirige a `/dashboard`
