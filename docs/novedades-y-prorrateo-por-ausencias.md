# Novedades y Prorrateo por Ausencias (Fase 3 — implementado)

> **Estado:** implementado en el cambio OpenSpec `prorrateo-descuentos-novedades`.
> **Depende de:** "Salario base mensual + recargos correctos" (Fase 1+2): columnas
> `employees.monthly_base_salary`, `employees.salary_type` (`hourly`|`monthly`), `hourly_rate`
> editable, defaults de empresa en `surcharge_rules`, y el prorrateo del base por mes comercial de
> 30 días en `CalculatePeriodBaseSalary`.

---

## Decisión de diseño clave (lo que cambió respecto al diseño original)

El diseño original de esta fase asumía **detección automática** de ausencias cruzando
`Schedule.days_of_week`, festivos y `TimeEntry`. Eso se descartó por un hecho operativo: **los
horarios por empleado están deshabilitados** (no se asigna "Juan trabaja L–V"). El kiosk de marcación
sí funciona y genera `TimeEntry` (de ahí salen recargos y horas extra), pero sin horarios el sistema
**no puede saber qué día era esperado vs. descanso**.

Por eso el modelo es **dirigido por el administrador**: en el resumen de la quincena, el admin registra
un descuento (número de días + motivo) y el reporte recalcula al vuelo. La novedad **es** la fuente de
verdad; no hay detección automática, ni cruce con `Schedule` ni con festivos.

---

## Fórmula

Cada día descontado vale `salario_mensual / 30`, **independiente de los días calendario del mes**:

```
base_periodo = max(0, monthly_base_salary × (días_comerciales − días_descontados) / 30)

  días_comerciales : días del rango por mes comercial de 30 días (ya viene de Fase 2;
                     quincena completa = 15, mes completo = 30, día 31 saturado en 30)
  días_descontados : Σ days de las novedades cuya effective_date ∈ [inicio, fin] del reporte
```

Invariantes:
- Sin novedades → idéntico a Fase 2 (febrero y octubre pagan el mismo medio salario).
- 2 faltas en quincena → `base × 13/30`.
- El descuento nunca deja el base negativo (**clamp en 0**); cuando los días descontados superan los
  pagables, el reporte marca el descuento como **topado**.
- Modo `hourly` → las novedades no afectan el cálculo (no hay base que prorratear).
- Recargos y horas extra (de `TimeEntry`) no se ven afectados: el descuento toca **solo** el base.

---

## Modelo de datos

Tabla `payroll_deductions` (`BelongsToCompany` + scope de tenant):

| Columna | Tipo | Notas |
|---|---|---|
| `id` | id | |
| `company_id` | FK | tenant scope |
| `employee_id` | FK | empleado (solo `monthly`) |
| `effective_date` | date | ubica el descuento dentro del rango del reporte |
| `days` | decimal(4,1) | días a descontar (admite medios días); cada día = `salario/30` |
| `reason` | string (enum) | `PayrollDeductionReason` |
| `notes` | text nullable | observaciones del admin |
| `created_by` | FK users nullable | auditoría: quién lo registró |
| timestamps | | |

Índice: `(company_id, employee_id, effective_date)`.

### Motivos (`PayrollDeductionReason`, enum TitleCase)

Todos los motivos de esta fase **descuentan** (son ausencias no remuneradas):

| Motivo | Descripción |
|---|---|
| `FaltaInjustificada` | Falta injustificada |
| `LicenciaNoRemunerada` | Licencia no remunerada |
| `PermisoNoRemunerado` | Permiso no remunerado |
| `Otro` | Otro (usar `notes`) |

---

## Puntos de integración en el código

| Archivo | Cambio |
|---|---|
| `app/Domain/TimeTracking/Models/PayrollDeduction.php` | Nuevo modelo |
| `app/Domain/TimeTracking/Enums/PayrollDeductionReason.php` | Nuevo enum |
| `app/Domain/TimeTracking/Actions/CalculatePeriodBaseSalary.php` | Acepta `float $deductedDays = 0`; resta del numerador comercial con clamp en 0 (sigue puro) |
| `app/Domain/TimeTracking/Actions/CalculateReportCosts.php` | Sin cambios: recibe el base ya ajustado |
| `app/Domain/TimeTracking/Actions/GenerateEmployeeReport.php` | Suma `days` del periodo, pasa `deductedDays` al base, expone `deductions` (días, monto, `capped`, items) |
| `app/Domain/TimeTracking/Actions/GenerateCompanyReport.php` | `SUM(days)` por empleado en una query; resta en cada base; agrega `deductions` al desglose y a los totales |
| `app/Domain/TimeTracking/Actions/CreatePayrollDeduction.php` / `DeletePayrollDeduction.php` | Registrar / eliminar |
| `app/Http/Controllers/PayrollDeductionController.php` | `store` / `destroy` delgados (rol `admin`/`super-admin`) |
| `app/Http/Requests/StorePayrollDeductionRequest.php` | Validación (employee `monthly`, scope de company, `days ≥ 0.5`) |
| `resources/js/pages/Reports/Employee.vue` | UI: línea de descuento en el resumen + card para agregar/eliminar (solo `monthly`) |

---

## Fuera de alcance (posibles fases futuras)

- Detección automática de días esperados vía `Schedule` / festivos (depende de reactivar horarios).
- Ausencias **pagadas** informativas (vacaciones, incapacidad, licencia remunerada) en el reporte.
- Subsidio de incapacidad EPS/ARL (66.67%, días de carencia).
- Descansos compensatorios por trabajo dominical.
- Cierre / bloqueo de un periodo de nómina ya liquidado (hoy basta la auditoría con `created_by`/timestamps).
