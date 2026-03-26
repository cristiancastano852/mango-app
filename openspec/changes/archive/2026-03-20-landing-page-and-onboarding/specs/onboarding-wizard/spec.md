## ADDED Requirements

### Requirement: Wizard de onboarding accesible solo para admin recién registrado
El sistema SHALL proteger las rutas `/onboarding/*` con middleware `auth`. Si el admin ya completó el onboarding (`onboarding_completed = true`), las rutas SHALL redirigir a `/dashboard`.

#### Scenario: Admin sin onboarding completado accede al wizard
- **WHEN** admin autenticado con `onboarding_completed = false` accede a `GET /onboarding/company`
- **THEN** la respuesta es 200 con el paso 1 del wizard

#### Scenario: Admin con onboarding ya completado intenta acceder al wizard
- **WHEN** admin autenticado con `onboarding_completed = true` accede a `GET /onboarding/company`
- **THEN** la respuesta redirige a `/dashboard`

#### Scenario: Employee intenta acceder al wizard
- **WHEN** usuario con rol `employee` accede a `GET /onboarding/company`
- **THEN** la respuesta es 403 Forbidden

---

### Requirement: Paso 1 — Perfil básico de empresa
El sistema SHALL mostrar en `GET /onboarding/company` un formulario con: nombre de empresa (pre-rellenado), timezone (selector, default America/Bogota), país (selector, pre-rellenado CO). Al guardar, SHALL actualizar la empresa y redirigir al paso 2.

#### Scenario: Admin completa el paso 1 exitosamente
- **WHEN** admin envía `POST /onboarding/company` con `name`, `timezone` y `country` válidos
- **THEN** la empresa se actualiza con esos valores
- **THEN** la respuesta redirige a `GET /onboarding/schedule`

#### Scenario: Timezone inválido en paso 1
- **WHEN** admin envía `timezone: "Invalid/Zone"`
- **THEN** la respuesta tiene errores de sesión para `timezone`

---

### Requirement: Paso 2 — Horario de trabajo por defecto
El sistema SHALL mostrar en `GET /onboarding/schedule` un formulario para crear un horario de trabajo por defecto: nombre, hora inicio, hora fin, días de la semana (checkboxes). Al guardar, SHALL crear el Schedule y asignarlo como `default_schedule_id` en la empresa.

#### Scenario: Admin crea horario de trabajo en paso 2
- **WHEN** admin envía `POST /onboarding/schedule` con `name: "Jornada Normal"`, `start_time: "08:00"`, `end_time: "17:00"`, `days_of_week: [1,2,3,4,5]`
- **THEN** se crea un registro en `schedules` con `company_id` del admin
- **THEN** la respuesta redirige a `GET /onboarding/break-types`

#### Scenario: Admin omite el paso 2 (skip)
- **WHEN** admin accede a `POST /onboarding/schedule` con acción "skip"
- **THEN** no se crea ningún schedule
- **THEN** la respuesta redirige a `GET /onboarding/break-types`

#### Scenario: Hora de fin anterior a hora de inicio
- **WHEN** admin envía `start_time: "17:00"`, `end_time: "08:00"` sin pasar por medianoche
- **THEN** la respuesta tiene errores de sesión para `end_time`

---

### Requirement: Paso 3 — Tipos de pausa activos
El sistema SHALL mostrar en `GET /onboarding/break-types` los 5 tipos de pausa por defecto (almuerzo, descanso, baño, personal, médica) con toggles para activar/desactivar cada uno. Al guardar, SHALL actualizar `is_active` de cada tipo y marcar el onboarding como completado.

#### Scenario: Admin configura tipos de pausa y finaliza onboarding
- **WHEN** admin envía `POST /onboarding/break-types` con lista de break_type_ids activos
- **THEN** los tipos seleccionados quedan con `is_active = true`
- **THEN** los tipos no seleccionados quedan con `is_active = false`
- **THEN** `companies.onboarding_completed` se actualiza a `true`
- **THEN** la respuesta redirige a `/dashboard` con mensaje de éxito

#### Scenario: Admin no desmarca ningún tipo de pausa
- **WHEN** admin envía todos los break types activos
- **THEN** todos quedan con `is_active = true`
- **THEN** `onboarding_completed` se actualiza a `true`
- **THEN** la respuesta redirige a `/dashboard`

---

### Requirement: Indicador de progreso del wizard
La UI SHALL mostrar un indicador de progreso con los 3 pasos (Empresa, Horario, Pausas) y el paso actual resaltado.

#### Scenario: Admin en paso 2 ve el indicador de progreso
- **WHEN** admin está en `GET /onboarding/schedule`
- **THEN** la UI muestra paso 1 como completado, paso 2 como activo, paso 3 como pendiente
