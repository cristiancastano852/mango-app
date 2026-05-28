## Context

La aplicación tiene modelos `Department` y `Position` con sus tablas, relaciones y formularios. Actualmente los empleados pueden ser asignados a un departamento y cargo, y los reportes de empresa pueden filtrarse por departamento. Sin embargo, estas funciones no están siendo utilizadas activamente, y su presencia en el UI genera confusión. El proyecto ya tiene precedente de inhabilitar features provisionalmente dejando el código comentado (`schedules`, `locations`).

## Goals / Non-Goals

**Goals:**
- Eliminar departamento y cargo del formulario de creación/edición de empleados.
- Eliminar el filtro de departamento de la lista de empleados y de los reportes.
- Mantener toda la infraestructura de BD, modelos y relaciones intacta.
- Dejar el código comentado con marcadores `// DEPARTMENTS & POSITIONS FEATURE DISABLED` para fácil re-habilitación.

**Non-Goals:**
- Eliminar tablas, modelos, factories ni seeders.
- Cambiar validaciones backend (los campos siguen siendo `nullable`).
- Crear migraciones.

## Decisions

**Patrón de comentado**: Se sigue el mismo patrón establecido para `schedules` y `locations`: código comentado con marcador `// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore X when re-enabling.` Esto permite búsqueda global para re-habilitar.

**Profundidad del cambio — solo UI y controllers**: La validación backend (`StoreEmployeeRequest`, `UpdateEmployeeRequest`) no se toca porque `department_id` y `position_id` ya son `nullable`. Si el frontend no los envía, el backend los acepta sin error.

**Reportes — siempre todos los empleados**: `buildCompanyReport` en `ReportController` ahora pasa `null` como `departmentId`, lo que equivale a "sin filtro". La lógica de filtrado en `GenerateCompanyReport` permanece intacta para cuando se re-habilite.

**Tests**: Los assertions de `->has('departments')` en `ReportControllerTest` se comentan (no se eliminan). Los tests de `BreakTypeControllerTest` se actualizan para contar con los 5 break types que `SeedDefaultBreakTypes` inserta automáticamente vía `CompanyObserver`.

## Risks / Trade-offs

- **Datos existentes**: Empleados que ya tenían `department_id` o `position_id` asignados conservan esos valores en BD, pero no son visibles en el UI ni editables hasta re-habilitar. → Aceptable; los datos no se pierden.
- **Exportes**: Los exports de Excel/PDF del reporte de empresa tienen una columna "Departamento" que mostrará `N/A` para todos los empleados. → Aceptable en el estado actual.
- **Re-habilitación**: Buscar `DEPARTMENTS & POSITIONS FEATURE DISABLED` en el proyecto para encontrar todos los puntos a restaurar.
