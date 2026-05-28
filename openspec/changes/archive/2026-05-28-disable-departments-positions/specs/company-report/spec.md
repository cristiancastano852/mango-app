## REMOVED Requirements

### Requirement: Filtrar reporte de empresa por departamento
**Reason**: Funcionalidad inhabilitada provisionalmente. Sin departamentos asignados a empleados, el filtro no tiene utilidad.
**Migration**: Re-habilitar buscando `DEPARTMENTS & POSITIONS FEATURE DISABLED` en `ReportController` y en los componentes `Reports/Index.vue` y `Reports/Company.vue`.

#### Scenario: Pantalla de reportes sin selector de departamento
- **WHEN** un admin accede a `/reports`
- **THEN** el card "Reporte de Empresa" NO muestra selector de departamento
- **THEN** al hacer click en "Generar" se genera el reporte con todos los empleados

#### Scenario: Reporte de empresa sin filtro interno de departamento
- **WHEN** un admin está dentro de `/reports/company`
- **THEN** el panel de filtros NO muestra selector de departamento
- **THEN** el reporte incluye siempre a todos los empleados de la empresa
