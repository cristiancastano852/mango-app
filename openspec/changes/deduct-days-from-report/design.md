## Context

El reporte de empleado (`ReportController::employee`) ya maneja tres "decisiones de periodo" con un patrón consolidado: un filtro llega por URL, una acción `Resolve*` calcula el valor efectivo (precedencia request → decisión guardada → default), ese valor entra a `CalculateReportCosts`, y al **exportar** se persiste en una tabla de decisión con `BelongsToCompany`. Los ejemplos vivos son `dominical_payable_count` (`DominicalPaymentDecision`) y `overtime_payable_hours` (`OvertimePaymentDecision`).

`CalculateReportCosts` ya calcula un `final_pay = net_pay + bonus_total − deduction_total` a partir de `EmployeeAdjustment` (bonos/deducciones de monto fijo). El campo `employees.normal_day_value` (decimal:2) ya existe y se usa para el recargo dominical/festivo en modo por-día.

Existe un diseño completo de novedades/ausencias en `docs/novedades-y-prorrateo-por-ausencias.md` (Fase 3, no implementado) que reduce el base pre-IBC. Este cambio es deliberadamente la **versión manual/express**: un solo input de días, sin CRUD de novedades ni detección de días esperados.

## Goals / Non-Goals

**Goals:**
- Reutilizar el patrón de decisión por periodo para un cuarto input `deducted_days`.
- Restar `deducted_days × normal_day_value` como línea plana **después del neto**, sin tocar el IBC.
- Persistir al exportar, igual que las otras decisiones.
- Mostrar la línea de descuento en pantalla, PDF y Excel.

**Non-Goals:**
- Reducir el base o el IBC (queda para la Fase 3 de novedades completa).
- Distinguir ausencia justificada/injustificada ni detectar días esperados por horario.
- Días fraccionados.
- Aplicar el descuento al reporte de empresa.

## Decisions

### Resta plana post-neto (no reduce IBC)
`final_pay = net_pay + bonus_total − deduction_total − day_deduction`, con `day_deduction = max(0, deducted_days) × normal_day_value`.
- **Por qué:** coincide con la petición literal ("se descuenta del total a pagar"), es simétrico al mecanismo de deducciones existente y funciona igual para `monthly` y `hourly` sin lógica condicional por tipo de salario.
- **Alternativa descartada:** reducir el base pre-IBC (correcto legalmente para ausencias). Se descarta por complejidad y porque solo aplica limpio a `monthly`; se reserva para el sistema completo de novedades.

### Valor por día = `employees.normal_day_value`
- **Por qué:** campo ya existente, editable por empleado, válido para cualquier `salary_type`. Evita una nueva columna y evita ramificar por `monthly` (base/30) vs `hourly`.
- **Alternativa descartada:** `monthly_base_salary / 30` — solo sirve para `monthly`; input manual de valor — más fricción para el admin.

### Nueva tabla `day_deduction_decisions` + acción `ResolveDayDeductionDecision`
Estructura espejo de `dominical_payment_decisions`: `id`, `company_id`, `employee_id`, `start_date`, `end_date`, `deducted_days` (int, default 0), `exported_by` (nullable), `exported_at` (nullable), timestamps. Índice/único por `(company_id, employee_id, start_date, end_date)`.
- **Por qué tabla propia:** `deducted_days` no encaja semánticamente en `overtime_payment_decisions` ni en `dominical_payment_decisions`; una tabla dedicada mantiene cada decisión autocontenida y coherente con el patrón.
- **Alternativa descartada:** crear un `EmployeeAdjustment` de tipo `Deduction` al exportar — mezclaría una decisión de periodo (viva, recalculable) con la lista de ajustes manuales y duplicaría el monto ya expresado en días.

### Modelo de datos y flujo
```
URL ?deducted_days=2
   │
   ├─ ReportFilterRequest         nullable|integer|min:0
   │
   ├─ ResolveDayDeductionDecision request → decisión guardada → 0
   │
   ├─ GenerateEmployeeReport       lee employee.normal_day_value
   │        └─ CalculateReportCosts(deductedDays, normalDayValue)
   │                 day_deduction = deductedDays × normalDayValue
   │                 final_pay    −= day_deduction
   │
   └─ export (PDF/Excel) → upsert en day_deduction_decisions
```

### Controller delgado, lógica en Actions
`ReportController` gana `requestDeductedDays()`, y un `persistEmployeeDayDeduction()` análogo a `persistEmployeeDominicalDecision()`. La resolución vive en `ResolveDayDeductionDecision` (dominio TimeTracking). El cálculo vive en `CalculateReportCosts`.

## Risks / Trade-offs

- **`final_pay` negativo** cuando `deducted_days × normal_day_value > net_pay` → No pisar a 0 en el cálculo (se muestra el valor real); la vista SHALL mostrar una advertencia cuando `final_pay ≤ 0`. Decisión menor confirmable en implementación.
- **`normal_day_value` no cargado (= 0)** → el descuento sale $0 silenciosamente. Mitigación: el preview del input muestra el monto calculado, dejando evidente que es $0; opcionalmente avisar.
- **Confusión con las Deducciones existentes** (`EmployeeAdjustment`) → mitigar con etiqueta/columna clara "Descuento por días" separada de "Deducciones" en el resumen de costo.
- **Multi-tenant:** la nueva tabla debe respetar `BelongsToCompany`; en `super-admin` (`company_id = null`) no aplicar filtro `where('company_id')` (consistente con `employeeCompanyId()` que usa `withoutGlobalScopes`).
- **No reduce IBC:** el empleado paga seguridad social sobre días no trabajados; es una desviación conocida y aceptada frente al cálculo legal estricto (documentada en Non-Goals).
