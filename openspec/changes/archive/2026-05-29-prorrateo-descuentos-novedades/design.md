## Context

La Fase 1+2 (capabilities `monthly-base-salary`, `monthly-salary-cost-calculation`, `payroll-pay-period`) ya implementó el salario base mensual y su prorrateo por **mes comercial de 30 días** en `CalculatePeriodBaseSalary`. Hoy el prorrateo asume que todos los días del periodo son pagables.

Restricción clave del contexto real: los **horarios por empleado (`Schedule.days_of_week`) están deshabilitados** — no se asigna "Juan trabaja L–V". El kiosk de marcación sí funciona y genera `TimeEntry` (de ahí salen recargos y horas extra), pero sin horarios el sistema **no puede deducir** qué día era esperado vs. descanso. Por tanto, la detección automática de ausencias que planteaba `docs/novedades-y-prorrateo-por-ausencias.md` no es viable: el descuento debe ser **aseverado por el administrador**.

La aritmética del base y los costos vive en `app/Domain/TimeTracking/Actions/`: `CalculatePeriodBaseSalary`, `CalculateReportCosts` (puro), `GenerateEmployeeReport`, `GenerateCompanyReport`. Los reportes operan por rango `[start, end]` (los presets de quincena resuelven a un rango); no existe una entidad "periodo de pago" con identidad propia. Sí existe precedente de "decisión amarrada a un periodo": `OvertimePaymentDecision`.

## Goals / Non-Goals

**Goals:**
- Permitir al administrador registrar descuentos por novedad (días + motivo) sobre el resumen de quincena, con recálculo al vuelo.
- Cada día descontado vale `salario_mensual / 30`, preservando el invariante febrero = octubre.
- Mantener `CalculatePeriodBaseSalary` y `CalculateReportCosts` puros (sin acceso a BD de novedades).
- Auditar quién registró cada descuento.

**Non-Goals:**
- Detección automática de días esperados (horarios deshabilitados).
- Ausencias pagadas informativas (vacaciones/incapacidad), subsidio EPS/ARL, compensatorios, cierre de nómina → fases futuras.
- Tocar recargos u horas extra.

## Decisions

### 1. Modelo de datos: `payroll_deductions` (no `employee_absences`)
El alcance es **solo descuentos**, así que el nombre refleja la intención. Columnas: `company_id`, `employee_id`, `effective_date` (date), `days` (decimal 4,1 — permite medios días), `reason` (string/enum), `notes` (text nullable), `created_by` (FK users nullable), timestamps. Índice `(company_id, employee_id, effective_date)`. Trait `BelongsToCompany`.

- **`effective_date` (un solo día) en vez de rango** — el reporte resta los descuentos cuyo `effective_date ∈ [start, end]`. Compone con presets y rangos libres sin inventar una entidad de periodo, y es más simple que el `(start_date, end_date)` de `OvertimePaymentDecision` (que es frágil con rangos solapados). `days` es la magnitud del descuento, no un conteo de fechas; un descuento de 2 días lleva una sola `effective_date`.
- **Alternativa descartada:** reutilizar el patrón `(start_date, end_date)` y contar días — requeriría filtrar domingos/descansos que no podemos conocer sin horarios.

### 2. `reason` como enum corto, TitleCase
`FaltaInjustificada`, `LicenciaNoRemunerada`, `PermisoNoRemunerado`, `Otro`. Enum PHP respaldado por string. Mejor para reportes e i18n que texto libre; `Otro` + `notes` cubre el resto. Todos descuentan (no se persiste `is_paid`: en esta fase todo descuento es no remunerado por definición).

### 3. Cálculo al vuelo, base puro
`GenerateEmployeeReport`/`GenerateCompanyReport` suman los `days` del periodo y los pasan como un nuevo parámetro a `CalculatePeriodBaseSalary` (p. ej. `deductedDays`), que devuelve `salario × (díasComerciales − díasDescontados) / 30`, **clamp en 0**. `CalculateReportCosts` sigue recibiendo el `baseSalary` ya ajustado. Ninguna de las dos Actions consulta la BD de novedades.

- **Clamp en 0** — si el descuento supera el base prorrateado, base = 0; se expone un flag/indicador "descuento topado". Sin deuda negativa arrastrada.

### 4. Company report sin romper "todo en BD"
El conteo por empleado es un `SUM(days)` agrupado por `employee_id` con `effective_date` en el rango — una query barata adicional, mapeada por `employee_id`. No se introduce loop de calendario por empleado.

### 5. UI en el resumen de quincena
Acción "agregar descuento" dentro de `Reports/Employee.vue` (Form de Inertia → `store`), y borrar (`destroy`). El reporte recarga y recalcula. Solo visible para empleados `monthly`. Controller delgado (`PayrollDeductionController` con `store`/`destroy`) + Form Request; rutas con middleware de rol `admin`/`super-admin`.

## Risks / Trade-offs

- **Doble registro accidental** (admin agrega "2 días" dos veces) → los dos cuentan. Mitigación: lista visible de descuentos del periodo con opción de borrar; sin dedup automático (decisión consciente).
- **Edición tardía cambia un reporte ya pagado** (no hay cierre de nómina) → Mitigación: auditoría con `created_by` + timestamps; el cierre/bloqueo de periodo queda como fase futura.
- **`days` fraccionarios** (medio día) → decimal(4,1) y la fórmula los soporta naturalmente al ser `× salario/30`.
- **Modo `hourly`** podría recibir un descuento por error vía API → Mitigación: el Form Request valida que el empleado sea `monthly`; la UI no ofrece la acción para `hourly`.
- **Cross-company** → `effective_date`/`employee_id` validados con `Rule::exists()->where('company_id', ...)` condicional al tenant; `assertSessionHasErrors` en tests.

## Migration Plan

1. Migración `create_payroll_deductions_table`; actualizar `ai-specs/specs/data-model.md` y `domain-model.md`.
2. Desplegar backend (modelo, action, reportes) — retrocompatible: sin filas de descuento, los reportes dan idéntico a hoy.
3. Desplegar UI. Rollback: revertir migración (drop table) y código; no hay datos previos que migrar.
