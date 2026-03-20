## MODIFIED Requirements

### Requirement: Admin puede ver y editar datos básicos de su empresa
El sistema SHALL mostrar un formulario en `Settings → Empresa` con nombre, país y timezone de la empresa del admin. El admin SHALL poder actualizar estos campos.

#### Scenario: Admin ve datos actuales de su empresa
- **WHEN** admin accede a `GET /settings/company-profile`
- **THEN** la página muestra nombre, logo actual (si existe), país y timezone de su empresa

#### Scenario: Admin actualiza nombre y país
- **WHEN** admin envía `PUT /settings/company-profile` con `name: "Mi Empresa SAS"`, `country: "CO"`
- **THEN** la empresa se actualiza con esos valores
- **THEN** la respuesta redirige con mensaje de éxito

#### Scenario: Super-admin accede sin empresa asignada
- **WHEN** super-admin con `company_id = null` accede a `GET /settings/company-profile`
- **THEN** la página muestra un estado vacío indicando que no tiene empresa asignada

#### Scenario: Empleado no puede acceder al perfil de empresa
- **WHEN** usuario con rol `employee` accede a `GET /settings/company-profile`
- **THEN** la respuesta es 403 Forbidden

---

### Requirement: Admin puede subir y eliminar logo de empresa
El sistema SHALL permitir al admin subir una imagen como logo de la empresa (jpg, jpeg, png, svg, máximo 2MB). El logo SHALL almacenarse en el disco `public` de Laravel.

#### Scenario: Admin sube logo válido
- **WHEN** admin envía `PUT /settings/company-profile` con archivo `logo` de tipo png y 500KB
- **THEN** el archivo se almacena en `storage/app/public/logos/`
- **THEN** `companies.logo` se actualiza con el path del archivo
- **THEN** el logo anterior (si existía) se elimina del storage

#### Scenario: Admin sube archivo que excede 2MB
- **WHEN** admin envía logo de 5MB
- **THEN** la respuesta tiene errores de sesión para `logo`

#### Scenario: Admin sube archivo no-imagen
- **WHEN** admin envía archivo `.pdf` como logo
- **THEN** la respuesta tiene errores de sesión para `logo`

#### Scenario: Admin elimina logo existente
- **WHEN** admin envía `PUT /settings/company-profile` con `remove_logo: true`
- **THEN** el archivo se elimina del storage
- **THEN** `companies.logo` se pone null

---

### Requirement: Admin puede cambiar la zona horaria de su empresa
El sistema SHALL permitir al admin seleccionar una zona horaria válida de PHP. El cambio SHALL afectar únicamente cálculos futuros de `CalculateWorkHours`.

#### Scenario: Admin cambia timezone a zona válida
- **WHEN** admin envía `PUT /settings/company-profile` con `timezone: "America/Mexico_City"`
- **THEN** `companies.timezone` se actualiza a `"America/Mexico_City"`

#### Scenario: Admin envía timezone inválido
- **WHEN** admin envía `timezone: "Invalid/Zone"`
- **THEN** la respuesta tiene errores de sesión para `timezone`

#### Scenario: Timezone default para nuevas empresas
- **WHEN** se crea una nueva empresa sin timezone explícito
- **THEN** `timezone` es `"America/Bogota"` (sin cambio, ya funciona así)

---

### Requirement: Validación de datos de empresa
El Form Request SHALL validar todos los campos del perfil de empresa.

#### Scenario: Nombre vacío
- **WHEN** admin envía `name` vacío
- **THEN** la respuesta tiene errores de sesión para `name`

#### Scenario: País con formato inválido
- **WHEN** admin envía `country: "Colombia"` (debe ser código ISO 2 letras)
- **THEN** la respuesta tiene errores de sesión para `country`

#### Scenario: Nombre demasiado largo
- **WHEN** admin envía `name` con más de 255 caracteres
- **THEN** la respuesta tiene errores de sesión para `name`

---

### Requirement: Authorization y multi-tenancy para perfil de empresa
Todos los endpoints de perfil de empresa SHALL requerir rol `admin` o `super-admin`. El admin solo SHALL poder editar su propia empresa.

#### Scenario: Cross-company — admin no puede editar otra empresa
- **WHEN** admin intenta enviar datos para una empresa diferente a la suya
- **THEN** el sistema usa `$request->user()->company_id` (ignora cualquier company_id enviado)
- **THEN** solo se modifica la empresa del admin autenticado

## ADDED Requirements

### Requirement: Modelo Company expone campo `onboarding_completed`
El modelo Company SHALL tener el atributo `onboarding_completed` (boolean, default false) persistido en BD. El campo SHALL ser casteable a boolean.

#### Scenario: Nueva empresa tiene onboarding_completed = false
- **WHEN** se crea una nueva empresa via registro
- **THEN** `companies.onboarding_completed` es `false`

#### Scenario: Empresa existente pre-feature tiene onboarding_completed = true
- **WHEN** se corre la migración en producción
- **THEN** empresas creadas antes de la migración tienen `onboarding_completed = true` (no se interrumpe su flujo)
