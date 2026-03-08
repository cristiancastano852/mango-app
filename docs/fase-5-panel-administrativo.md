# Fase 5 — Panel Administrativo

## Contexto

La Fase 4 entregó el motor de cálculo de horas (`CalculateWorkHours`), la configuración de recargos y la gestión de festivos. La Fase 5 construye encima de esa base el **Panel Administrativo** completo: dashboard en tiempo real, check-in manual, edición de registros, gestión de turnos y vista de calendario mensual.

---

## Funcionalidades implementadas

### 1. Dashboard con KPIs en tiempo real

**Controlador:** `app/Http/Controllers/DashboardController.php`
**Vista:** `resources/js/pages/Dashboard.vue`

El dashboard es la página principal para usuarios con rol `admin` o `super-admin`. Los empleados sin ese rol son redirigidos automáticamente al reloj de tiempo (`/time-clock`).

**KPIs mostrados:**

| KPI | Descripción |
|-----|-------------|
| Presentes hoy | Empleados con `clock_in` registrado hoy |
| En descanso | Empleados con una pausa activa (`ended_at IS NULL`) |
| Ausentes | Empleados con turno programado hoy pero sin `TimeEntry` |
| Horas netas hoy | Suma de `net_hours` del día + promedio por empleado presente |

Cada KPI de "Presentes" incluye el delta vs. el día anterior (ej. `+3 vs ayer`).

**Lista de estado de empleados:**

Muestra todos los empleados con su estado actual:
- `working` — tiene clock-in activo sin pausa en curso
- `on_break` — tiene una pausa activa
- `done` — completó la jornada (tiene clock-out)
- `absent` — sin registro hoy

**Polling automático:**

La página se refresca automáticamente cada 60 segundos usando `router.reload()` de Inertia v2. Un badge "En vivo" con punto verde parpadeante y contador de segundos indica cuándo fue la última actualización.

---

### 2. Alertas de llegadas tarde

Sección en el dashboard que muestra en tiempo real los empleados que:
- Tienen turno programado hoy (según `days_of_week` de su `Schedule`)
- Su hora de entrada (`start_time`) pasó hace más de 15 minutos
- No tienen ningún `TimeEntry` hoy

Cada alerta incluye el nombre del empleado, la hora programada y los minutos de retraso.

---

### 3. FAB — Check-in Manual del Admin

**Controlador:** `app/Http/Controllers/Admin/ManualCheckInController.php`
**Acción:** `app/Domain/TimeTracking/Actions/AdminClockIn.php`
**Ruta:** `POST /admin/manual-check-in`

Un botón flotante (FAB) en la esquina inferior derecha del dashboard abre un modal donde el admin selecciona un empleado y registra su entrada manualmente. El registro queda marcado con `pin_verified = false` para indicar que fue una entrada administrativa (no verificada por PIN del empleado).

Validaciones:
- El empleado no puede tener ya un check-in activo
- El empleado no puede haber completado la jornada ese día

---

### 4. Gestión de Turnos (Schedules CRUD)

**Controlador:** `app/Http/Controllers/SchedulesController.php`
**Rutas:** `GET|POST /schedules`, `GET|PUT|DELETE /schedules/{schedule}`
**Vistas:** `resources/js/pages/Schedules/`

CRUD completo para turnos laborales. Cada turno define:

| Campo | Descripción |
|-------|-------------|
| `name` | Nombre descriptivo del turno |
| `start_time` | Hora de entrada (HH:MM) |
| `end_time` | Hora de salida (HH:MM) |
| `break_duration` | Duración del descanso en minutos |
| `days_of_week` | Array de días activos (0=Dom, 1=Lun, ..., 6=Sáb) |

La vista de edición también muestra los empleados actualmente asignados a ese turno.

**Páginas Vue:**
- `Schedules/Index.vue` — lista con badges de días y conteo de empleados asignados
- `Schedules/Create.vue` — formulario de creación
- `Schedules/Edit.vue` — formulario de edición + sección de empleados asignados
- `Schedules/partials/ScheduleForm.vue` — componente compartido del formulario con checkboxes de días

---

### 5. Edición Manual de Registros de Tiempo

**Controlador:** `app/Http/Controllers/Admin/TimeEntryController.php`
**Rutas:** `GET /admin/time-entries`, `GET /admin/time-entries/{id}/edit`, `PUT /admin/time-entries/{id}`
**Vistas:** `resources/js/pages/Admin/TimeEntries/`

El admin puede ver todos los registros de tiempo filtrados por empleado y/o fecha, y editar los horarios de entrada/salida de cualquier registro.

Al actualizar un registro:
1. Se recalculan `gross_hours` y `net_hours` con los nuevos tiempos
2. Se llama a `CalculateWorkHours::execute()` para reclasificar las horas (regular, nocturna, dominical, extra)
3. Se guarda `edit_reason` (motivo obligatorio) y `edited_by` (ID del admin que realizó el cambio)
4. El `status` cambia a `'edited'`

**Páginas Vue:**
- `Admin/TimeEntries/Index.vue` — lista paginada con filtros de empleado y fecha
- `Admin/TimeEntries/Edit.vue` — formulario con campos `datetime-local` para clock-in/out y textarea para el motivo

---

### 6. Vista Calendario Mensual

**Controlador:** `app/Http/Controllers/CalendarController.php`
**Ruta:** `GET /calendar?month=Y-m&employee_id=opcional`
**Vista:** `resources/js/pages/Calendar/Index.vue`

Visualización de todos los registros del mes en un grid de calendario. Permite:
- Navegar entre meses con botones anterior/siguiente
- Filtrar por empleado individual
- Ver en cada celda del día los registros con horas netas trabajadas

Los chips de cada día son verdes si hay horas registradas, grises si el registro existe pero sin horas calculadas.

---

## Rutas nuevas

```php
// Dashboard
GET  /dashboard                           → DashboardController

// Admin — Turnos
GET  /schedules                           → SchedulesController@index
GET  /schedules/create                    → SchedulesController@create
POST /schedules                           → SchedulesController@store
GET  /schedules/{schedule}/edit           → SchedulesController@edit
PUT  /schedules/{schedule}                → SchedulesController@update
DELETE /schedules/{schedule}             → SchedulesController@destroy

// Admin — Calendario
GET  /calendar                            → CalendarController@index

// Admin — Registros de tiempo
GET  /admin/time-entries                  → Admin\TimeEntryController@index
GET  /admin/time-entries/{id}/edit        → Admin\TimeEntryController@edit
PUT  /admin/time-entries/{id}             → Admin\TimeEntryController@update

// Admin — Check-in manual
POST /admin/manual-check-in              → Admin\ManualCheckInController@store
```

Todas las rutas bajo `role:admin|super-admin` middleware.

---

## Archivos creados / modificados

### Backend

| Archivo | Tipo |
|---------|------|
| `app/Http/Controllers/DashboardController.php` | Nuevo |
| `app/Http/Controllers/Admin/ManualCheckInController.php` | Nuevo |
| `app/Http/Controllers/Admin/TimeEntryController.php` | Nuevo |
| `app/Http/Controllers/SchedulesController.php` | Nuevo |
| `app/Http/Controllers/CalendarController.php` | Nuevo |
| `app/Domain/TimeTracking/Actions/AdminClockIn.php` | Nuevo |
| `app/Http/Requests/StoreScheduleRequest.php` | Nuevo |
| `app/Http/Requests/UpdateScheduleRequest.php` | Nuevo |
| `app/Http/Requests/Admin/UpdateTimeEntryRequest.php` | Nuevo |
| `routes/web.php` | Modificado |
| `lang/en/messages.php` | Modificado |
| `lang/es/messages.php` | Modificado |

### Frontend

| Archivo | Tipo |
|---------|------|
| `resources/js/pages/Dashboard.vue` | Reemplazado (era placeholder) |
| `resources/js/pages/Admin/TimeEntries/Index.vue` | Nuevo |
| `resources/js/pages/Admin/TimeEntries/Edit.vue` | Nuevo |
| `resources/js/pages/Schedules/Index.vue` | Nuevo |
| `resources/js/pages/Schedules/Create.vue` | Nuevo |
| `resources/js/pages/Schedules/Edit.vue` | Nuevo |
| `resources/js/pages/Schedules/partials/ScheduleForm.vue` | Nuevo |
| `resources/js/pages/Calendar/Index.vue` | Nuevo |
| `resources/js/components/AppSidebar.vue` | Modificado (Schedules + Calendar) |
| `resources/js/locales/en.json` | Modificado |
| `resources/js/locales/es.json` | Modificado |

### Tests

| Archivo | Tests |
|---------|-------|
| `tests/Feature/DashboardTest.php` | 5 (reemplazado) |
| `tests/Feature/ManualCheckInTest.php` | 4 (nuevo) |
| `tests/Feature/Admin/TimeEntryControllerTest.php` | 6 (nuevo) |
| `tests/Feature/SchedulesControllerTest.php` | 6 (nuevo) |
| `tests/Feature/CalendarControllerTest.php` | 4 (nuevo) |

**Total suite:** 114 tests, todos en verde.

---

## Convenciones establecidas

- **`days_of_week`**: sigue la convención de Carbon/PHP — `0 = Domingo`, `1 = Lunes`, ..., `6 = Sábado`
- **CompanyScope**: los controladores admin NO necesitan `withoutGlobalScopes()` porque el usuario autenticado tiene `company_id` y el scope se aplica automáticamente
- **Polling**: se implementa con `setInterval` + `router.reload({ only: [...] })` — no se usa el método `poll()` de Inertia v2 para mayor control sobre el cleanup en `onUnmounted`
- **Edición auditada**: cualquier edición de `TimeEntry` por admin guarda `edited_by` + `edit_reason` y recalcula las horas vía `CalculateWorkHours`
