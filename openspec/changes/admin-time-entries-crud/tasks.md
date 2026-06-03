## 1. Schema y modelo (soft-delete)

- [x] 1.1 Crear migración para `time_entries`: `dropUnique(['employee_id','date'])`, `softDeletes()`, `unique(['employee_id','date','deleted_at'])`, con `down()` que revierta
- [x] 1.2 Agregar trait `SoftDeletes` a `TimeEntry` y ejecutar la migración
- [x] 1.3 Actualizar `ai-specs/specs/data-model.md` con `deleted_at` y el nuevo índice único
- [x] 1.4 Revisar/ajustar `TimeEntryFactory` para soportar estados usados en tests (con/sin pausas, eliminado)

## 2. Action de recálculo centralizada

- [x] 2.1 Crear Action `RecalculateTimeEntry` en `Domain/TimeTracking/Actions`: recomputa `gross_hours` → `break_hours` (pausas no pagadas finalizadas) → `net_hours = max(0, gross − break)` → invoca `CalculateWorkHours` → marca `status = 'edited'`
- [x] 2.2 Test unitario/feature de `RecalculateTimeEntry`: caso sin pausas, con pausa no pagada, con pausa pagada (no descuenta)
- [x] 2.3 `vendor/bin/pint --dirty --format agent` y correr los tests del recálculo

## 3. Crear registro (store)

- [x] 3.1 Crear `StoreTimeEntryRequest`: `employee_id` (exists + company condicional), `date`, `clock_in`, `clock_out` (after clock_in), unicidad activa `whereNull('deleted_at')`, mensajes en `messages.php`
- [x] 3.2 Agregar `create`/`store` a `Admin/TimeEntryController` (delgado): crear `TimeEntry` y delegar a `RecalculateTimeEntry`
- [x] 3.3 Registrar rutas `admin.time-entries.create` y `admin.time-entries.store` en `web.php`; `php artisan wayfinder:generate`
- [x] 3.4 Feature test `store`: creación exitosa, día con registro activo (rechazo), recrear tras soft-delete (éxito), `clock_out` no posterior (rechazo), por rol (admin/super-admin/employee), cross-company
- [x] 3.5 `pint` y correr tests de `store`

## 4. Editar horas del registro (update ampliado)

- [x] 4.1 Ampliar `UpdateTimeEntryRequest`: validar que las pausas existentes sigan dentro del nuevo rango `clock_in`–`clock_out`
- [x] 4.2 Refactorizar `update` de `Admin/TimeEntryController` para delegar el recálculo en `RecalculateTimeEntry` (persistiendo `edited_by`/`edit_reason`)
- [x] 4.3 Feature test `update`: edición exitosa con recálculo, motivo requerido, pausa que queda fuera del nuevo rango, cross-company
- [x] 4.4 `pint` y correr tests de `update`

## 5. Gestión de pausas (sub-recurso anidado)

- [x] 5.1 Crear `StoreBreakRequest` y `UpdateBreakRequest`: `break_type_id` (exists + company), `started_at`, `ended_at` (after start), rango dentro del turno; mensajes en `messages.php`
- [x] 5.2 Crear `Admin/TimeEntryBreakController` con `store`, `update`, `destroy` (delgados): operar sobre `BreakEntry`, recalcular `duration_minutes` y delegar a `RecalculateTimeEntry`
- [x] 5.3 Registrar rutas anidadas `admin/time-entries/{timeEntry}/breaks` (store) y `.../breaks/{break}` (update, destroy); `php artisan wayfinder:generate`
- [x] 5.4 Feature test pausas: agregar, editar horas, cambiar tipo (pagado↔no pagado recalcula net), eliminar, fuera de rango (rechazo), break_type de otra empresa (rechazo), recálculo del registro tras cada operación
- [x] 5.5 `pint` y correr tests de pausas

## 6. Eliminar registro (soft-delete)

- [x] 6.1 Agregar `destroy` a `Admin/TimeEntryController` (soft-delete) y ruta `admin.time-entries.destroy`; `php artisan wayfinder:generate`
- [x] 6.2 Feature test `destroy`: soft-delete exitoso, registro excluido del listado, día queda libre para recrear, cross-company (rechazo), por rol
- [x] 6.3 `pint` y correr tests de `destroy`

## 7. Listado con filtro de rango de fechas

- [x] 7.1 Ampliar `index` de `Admin/TimeEntryController`: filtro por rango (`date_from`/`date_to`) además de empleado; excluir soft-deleted (automático con SoftDeletes)
- [x] 7.2 Feature test `index`: filtro por empleado, por rango de fechas, sin filtros, registros eliminados ocultos
- [x] 7.3 `pint` y correr tests de `index`

## 8. Frontend — sección Registros

- [x] 8.1 Revisar `components/ui/` (card, dialog, select, input, button, badge) antes de construir
- [x] 8.2 Actualizar `Admin/TimeEntries/Index.vue`: filtro de rango de fechas, botón "Crear", acción "Eliminar" (con confirmación), enlaces vía Wayfinder
- [x] 8.3 Crear `Admin/TimeEntries/Create.vue`: formulario empleado + fecha + horas (usar skill frontend-design para una página pulida)
- [x] 8.4 Ampliar `Admin/TimeEntries/Edit.vue`: gestión de pausas (listar, agregar, editar inicio/fin, cambiar tipo, eliminar) además de horas + motivo
- [x] 8.5 Agregar item de sidebar "Registros" en `AppSidebar.vue` condicionado a `isAdmin`; claves i18n `nav.time_entries` en `es.json`/`en.json`
- [x] 8.6 Agregar/actualizar claves i18n de la sección (títulos, labels, mensajes) en `es.json`/`en.json`
- [x] 8.7 `npm run build` y verificar que compila

## 9. Dashboard — solo estado

- [x] 9.1 `DashboardController`: quitar `net_hours_today`, `clock_in`, `clock_out` y `time_entry_id` del payload `employeeStatus`; conservar KPIs y check-in
- [x] 9.2 `Dashboard.vue`: eliminar columnas de horas y el link "Editar"; conservar avatar/nombre/estado, KPIs, polling y FAB de check-in manual
- [x] 9.3 Feature test `DashboardController`: payload de empleados no incluye tiempos ni `time_entry_id`; KPIs presentes; check-in manual sigue funcionando
- [x] 9.4 `pint`, `npm run build` y correr el test del dashboard

## 10. Cierre

- [x] 10.1 Actualizar `ai-specs/specs/domain-model.md` con la nueva Action `RecalculateTimeEntry` y los controllers/requests nuevos
- [x] 10.2 Correr la suite completa con `php artisan test --compact` y confirmar verde
- [ ] 10.3 Verificación manual rápida: crear/editar/eliminar registro, editar pausas, y dashboard sin tiempos
