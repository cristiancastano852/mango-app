## Why

Los campos de departamento y cargo no se usan activamente en el flujo actual de la aplicación, pero aparecen en el formulario de empleados y como filtro en los reportes, generando confusión. Se inhabilitan provisionalmente siguiendo el patrón ya establecido para `schedules` y `locations`, dejando el código comentado para re-habilitación futura.

## What Changes

- Formulario de empleados (Create/Edit): selectores de `department_id` y `position_id` eliminados visualmente; campos removidos del form state.
- Lista de empleados (`/employees`): filtro de departamento removido.
- Reporte de empresa (`/reports/company`): selector de departamento removido del card de lanzamiento y del panel de filtros interno. El reporte ahora siempre incluye a todos los empleados.
- Backend (`EmployeeController`, `ReportController`): se dejan de pasar las props `departments` y `positions` al frontend; el eager load de relaciones `department`/`position` se comenta; `buildCompanyReport` siempre recibe `departmentId = null`.
- Tests afectados actualizados: `ReportControllerTest` y `BreakTypeControllerTest`.

## Capabilities

### New Capabilities
- ninguna

### Modified Capabilities
- `employee-form`: los campos `department_id` y `position_id` ya no se muestran ni se envían al crear/editar un empleado.
- `company-report`: el reporte de empresa ya no acepta filtro por departamento; siempre devuelve todos los empleados.

## Non-goals

- No se eliminan las tablas `departments` ni `positions` de la base de datos.
- No se eliminan los modelos, relaciones ni migraciones.
- No se modifica la validación backend (los campos siguen siendo `nullable`).

## Impact

- **Dominio afectado**: Organization (Department/Position), Employee, TimeTracking (Reports).
- **Multi-tenant**: sí — `department_id` en `employees` es scoped por `company_id`; sin impacto funcional al inhabilitar.
- **Roles**: solo `admin` y `super-admin` acceden a formulario de empleados y reportes.
- **Migración de BD**: no requerida.
