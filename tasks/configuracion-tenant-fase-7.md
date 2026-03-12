# FASE 7: Configuración del Tenant

## [Original]

> ### FASE 7: Configuracion del Tenant (Semana 7-8)
> 1. Dias laborales de la empresa
> 2. Horarios de trabajo por defecto
> 3. **Configuracion de tipos de pausa:**
>    - Crear/editar/desactivar tipos de pausa
>    - Definir si cada tipo es pagada o descuenta
>    - Limites de duracion y frecuencia por tipo
>    - Duracion por defecto de almuerzo
> 4. Gestion de dias festivos
> 5. Reglas de recargos (porcentajes configurables)
> 6. Logo y datos de la empresa
> 7. Zona horaria

---

## [Enhanced]

### Inventario de lo ya implementado

Antes de desglosar, se identifican las funcionalidades que **ya existen**:

| # | Sub-feature | Estado | Evidencia |
|---|-------------|--------|-----------|
| 4 | Gestión de días festivos | **HECHO** | `Settings/HolidayController` + `Holidays.vue` + rutas en `settings.php` |
| 5 | Reglas de recargos | **HECHO** | `Settings/SurchargeRuleController` + `SurchargeRules.vue` (incluye `night_start_time`/`night_end_time` según tarea `configuracion-horario-nocturno`) |

**Quedan por implementar:** sub-features 1, 2, 3, 6 y 7.

---

### Sub-feature 7.1 — Días laborales de la empresa

#### User Story
Como **administrador**, quiero definir qué días de la semana opera mi empresa para que los horarios y reportes reflejen únicamente los días laborales.

#### Descripción
Actualmente `schedules.days_of_week` define días por horario individual. Se necesita una configuración a nivel de empresa (`companies.settings.working_days`) que sirva como default al crear nuevos schedules y como referencia para reportes de ausentismo. El admin podrá seleccionar/deseleccionar días (checkboxes Lun-Dom) desde Settings.

#### Contexto técnico
- **Dominio:** `app/Domain/Company/`
- **Tablas:** `companies.settings` (jsonb) — agregar key `working_days: [1,2,3,4,5]` (convención Carbon: 0=Dom..6=Sáb)
- **Roles:** admin (su empresa), super-admin (cualquiera)
- **Multi-tenant:** sí, cada empresa su propio `settings`

#### Criterios de aceptación
- [ ] Admin puede ver y editar los días laborales desde `Settings → Empresa`
- [ ] Se guardan como array en `companies.settings.working_days`
- [ ] Al crear un nuevo Schedule, `days_of_week` se pre-llena con los días laborales de la empresa
- [ ] Mínimo 1 día debe estar seleccionado (validación)
- [ ] Cross-company: admin no puede editar settings de otra empresa

#### Desglose técnico — Backend
- **Migración:** ninguna (usa `companies.settings` jsonb existente)
- **Action:** `UpdateCompanySettings` en `app/Domain/Company/Actions/` — recibe y persiste keys del jsonb `settings`
- **Form Request:** `UpdateCompanySettingsRequest` — `working_days: required|array|min:1`, cada elemento `integer|between:0,6`
- **Controller:** `Settings/CompanySettingsController@edit` (GET) + `@update` (PUT)
- **Ruta:** `settings/company` en `settings.php`, middleware `role:admin|super-admin`
- **Tests:** happy path admin, happy path super-admin, cross-company, validación array vacío, valores fuera de rango

#### Desglose técnico — Frontend
- **Página:** `resources/js/pages/settings/CompanySettings.vue` (layout settings)
- **UI:** checkboxes (7 días) con `Checkbox` de shadcn-vue
- **Props:** `{ company: { settings: { working_days: number[] } } }`
- **i18n:** `settings.company`, `settings.working_days`, nombres de días

---

### Sub-feature 7.2 — Horarios de trabajo por defecto

#### User Story
Como **administrador**, quiero asignar un horario por defecto a mi empresa para que los nuevos empleados hereden automáticamente ese horario al ser creados.

#### Descripción
Se agrega `companies.settings.default_schedule_id` que apunta a un `Schedule` existente de la empresa. Al crear un empleado sin `schedule_id` explícito, se asigna automáticamente el default. El admin selecciona el horario desde el mismo formulario de `Settings → Empresa`.

#### Contexto técnico
- **Dominio:** `app/Domain/Company/` + `app/Domain/Employee/`
- **Tablas:** `companies.settings` (key `default_schedule_id`) + `schedules` (lectura)
- **Roles:** admin, super-admin
- **Multi-tenant:** sí, el schedule seleccionado debe pertenecer a la misma empresa

#### Criterios de aceptación
- [ ] Admin puede seleccionar un horario por defecto desde `Settings → Empresa`
- [ ] Solo se listan schedules de la misma empresa en el selector
- [ ] Al crear empleado sin schedule_id, se asigna el default
- [ ] Si no hay default configurado, el campo schedule_id del empleado queda null (comportamiento actual)
- [ ] Si el schedule default se elimina, el setting se limpia automáticamente

#### Desglose técnico — Backend
- **Migración:** ninguna
- **Action:** reutiliza `UpdateCompanySettings` — agrega validación de `default_schedule_id`
- **Form Request:** agregar `default_schedule_id: nullable|exists:schedules,id` con validación condicional de company_id
- **Action `CreateEmployee`:** si `schedule_id` es null, buscar default en company settings
- **Tests:** asignación automática al crear empleado, schedule de otra empresa rechazado, schedule eliminado limpia setting

#### Desglose técnico — Frontend
- **Página:** misma `CompanySettings.vue`
- **UI:** `Select` con lista de schedules de la empresa
- **Props:** agregar `schedules: Array<{ id: number, name: string }>` al controller

---

### Sub-feature 7.3 — Configuración de tipos de pausa

#### User Story
Como **administrador**, quiero crear y gestionar los tipos de pausa disponibles en mi empresa (almuerzo, café, personal, etc.) definiendo si son pagadas, sus límites de duración y frecuencia, para controlar las pausas de los empleados.

#### Descripción
El modelo `BreakType` ya existe con todos los campos necesarios (`is_paid`, `max_duration_minutes`, `max_per_day`, `is_default`, `is_active`). Falta el CRUD completo: un controller en Settings, páginas Vue para listar/crear/editar, y la lógica para marcar un tipo como "almuerzo por defecto" (`is_default = true`, solo uno por empresa).

El admin podrá:
- Crear nuevos tipos de pausa con nombre, icono, color, si es pagada, duración máxima y máximo por día
- Editar tipos existentes
- Desactivar tipos (soft-disable via `is_active = false`) sin eliminar datos históricos
- Marcar uno como "almuerzo por defecto" — al iniciar break sin tipo, se usa éste

#### Contexto técnico
- **Dominio:** `app/Domain/TimeTracking/` (modelo BreakType ya existe)
- **Tablas:** `break_types` — todas las columnas ya existen en data-model
- **Roles:** admin (su empresa), super-admin (cualquiera)
- **Multi-tenant:** sí, `break_types.company_id`

#### Criterios de aceptación
- [ ] Admin puede listar todos los tipos de pausa de su empresa
- [ ] Admin puede crear un nuevo tipo de pausa con: nombre, slug (auto-generado), is_paid, max_duration_minutes, max_per_day, is_default
- [ ] Admin puede editar un tipo existente
- [ ] Admin puede desactivar un tipo (`is_active = false`); los tipos desactivados no aparecen en el time-clock
- [ ] Solo un tipo puede ser `is_default = true` por empresa — al marcar uno, se desmarca el anterior
- [ ] El tipo por defecto define la duración de almuerzo (su `max_duration_minutes`)
- [ ] Cross-company: admin no puede ver/editar break types de otra empresa
- [ ] Validación: nombre requerido, max_duration_minutes > 0 si se proporciona, max_per_day >= 1 si se proporciona
- [ ] No se pueden eliminar tipos con break entries asociados — solo desactivar

#### Desglose técnico — Backend
- **Migración:** ninguna (tabla ya existe)
- **Actions:**
  - `CreateBreakType` en `app/Domain/TimeTracking/Actions/` — crea tipo, auto-genera slug, gestiona is_default
  - `UpdateBreakType` — actualiza, gestiona is_default (desmarca otros)
  - `ToggleBreakTypeActive` — cambia is_active; valida que no sea el default si se desactiva
- **Form Request:** `StoreBreakTypeRequest` y `UpdateBreakTypeRequest`
  - `name: required|string|max:255`
  - `is_paid: required|boolean`
  - `max_duration_minutes: nullable|integer|min:1`
  - `max_per_day: nullable|integer|min:1`
  - `is_default: boolean`
  - `icon: nullable|string|max:50`
  - `color: nullable|string|max:7` (hex)
- **Controller:** `Settings/BreakTypeController` — index, store, update, toggleActive (PATCH)
- **Rutas:** en `settings.php`, middleware `role:admin|super-admin`
  ```
  GET    settings/break-types           → index    (break-types.index)
  POST   settings/break-types           → store    (break-types.store)
  PUT    settings/break-types/{id}      → update   (break-types.update)
  PATCH  settings/break-types/{id}/toggle → toggleActive (break-types.toggle)
  ```
- **Factory:** crear `BreakTypeFactory` con states: `paid()`, `unpaid()`, `lunchDefault()`
- **Tests requeridos:**
  - CRUD happy path admin y super-admin
  - Cross-company: admin no puede ver/editar types de otra empresa
  - is_default: al marcar uno, el anterior se desmarca
  - Desactivar tipo default → error de validación
  - Tipo con break entries → no se elimina, solo desactiva
  - Validaciones de campos

#### Desglose técnico — Frontend
- **Página:** `resources/js/pages/settings/BreakTypes.vue` (layout settings)
- **UI:** tabla/lista de tipos con badges (pagada/no pagada), toggle para is_active, dialog para crear/editar
- **Componentes:** `Card`, `Badge`, `Dialog`, `Input`, `Checkbox`, `Button`, `Select`
- **Props:** `{ breakTypes: Array<BreakType> }`
- **Wayfinder:** imports desde `@/actions/Settings/BreakTypeController/*`
- **i18n:** `settings.break_types`, `break_type.name`, `break_type.is_paid`, `break_type.max_duration`, `break_type.max_per_day`, `break_type.is_default`, `break_type.active`/`inactive`
- **Nav:** agregar "Tipos de pausa" en `adminNavItems` del Settings Layout

---

### Sub-feature 7.4 — Gestión de días festivos

**YA IMPLEMENTADO** — `Settings/HolidayController` + `Holidays.vue` + rutas `holidays.*` en `settings.php`.

No requiere trabajo adicional.

---

### Sub-feature 7.5 — Reglas de recargos

**YA IMPLEMENTADO** — `Settings/SurchargeRuleController` + `SurchargeRules.vue`.
La configuración de horario nocturno se detalla en `tasks/configuracion-horario-nocturno.md`.

No requiere trabajo adicional.

---

### Sub-feature 7.6 — Logo y datos de la empresa

#### User Story
Como **administrador**, quiero editar el nombre, logo y datos básicos de mi empresa para personalizar la plataforma y los reportes.

#### Descripción
Se necesita un formulario en `Settings → Empresa` (junto con días laborales y horario default) donde el admin pueda actualizar: nombre de la empresa, logo (upload de imagen), país y otros datos básicos. El logo se almacena usando el filesystem de Laravel (disk `public`).

#### Contexto técnico
- **Dominio:** `app/Domain/Company/`
- **Tablas:** `companies` — campos `name`, `logo`, `country`
- **Roles:** admin (su empresa), super-admin
- **Multi-tenant:** sí, admin solo edita su propia empresa

#### Criterios de aceptación
- [ ] Admin puede ver y editar nombre de empresa y país
- [ ] Admin puede subir un logo (imagen: jpg, png, svg, max 2MB)
- [ ] El logo se muestra en el sidebar/header tras actualizar
- [ ] Admin puede eliminar el logo existente
- [ ] Cross-company: admin no puede editar datos de otra empresa
- [ ] Validación: nombre requerido, logo formato/tamaño

#### Desglose técnico — Backend
- **Migración:** ninguna
- **Action:** `UpdateCompanyProfile` en `app/Domain/Company/Actions/` — actualiza name, country, gestiona upload/delete de logo
- **Form Request:** `UpdateCompanyProfileRequest`
  - `name: required|string|max:255`
  - `logo: nullable|image|mimes:jpg,jpeg,png,svg|max:2048`
  - `country: required|string|size:2`
- **Controller:** `Settings/CompanyProfileController@edit` (GET) + `@update` (PUT)
- **Ruta:** en `settings.php`, middleware `role:admin|super-admin`
  ```
  GET  settings/company-profile  → edit   (company-profile.edit)
  PUT  settings/company-profile  → update (company-profile.update)
  ```
- **Tests:** happy path, upload de logo, eliminar logo, cross-company, validaciones

#### Desglose técnico — Frontend
- **Página:** `resources/js/pages/settings/CompanyProfile.vue`
- **UI:** formulario con Input (nombre), file input para logo con preview, Select para país
- **Props:** `{ company: { name, logo, country } }`
- **Nav:** agregar "Empresa" en `adminNavItems` del Settings Layout
- **i18n:** `settings.company_profile`, `company.name`, `company.logo`, `company.country`

---

### Sub-feature 7.7 — Zona horaria

#### User Story
Como **administrador**, quiero configurar la zona horaria de mi empresa para que todos los cálculos de horas, recargos y reportes usen la hora local correcta.

#### Descripción
El campo `companies.timezone` ya existe (default `America/Bogota`). Se necesita exponerlo en el formulario de `Settings → Empresa` con un selector de zonas horarias. El cambio afecta `CalculateWorkHours` y todos los formateos de fechas.

#### Contexto técnico
- **Dominio:** `app/Domain/Company/`
- **Tablas:** `companies.timezone` (ya existe)
- **Roles:** admin, super-admin
- **Multi-tenant:** sí

#### Criterios de aceptación
- [ ] Admin puede ver y cambiar la zona horaria desde `Settings → Empresa`
- [ ] El selector muestra zonas horarias relevantes para Latinoamérica (mínimo)
- [ ] El cambio se refleja en los cálculos futuros de CalculateWorkHours
- [ ] Validación: timezone debe ser un timezone válido de PHP

#### Desglose técnico — Backend
- **Migración:** ninguna
- **Action:** reutiliza `UpdateCompanyProfile` o `UpdateCompanySettings` — agrega timezone
- **Validación:** `timezone: required|timezone:all`
- **Tests:** cambio de timezone, timezone inválido rechazado

#### Desglose técnico — Frontend
- **Página:** misma `CompanyProfile.vue`
- **UI:** `Select` con opciones de timezone (lista filtrada o completa)
- **i18n:** `company.timezone`

---

### Plan de implementación sugerido (orden)

Dado que 7.4 y 7.5 ya están hechos, el orden recomendado es:

1. **7.3 — Tipos de pausa** (mayor complejidad, modelo ya existe, necesita CRUD completo + factory)
2. **7.6 + 7.7 — Logo/datos + Timezone** (se implementan juntos en un solo controller `CompanyProfileController`)
3. **7.1 + 7.2 — Días laborales + Horario default** (se implementan juntos en `CompanySettingsController`)

### Requisitos no funcionales
- **Seguridad:** todos los endpoints con middleware `role:admin|super-admin`, validación cross-company con `company_id` condicional para super-admin
- **Performance:** break types paginados si la empresa tiene muchos (>50); eager loading en listas

### Definición de Done (por sub-feature)
- [ ] Tests pasando (`php artisan test --compact --filter=FeatureName`)
- [ ] `vendor/bin/pint --dirty` sin errores
- [ ] `npm run build` exitoso
- [ ] `php artisan wayfinder:generate` ejecutado
- [ ] `ai-specs/specs/domain-model.md` actualizado con nuevos controllers/actions
- [ ] Nav items de Settings Layout actualizados
- [ ] i18n en `en.json` y `es.json`
