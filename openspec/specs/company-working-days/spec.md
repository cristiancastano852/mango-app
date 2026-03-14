## ADDED Requirements

### Requirement: Admin puede configurar días laborales de su empresa
El sistema SHALL permitir al admin definir qué días de la semana opera su empresa, almacenados en `companies.settings.working_days` como array de enteros (convención Carbon: 0=Dom, 1=Lun, ..., 6=Sáb). El default SHALL ser `[1,2,3,4,5]` (lunes a viernes).

#### Scenario: Admin ve días laborales actuales
- **WHEN** admin accede a `GET /settings/company-settings`
- **THEN** la página muestra checkboxes para los 7 días de la semana
- **THEN** los días configurados en `settings.working_days` están marcados

#### Scenario: Admin actualiza días laborales
- **WHEN** admin envía `PUT /settings/company-settings` con `working_days: [1,2,3,4,5,6]`
- **THEN** `companies.settings.working_days` se actualiza a `[1,2,3,4,5,6]`
- **THEN** la respuesta redirige con mensaje de éxito

#### Scenario: Empresa sin working_days configurados
- **WHEN** `companies.settings` es null o no tiene key `working_days`
- **THEN** se usa el default `[1,2,3,4,5]` en la vista

#### Scenario: Super-admin accede sin empresa asignada
- **WHEN** super-admin con `company_id = null` accede a `GET /settings/company-settings`
- **THEN** la página muestra un estado vacío indicando que no tiene empresa asignada

#### Scenario: Empleado no puede acceder a configuración de empresa
- **WHEN** usuario con rol `employee` accede a `GET /settings/company-settings`
- **THEN** la respuesta es 403 Forbidden

---

### Requirement: Validación de días laborales
El sistema SHALL validar que `working_days` sea un array con al menos 1 elemento, y que cada elemento sea un entero entre 0 y 6.

#### Scenario: Array vacío de días laborales
- **WHEN** admin envía `working_days: []`
- **THEN** la respuesta tiene errores de sesión para `working_days`

#### Scenario: Valor fuera de rango
- **WHEN** admin envía `working_days: [1,2,7]`
- **THEN** la respuesta tiene errores de sesión para `working_days.2`

#### Scenario: Valores duplicados se aceptan sin error
- **WHEN** admin envía `working_days: [1,1,2,3]`
- **THEN** se almacenan valores únicos `[1,2,3]` (deduplicación)

---

### Requirement: Admin puede seleccionar horario por defecto de la empresa
El sistema SHALL permitir al admin seleccionar un `Schedule` existente de su empresa como horario por defecto, almacenado en `companies.settings.default_schedule_id`.

#### Scenario: Admin selecciona horario por defecto
- **WHEN** admin envía `PUT /settings/company-settings` con `default_schedule_id: 5`
- **AND** el schedule 5 pertenece a la misma empresa
- **THEN** `companies.settings.default_schedule_id` se actualiza a `5`

#### Scenario: Admin selecciona schedule de otra empresa
- **WHEN** admin envía `default_schedule_id` que pertenece a otra empresa
- **THEN** la respuesta tiene errores de sesión para `default_schedule_id`

#### Scenario: Admin limpia horario por defecto
- **WHEN** admin envía `default_schedule_id: null`
- **THEN** `companies.settings.default_schedule_id` se pone null
- **THEN** nuevos empleados no recibirán schedule automáticamente

#### Scenario: Selector solo muestra schedules de la misma empresa
- **WHEN** admin accede a `GET /settings/company-settings`
- **THEN** el selector de horario por defecto solo lista schedules con `company_id` del admin

---

### Requirement: Nuevos empleados heredan el horario por defecto
`CreateEmployee` SHALL asignar el `default_schedule_id` de la empresa cuando no se proporciona `schedule_id` explícito.

#### Scenario: Empleado creado sin schedule_id hereda el default
- **WHEN** se ejecuta `CreateEmployee` con `schedule_id: null`
- **AND** la empresa tiene `settings.default_schedule_id: 5`
- **THEN** el empleado se crea con `schedule_id: 5`

#### Scenario: Empleado creado con schedule_id explícito ignora el default
- **WHEN** se ejecuta `CreateEmployee` con `schedule_id: 3`
- **AND** la empresa tiene `settings.default_schedule_id: 5`
- **THEN** el empleado se crea con `schedule_id: 3`

#### Scenario: Empresa sin default_schedule_id
- **WHEN** se ejecuta `CreateEmployee` con `schedule_id: null`
- **AND** la empresa no tiene `default_schedule_id` configurado
- **THEN** el empleado se crea con `schedule_id: null` (comportamiento actual)

---

### Requirement: Limpieza automática al eliminar schedule default
Cuando se elimina un Schedule que es el `default_schedule_id` de una empresa, el sistema SHALL limpiar ese setting automáticamente.

#### Scenario: Schedule default eliminado limpia el setting
- **WHEN** se elimina un schedule con id `5`
- **AND** una empresa tiene `settings.default_schedule_id: 5`
- **THEN** `settings.default_schedule_id` de esa empresa se pone null

---

### Requirement: Authorization y multi-tenancy para configuración de empresa
Todos los endpoints de configuración de empresa SHALL requerir rol `admin` o `super-admin`. El admin solo SHALL poder configurar su propia empresa.

#### Scenario: Cross-company — admin no puede editar settings de otra empresa
- **WHEN** admin intenta enviar configuración para una empresa diferente
- **THEN** el sistema usa `$request->user()->company_id` (ignora company_id enviado)
- **THEN** solo se modifica la empresa del admin autenticado
