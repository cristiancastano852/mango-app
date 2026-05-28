## 1. Backend — EmployeeController

- [x] 1.1 Comentar imports de `Department` y `Position`
- [x] 1.2 Comentar eager load `department`/`position` en `index()`, `show()`, `edit()`
- [x] 1.3 Comentar filtro `->when(department)` en `index()`
- [x] 1.4 Comentar props `departments`/`positions` en `create()` y `edit()`

## 2. Backend — ReportController

- [x] 2.1 Comentar import de `Department`
- [x] 2.2 Comentar prop `departments` en `index()` y `company()`
- [x] 2.3 Comentar `department_id` en `filters` del render de `company()`
- [x] 2.4 Pasar `null` como `departmentId` en `buildCompanyReport()`

## 3. Frontend — Formulario de empleados

- [x] 3.1 Comentar imports de `Select`, `computed` y tipos en `EmployeeForm.vue`
- [x] 3.2 Comentar props `departments`/`positions` y campos `department_id`/`position_id` en type `Props`
- [x] 3.3 Comentar `filteredPositions` computed
- [x] 3.4 Comentar sección de selectores de departamento/cargo en el template
- [x] 3.5 Comentar props y campos en `Create.vue`
- [x] 3.6 Comentar props y campos en `Edit.vue`

## 4. Frontend — Lista de empleados

- [x] 4.1 Comentar import de `Department` y prop `departments` en `Index.vue`
- [x] 4.2 Comentar variable `department` y función `onDepartmentChange`
- [x] 4.3 Comentar Select de departamento en template
- [x] 4.4 Remover `department` de la llamada de paginación

## 5. Frontend — Reportes

- [x] 5.1 Comentar prop `departments` y `selectedDepartment` en `Reports/Index.vue`
- [x] 5.2 Comentar selector de departamento del card empresa en template
- [x] 5.3 Comentar `department_id` en `goToCompanyReport()`
- [x] 5.4 Comentar prop `departments` y `department_id` en filters de `Reports/Company.vue`
- [x] 5.5 Comentar `selectedDepartment` y referencias a `department_id` en funciones
- [x] 5.6 Comentar selector de departamento en template del reporte

## 6. Tests

- [x] 6.1 Comentar assertions `->has('departments')` en `ReportControllerTest`
- [x] 6.2 Actualizar `BreakTypeControllerTest` para contar con 5 break types por defecto (CompanyObserver)
- [x] 6.3 Verificar suite completa: 379 tests passing
