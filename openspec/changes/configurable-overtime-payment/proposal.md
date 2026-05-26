## Why

En Colombia es común que las horas extra no se paguen en dinero, sino que se compensen con tiempo libre o días de descanso. Hoy la app siempre calcula y suma el costo de las 4 categorías de hora extra al total del reporte, sin posibilidad de excluirlas. El usuario necesita poder generar un desprendible donde las horas extra **se muestren** (cuántas se trabajaron) pero **se paguen en $0** y no sumen al total.

## What Changes

- Nueva columna `pay_overtime_by_default` (boolean, default `true`) en `surcharge_rules`: define el criterio general de la compañía sobre si pagar horas extra. Editable en la pantalla de ajustes de recargos.
- `CalculateReportCosts` recibe un flag `payOvertime`. Cuando es `false`, los 4 subtotales de overtime (`overtime_day`, `overtime_night`, `overtime_day_sunday`, `overtime_night_sunday`) se calculan en `$0`, se excluyen del `total`, y cada uno se marca como `compensated: true` en `details[]`. **Las horas trabajadas no se modifican** y siguen visibles.
- Switch "Pagar horas extra" en el reporte de empleado (override por empleado) y en el reporte de empresa (override global, independiente de los empleados). Se precarga desde la decisión guardada del periodo o, si no hay, desde `pay_overtime_by_default`.
- Nueva tabla `overtime_payment_decisions` que registra qué se eligió: `(company_id, employee_id nullable, start_date, end_date, pay_overtime, exported_by, exported_at)`. `employee_id` lleno = decisión de un empleado; `NULL` = decisión del reporte de empresa.
- La decisión se persiste (upsert, gana la última) **al exportar** el reporte a PDF o Excel. Ver el reporte en pantalla no guarda nada.
- Las vistas (pantalla, Excel, PDF) muestran las horas extra con costo `$0` y la etiqueta "Compensado con tiempo" cuando no se pagan.

## Capabilities

### New Capabilities
- `overtime-payment-toggle`: Capacidad de elegir, a nivel de compañía y por reporte, si las horas extra se pagan en dinero o se compensan con tiempo (costo $0 pero horas visibles), incluyendo la persistencia de la decisión al exportar.

### Modified Capabilities
<!-- No hay specs existentes para generación de reportes/costos; toda la conducta nueva vive en la capability nueva. -->

## Impact

- **Dominio afectado:** Company (config y nueva tabla de decisiones) + TimeTracking (cálculo de costos y reportes).
- **Backend:**
  - Migración 1: agregar `pay_overtime_by_default` a `surcharge_rules`.
  - Migración 2: crear tabla `overtime_payment_decisions`.
  - Modelos: `SurchargeRule` (nuevo fillable/cast), nuevo modelo `OvertimePaymentDecision` con `BelongsToCompany`.
  - `CalculateReportCosts::execute()` — nuevo parámetro `bool $payOvertime`.
  - `GenerateEmployeeReport` y `GenerateCompanyReport` — propagar el flag.
  - `ReportController` — resolver el flag (request → decisión guardada → default), persistir al exportar, exponer el valor a las vistas.
  - `Settings/SurchargeRuleController` + `UpdateSurchargeRuleRequest` — aceptar `pay_overtime_by_default`.
  - `ReportFilterRequest` — aceptar `pay_overtime` opcional.
- **Frontend:** `SurchargeRules.vue` (toggle de default), `Reports/Employee.vue` y `Reports/Company.vue` (switch + UI de "Compensado"), plantillas Blade de export PDF y clases de Excel export. i18n en `es.json`/`en.json`.
- **Multi-tenant:** ambas tablas llevan `company_id`; `OvertimePaymentDecision` usa `BelongsToCompany`. `super-admin` (company_id=null) no aplica a estos flujos por compañía.
- **Roles:** la configuración y los reportes son admin + super-admin (igual que los reportes actuales); `employee` no accede.
- **Migración de BD:** Sí — dos migraciones (alterar `surcharge_rules` + crear `overtime_payment_decisions`).

## Non-goals

- No se crea un "desprendible congelado" (snapshot inmutable del reporte). El registro es ligero: solo guarda la decisión; las horas y montos se recalculan desde los `TimeEntry` en cada generación.
- No hay control por cada tipo de hora extra: un único switch cubre las 4 categorías.
- No se modifica `CalculateWorkHours` ni la clasificación de horas; las horas extra se siguen calculando y almacenando igual.
- No se cambia el modelo de roles ni el acceso a reportes.
