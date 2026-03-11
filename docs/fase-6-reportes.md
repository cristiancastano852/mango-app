# Fase 6 — Reportes

## Contexto

La Fase 5 entregó el panel administrativo con dashboard en tiempo real, check-in manual, edición de registros y calendario. La Fase 6 construye el módulo de **Reportes** completo: consulta de horas y costos por empleado o empresa, gráficas de tendencias y exportación a Excel y PDF.

---

## Funcionalidades implementadas

### 1. Reporte individual de empleado (`/reports/employee`)
- KPIs: días trabajados, horas brutas, horas netas, costo total
- Mini-tarjetas por tipo de hora: ordinarias, nocturnas, extras, dom/festivas
- Tabla de resumen de costos con porcentajes de recargo por ley colombiana
- Tabla de pausas por tipo (almuerzo, descanso, baño, etc.)
- Gráficas: barra apilada (desglose diario de horas), área (tendencia de horas netas), dona (distribución de pausas)

### 2. Reporte general de empresa (`/reports/company`)
- KPIs: empleados activos, horas netas, días trabajados, costo total
- Gráficas: barra+línea (asistencia diaria), dona (distribución de costos)
- Tabla ranking de empleados ordenada por horas netas DESC

### 3. Filtros de fecha
- Presets: Hoy, Semana, Quincena (1–15 / 16–fin de mes, lógica colombiana), Mes, Personalizado
- Componente reutilizable `DateRangeFilter.vue`
- Filtro por empleado (reporte individual) y por departamento (reporte empresa)

### 4. Exportación a Excel
- Reporte empleado: 2 hojas (Resumen + Detalle Diario)
- Reporte empresa: 3 hojas (Resumen + Empleados + Asistencia Diaria)
- Celdas con estilos: encabezados en negrita, filas de totales destacadas, columnas auto-dimensionadas

### 5. Exportación a PDF
- Plantillas Blade (`resources/views/exports/`) con HTML/CSS inline (compatible con DomPDF)
- Hoja horizontal (`landscape`) para que las tablas quepan correctamente
- Incluye: KPIs, desglose de horas, costos, pausas y detalle diario/empleados
- El nombre del archivo lleva el nombre del empleado o `reporte-empresa`

### 6. Seeder de datos demo (`ReportDemoSeeder`)
- Crea 3 perfiles de empleados con patrones variados durante el último mes:
  - **María García** (turno mañana 07:00–15:00 COT): mayoritariamente horas ordinarias
  - **Ana López** (turno tarde 14:00–22:00+ COT): horas nocturnas (después de las 21:00 COT)
  - **Pedro Martínez** (trabaja domingos): horas dom/festivas + horas nocturnas
- Ejecutar: `php artisan db:seed --class=ReportDemoSeeder`

---

## Arquitectura y decisiones técnicas

### Acciones de dominio
Tres acciones en `app/Domain/TimeTracking/Actions/`:

| Acción | Responsabilidad |
|--------|----------------|
| `CalculateReportCosts` | Calcula costos aplicando porcentajes de recargo. Sin queries, 100% testeable como unit test. |
| `GenerateEmployeeReport` | Genera el reporte individual con 3 queries DB-level (aggregateTotals, aggregateBreaksByType, getDailyBreakdown). |
| `GenerateCompanyReport` | Genera el reporte de empresa con JOINs y `COUNT(DISTINCT)`. Sin N+1. |

### Eficiencia de queries
- Se usa `selectRaw` con `SUM`, `COALESCE`, `COUNT(DISTINCT)` para agregar en la base de datos en lugar de iterar en PHP.
- `GenerateCompanyReport` hace un único JOIN entre `time_entries`, `employees`, `users` y `departments` en lugar de cargar colecciones y recorrerlas.
- Se usa `withoutGlobalScopes()` para saltar el `CompanyScope` global dentro de las acciones (necesario porque las acciones reciben `company_id` explícitamente).

### CarbonInterface vs Carbon
- Laravel 12 devuelve `CarbonImmutable` desde `now()`. Se usa `CarbonInterface` como tipo en los parámetros de las acciones para aceptar ambos tipos.

### Zona horaria
- Los registros se almacenan en UTC.
- `CalculateWorkHours` convierte a `America/Bogota` para clasificar los minutos (nocturnas = 21:00–06:00 COT).
- El seeder crea las horas en zona Colombia y las convierte a UTC antes de guardar para que la clasificación sea correcta.

### Exportaciones
- **Excel**: `maatwebsite/excel` v3.1 con `WithMultipleSheets`. Cada sheet es una clase independiente implementando `FromArray + WithHeadings + WithStyles + ShouldAutoSize + WithTitle`.
- **PDF**: `barryvdh/laravel-dompdf` v3.1 con vistas Blade. CSS inline para compatibilidad con DomPDF.
- Los botones de descarga usan `window.location.href` (no Inertia) ya que son respuestas de archivo, no páginas SPA.
- URLs generadas con Wayfinder (`exportEmployeeExcel.url()` + query params manuales).

### Validación de rutas export
`ReportFilterRequest` usa `$this->routeIs('reports.employee', 'reports.employee.*')` para hacer `employee_id` obligatorio tanto en la vista como en las rutas de exportación del empleado.

---

## Estructura de archivos

```
app/
  Domain/TimeTracking/Actions/
    CalculateReportCosts.php
    GenerateEmployeeReport.php
    GenerateCompanyReport.php
  Exports/
    EmployeeReportExport.php     # Excel empleado (2 sheets)
    CompanyReportExport.php      # Excel empresa (3 sheets)
  Http/
    Controllers/
      ReportController.php       # index, employee, company + 4 métodos export
    Requests/
      ReportFilterRequest.php

resources/
  js/pages/Reports/
    Index.vue                    # Hub: selección de tipo de reporte
    Employee.vue                 # Reporte individual con gráficas + export buttons
    Company.vue                  # Reporte empresa con gráficas + export buttons
    partials/
      DateRangeFilter.vue        # Componente reutilizable de filtro de fechas
  views/exports/
    employee-report.blade.php    # Plantilla PDF empleado
    company-report.blade.php     # Plantilla PDF empresa

database/seeders/
  ReportDemoSeeder.php

tests/
  Unit/
    CalculateReportCostsTest.php    # 10 tests
  Feature/
    GenerateEmployeeReportTest.php  # 8 tests
    GenerateCompanyReportTest.php   # 8 tests
    ReportControllerTest.php        # 13 tests
    ReportExportTest.php            # 14 tests
```

---

## Rutas registradas

```
GET /reports                    reports.index
GET /reports/employee           reports.employee
GET /reports/company            reports.company
GET /reports/employee/excel     reports.employee.excel
GET /reports/employee/pdf       reports.employee.pdf
GET /reports/company/excel      reports.company.excel
GET /reports/company/pdf        reports.company.pdf
```

Todas protegidas con middleware `role:admin|super-admin`.

---

## Tests (53 tests en total)

| Archivo | Tests | Qué cubre |
|---------|-------|-----------|
| `CalculateReportCostsTest` | 10 | Unit: recargos por tipo de hora, tasa cero, horas cero, reglas personalizadas |
| `GenerateEmployeeReportTest` | 8 | Agregación, rango de fechas, pausas por tipo, breakdown diario, costos |
| `GenerateCompanyReportTest` | 8 | Multi-empleado, filtro departamento, costos por empleado, asistencia diaria, aislamiento entre empresas |
| `ReportControllerTest` | 13 | Acceso admin/super-admin, empleado prohibido, validación, presets, estado vacío |
| `ReportExportTest` | 14 | Excel/PDF por tipo, control de acceso, validación, filtro departamento, nombre de archivo, datos vacíos |

---

## Dependencias utilizadas

- `maatwebsite/excel` v3.1 (ya instalado)
- `barryvdh/laravel-dompdf` v3.1 (ya instalado)
- `apexcharts` (lazy-loaded via `import('apexcharts')` en Vue para no bloquear carga inicial)
- `lucide-vue-next` para iconos (`Download`, `ArrowLeft`, etc.)
