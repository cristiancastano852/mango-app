## ADDED Requirements

### Requirement: Admin puede listar tipos de pausa de su empresa
El sistema SHALL mostrar todos los `break_types` de la empresa del admin autenticado en `Settings → Tipos de pausa`, ordenados por nombre. Los tipos inactivos SHALL mostrarse con indicador visual diferenciado.

#### Scenario: Admin ve lista de tipos de pausa
- **WHEN** admin accede a `GET /settings/break-types`
- **THEN** la respuesta renderiza la página con todos los break types de su empresa
- **THEN** cada tipo muestra nombre, si es pagada, duración máxima, máximo por día, estado activo/inactivo y si es default

#### Scenario: Admin no ve tipos de pausa de otra empresa
- **WHEN** admin accede a `GET /settings/break-types`
- **THEN** solo se muestran break types con `company_id` igual al del admin

#### Scenario: Empleado no puede acceder a tipos de pausa
- **WHEN** usuario con rol `employee` accede a `GET /settings/break-types`
- **THEN** la respuesta es 403 Forbidden

---

### Requirement: Admin puede crear un tipo de pausa
El sistema SHALL permitir al admin crear nuevos tipos de pausa para su empresa con nombre, is_paid, max_duration_minutes, max_per_day e is_default. El slug SHALL generarse automáticamente desde el nombre.

#### Scenario: Admin crea tipo de pausa con datos válidos
- **WHEN** admin envía `POST /settings/break-types` con `name: "Almuerzo"`, `is_paid: false`, `max_duration_minutes: 60`, `max_per_day: 1`
- **THEN** se crea un `break_type` con `company_id` del admin, `slug: "almuerzo"`, `is_active: true`
- **THEN** la respuesta redirige a `break-types.index`

#### Scenario: Super-admin crea tipo de pausa para su contexto
- **WHEN** super-admin envía `POST /settings/break-types` con datos válidos
- **THEN** se crea el break type con `company_id` del super-admin (o null si no tiene empresa)

#### Scenario: Nombre duplicado en la misma empresa
- **WHEN** admin envía `POST /settings/break-types` con un `name` que ya existe para su empresa
- **THEN** la respuesta tiene errores de sesión para `name`

#### Scenario: Validación de campos requeridos
- **WHEN** admin envía `POST /settings/break-types` sin `name`
- **THEN** la respuesta tiene errores de sesión para `name`

#### Scenario: Validación de campos numéricos
- **WHEN** admin envía `max_duration_minutes: -5` o `max_per_day: 0`
- **THEN** la respuesta tiene errores de sesión para el campo correspondiente

---

### Requirement: Admin puede editar un tipo de pausa
El sistema SHALL permitir al admin actualizar nombre, is_paid, max_duration_minutes, max_per_day, icon y color de un tipo de pausa existente de su empresa.

#### Scenario: Admin actualiza tipo de pausa con datos válidos
- **WHEN** admin envía `PUT /settings/break-types/{id}` con campos actualizados
- **THEN** el break type se actualiza en base de datos con todos los campos enviados
- **THEN** la respuesta redirige a `break-types.index`

#### Scenario: Admin intenta editar tipo de pausa de otra empresa
- **WHEN** admin envía `PUT /settings/break-types/{id}` donde el break type pertenece a otra empresa
- **THEN** la respuesta tiene errores de sesión (cross-company)
- **THEN** la base de datos no cambia

---

### Requirement: Admin puede desactivar/activar un tipo de pausa
El sistema SHALL permitir al admin cambiar el estado `is_active` de un tipo de pausa. Los tipos desactivados NO SHALL aparecer como opciones en el time-clock al iniciar pausa.

#### Scenario: Admin desactiva un tipo de pausa no-default
- **WHEN** admin envía `PATCH /settings/break-types/{id}/toggle` para un tipo con `is_default: false`
- **THEN** `is_active` cambia a su valor opuesto
- **THEN** la respuesta redirige a `break-types.index`

#### Scenario: Admin intenta desactivar el tipo default
- **WHEN** admin envía `PATCH /settings/break-types/{id}/toggle` para un tipo con `is_default: true` e `is_active: true`
- **THEN** la respuesta tiene errores de sesión indicando que no se puede desactivar el tipo por defecto

#### Scenario: Admin reactiva un tipo desactivado
- **WHEN** admin envía `PATCH /settings/break-types/{id}/toggle` para un tipo con `is_active: false`
- **THEN** `is_active` cambia a `true`

---

### Requirement: Gestión de tipo de pausa por defecto (almuerzo)
Solo un tipo de pausa por empresa SHALL tener `is_default: true`. Al marcar uno como default, el anterior default SHALL desmarcarse automáticamente.

#### Scenario: Marcar nuevo tipo como default desmarca el anterior
- **WHEN** admin crea o actualiza un break type con `is_default: true`
- **AND** ya existe otro break type con `is_default: true` en la misma empresa
- **THEN** el anterior pierde `is_default` (se pone `false`)
- **THEN** el nuevo queda como único `is_default: true`

#### Scenario: El tipo default define la duración de almuerzo
- **WHEN** la empresa tiene un break type con `is_default: true` y `max_duration_minutes: 60`
- **THEN** ese valor representa la "duración por defecto de almuerzo" de la empresa

---

### Requirement: Authorization y multi-tenancy para break types
Todos los endpoints de break types SHALL requerir rol `admin` o `super-admin`. La aislación por tenant SHALL aplicarse en todas las operaciones.

#### Scenario: Cross-company — admin intenta toggle de break type de otra empresa
- **WHEN** admin envía `PATCH /settings/break-types/{id}/toggle` donde el break type pertenece a otra empresa
- **THEN** la respuesta tiene errores de sesión
- **THEN** la base de datos no cambia
