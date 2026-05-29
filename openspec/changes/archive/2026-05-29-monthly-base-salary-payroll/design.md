## Context

El motor de costo actual (`app/Domain/TimeTracking/Actions/CalculateReportCosts.php`) liquida los 8 buckets de hora con la misma fórmula `horas × hourly_rate × (1 + recargo%)`, donde `regular` tiene recargo 0%. Esto modela bien a un empleado por horas, pero es incorrecto para el caso colombiano más común: el salario base mensual fijo, donde la hora ordinaria ya está incluida en el salario y no se paga por hora.

La clasificación de horas (`CalculateWorkHours`) y los porcentajes de recargo por empresa (`SurchargeRule`) ya existen y son correctos. El campo `employees.salary_type` (`hourly`|`monthly`) ya existe en BD (default `hourly`) aunque fue retirado del formulario. Los reportes se generan sobre un rango de fechas libre (`GenerateEmployeeReport`/`GenerateCompanyReport` + `DateRangeFilter.vue`).

Constraints del proyecto: multi-tenant con `company_id` y `BelongsToCompany`; arquitectura de dominios con lógica en Actions; controllers delgados con Form Requests; tests PHPUnit por rol; timezone `America/Bogota`.

## Goals / Non-Goals

**Goals:**
- Soportar dos modos de salario por empleado (`monthly`, `hourly`) coexistiendo, sin romper a los empleados actuales (`hourly`).
- En modo `monthly`: absorber las horas ordinarias en el salario base, sumar solo el porcentaje en los recargos, sumar el valor completo en las horas extra, y agregar el salario base prorrateado del periodo.
- Resolver el problema febrero/octubre fijando el denominador en mes comercial de 30 días (15 por quincena).
- Defaults de salario por compañía sembrados con el SMLV y editables, siguiendo el patrón de `SurchargeRule`.
- Periodos de pago (quincena/mes) además del rango libre en reportes.

**Non-Goals:**
- Prorrateo por ausencias y modelo de novedades (fase posterior — `docs/novedades-y-prorrateo-por-ausencias.md`).
- Subsidio de incapacidad, deducciones de seguridad social, prestaciones sociales.
- Actualización automática anual del SMLV.

## Decisions

### 1. Ramificar `CalculateReportCosts` por `salary_type` en lugar de crear una Action nueva
La firma actual es `execute(float $hourlyRate, array $hourTotals, SurchargeRule $rules, bool $payOvertime)`. Se extiende para recibir el contexto de salario (modo + base prorrateado del periodo) y ramificar internamente.

- **Por qué**: mantiene un solo punto de cálculo de costo, reutiliza el manejo existente del flag `payOvertime` (que debe seguir aplicando en ambos modos), y evita duplicar la lógica de las 4 categorías de overtime que no cambian.
- **Alternativa considerada**: una Action separada `CalculateMonthlySalaryCost`. Descartada: duplicaría la rama de overtime y el flag `payOvertime`, y obligaría a los generadores de reporte a elegir Action, dispersando la lógica.
- **Forma de la firma**: pasar el modo y el `baseAmount` ya prorrateado (calculado por el generador de reporte, que es quien conoce el periodo). `CalculateReportCosts` permanece puro: no consulta fechas ni BD, solo aplica fórmulas. El prorrateo (que sí depende del periodo) vive en el generador.

### 2. Recargos = solo el porcentaje; overtime = valor completo; regular = 0 (modo monthly)
Es el cambio matemático central, alineado con la ley (la hora base ya está en el salario):
- `night`/`sunday_holiday`/`night_sunday`: `horas × valor_hora × (%/100)`.
- `overtime_*` (4): `horas × valor_hora × (1 + %/100)` — idéntico a hoy.
- `regular`: subtotal 0; las horas se conservan en `totals` (informativas).
- `valor_hora` = `hourly_rate` del empleado (editable, default por empresa).

### 3. Defaults de salario en `surcharge_rules`, no en `companies`
`surcharge_rules` ya es la tabla de configuración de nómina por empresa (única por `company_id`, sembrada en `CompanyObserver`, editada en `settings/SurchargeRules.vue`). Se agregan `default_monthly_salary` y `default_hourly_rate`.

- **Por qué**: reusa el patrón, el seeding y la UI existentes; el admin ya gestiona ahí los porcentajes.
- **Seeding**: `default_monthly_salary` = SMLV (valor en `config/payroll.php` o equivalente, editable por super-admin a futuro); `default_hourly_rate` = `default_monthly_salary / 220` (divisor de jornada 42h). El divisor 220 solo aplica al **sembrar**; luego ambos campos son editables independientemente.
- **SMLV como config**: vive en configuración de la app (no `env()` directo en código), aplicado como default al crear empresa. Las empresas existentes lo reciben vía migración.

### 4. Prorrateo del base en el generador de reporte, con mes comercial de 30 días
`GenerateEmployeeReport`/`GenerateCompanyReport` resuelven el periodo (preset o rango) y calculan:
`base_periodo = (monthly_base_salary / divisor) × (días_pagables / días_base)`, con `divisor`/`días_base` = 2/15 (quincena) o 1/30 (mes), y `días_pagables` = días del rango acotados (sin ausencias en esta fase).

- **Por qué denominador fijo**: es lo que resuelve febrero vs octubre — el calendario real no entra; solo el rango pagable contra 15/30.
- **Quién infiere el periodo**: el controller/Form Request recibe el preset o el rango; el generador traduce a `startDate`/`endDate` y a `(divisor, días_base, días_pagables)`.

### 5. Empleados existentes y compatibilidad
- Migración: `employees.monthly_base_salary` nullable; empleados actuales quedan `salary_type = hourly` (sin cambio de costo).
- `surcharge_rules`: nuevas columnas con el SMLV vía `down()`/`up()` seguro para datos existentes.
- El flag `payOvertime` y los specs `8-hour-type-classification`, `overtime-payment-toggle`, `overtime-daily-limit` no cambian sus requirements.

## Risks / Trade-offs

- **[Divisor 220 es debatible legalmente]** → Solo se usa como semilla del default; el admin puede editar el valor hora. Documentado como decisión y editable, no hardcodeado en el cálculo.
- **[Inconsistencia entre `monthly_base_salary` y `hourly_rate`]** (el admin puede dejarlos no proporcionales) → Es por diseño (ambos editables). Los recargos son deltas sobre el valor hora; no se reconcilian con el base. Aceptado.
- **[Rango libre que no calza con quincena/mes]** → El prorrateo usa `días_pagables/días_base` con `días_base` del periodo elegido; para rango libre puro se define `días_base` = 30 (mensual) y `días_pagables` = días del rango. Confirmar UX para que el admin entienda el monto resultante.
- **[Doble lógica de costo monthly/hourly]** → Más superficie de test, mitigada con casos explícitos por modo en `CalculateReportCostsTest`.
- **[Reportes de empresa con empleados mixtos]** → El agregado debe sumar correctamente costos de empleados `monthly` y `hourly` juntos; cubrir con test específico.

## Migration Plan

1. Migración: agregar `employees.monthly_base_salary` (decimal 10,2 nullable) y `surcharge_rules.default_monthly_salary`, `default_hourly_rate` (decimal 10,2) con default = SMLV / (SMLV÷220). Actualizar `ai-specs/specs/data-model.md`.
2. Config del SMLV vigente (`config/payroll.php` o similar).
3. Modelo/casts (`Employee`, `SurchargeRule`), `CompanyObserver` (sembrar defaults).
4. `CalculateReportCosts` (rama monthly), generadores de reporte (periodo + base prorrateado).
5. Form Requests (empleado: `salary_type`, `monthly_base_salary`, `hourly_rate`; `UpdateSurchargeRuleRequest`: defaults).
6. Frontend (`EmployeeForm`, `DateRangeFilter`, `Reports/Employee`, `Reports/Company`, `settings/SurchargeRules`) + `wayfinder:generate` + `npm run build`.
7. Exports.
8. Tests + `pint`.

**Rollback**: las columnas nuevas son aditivas y nullable/with-default; revertir la migración no afecta a empleados `hourly`. El comportamiento de costo legacy queda intacto mientras `salary_type = hourly`.

## Open Questions

- ¿Para rango libre puro (no quincena/mes), `días_base` = 30 fijo es la regla deseada, o se infiere del mes del rango? (Asumido: 30 mensual.)
- ¿El SMLV vive en `config/payroll.php` editable por deploy, o conviene una tabla/ajuste de super-admin desde ya? (Asumido: config por ahora; tabla queda para después.)
