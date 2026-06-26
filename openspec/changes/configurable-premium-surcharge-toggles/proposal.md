## Why

Algunas empresas no pagan ciertos recargos premium como tales: si un trabajador hace horas nocturnas un domingo, ciertas empresas las pagan como **nocturno normal**, no como nocturno dominical; igual con el nocturno festivo y con las horas extra dominicales/festivas (las pagan como extra normal). Hoy el sistema solo ofrece switches "todo o nada" (`pay_overtime_by_default`, `pay_dominical_by_default`), sin control granular para colapsar un recargo premium hacia su equivalente base.

## What Changes

- **4 switches nuevos por empresa** en `surcharge_rules` (boolean, default `true` = pagar el premium):
  - `pay_night_dominical` — si OFF, las horas `night_dominical` se pagan como `night` (nocturno normal).
  - `pay_night_holiday` — si OFF, las horas `night_holiday` se pagan como `night`.
  - `pay_overtime_dominical` — si OFF, `overtime_day_dominical` → `overtime_day` y `overtime_night_dominical` → `overtime_night`.
  - `pay_overtime_holiday` — si OFF, `overtime_day_holiday` → `overtime_day` y `overtime_night_holiday` → `overtime_night`.
- **El colapso se aplica en `CalculateReportCosts` (cost-time):** las horas premium no pagadas se suman a su bucket base antes de multiplicar — **cero queries nuevas, sin recálculo histórico**. La clasificación (`CalculateWorkHours`) y los 12 buckets de `time_entries` **no se tocan**.
- **`pay_dominical_by_default` pasa a controlar solo el recargo dominical DIURNO.** Hoy, cuando está OFF, colapsa toda la familia dominical (diurno + noche + extra); con este cambio la noche y la extra dominical pasan a sus switches nuevos (independientes). **BREAKING (comportamiento):** para no alterar lo que las empresas pagan hoy, la migración **siembra** `pay_night_dominical` y `pay_overtime_dominical` con el valor actual de `pay_dominical_by_default` de cada empresa.
- **El recargo dominical DIURNO y festivo DIURNO no se tocan** — siguen con su modo por hora / por día tal cual.
- **Display:** cuando un recargo se colapsa, sus horas se suman al renglón base (`night` / `overtime_day` / `overtime_night`) y el renglón premium queda en `0h / $0`, en reporte de empleado, empresa, Excel y PDF.

## Capabilities

### New Capabilities
- `premium-surcharge-toggles`: Capacidad de la empresa de elegir, por recargo premium (nocturno dominical, nocturno festivo, extra dominical, extra festivo), si se paga como premium o se colapsa hacia su recargo base equivalente, aplicado en el cálculo de costos. Incluye el cambio de alcance de `pay_dominical_by_default` (ahora solo el dominical diurno).

### Modified Capabilities
<!-- El spec canónico 8-hour-type-classification está desactualizado (el change configurable-dominical-surcharge
     se mergeó a main pero no se archivó, así que el canónico aún describe 8 tipos). Para no escribir un delta
     contra un canónico obsoleto, todo el comportamiento nuevo de costos vive en la capability nueva.
     PENDIENTE de proceso: archivar configurable-dominical-surcharge para sincronizar los specs canónicos. -->
- (ninguna)

## Impact

- **Dominio afectado:** Company (config en `surcharge_rules`) + TimeTracking (cálculo de costos y presentación de reportes).
- **Backend:**
  - Migración: agregar `pay_night_dominical`, `pay_night_holiday`, `pay_overtime_dominical`, `pay_overtime_holiday` (boolean, default `true`) a `surcharge_rules`; sembrar `pay_night_dominical`/`pay_overtime_dominical` desde `pay_dominical_by_default` para preservar comportamiento.
  - `SurchargeRule` — fillable + casts.
  - `CalculateReportCosts` — colapsar buckets premium no pagados hacia base (noche y overtime); `pay_dominical_by_default` solo afecta el diurno.
  - `UpdateSurchargeRuleRequest` + `Settings/SurchargeRuleController` — aceptar los 4 flags.
- **Frontend:** `SurchargeRules.vue` (4 checkboxes), reportes (`Reports/Employee.vue`, `Reports/Company.vue`) y exports (Excel + Blade PDF) — fundir renglones premium colapsados en su base. i18n.
- **Multi-tenant:** todo vive en `surcharge_rules` (ya `company_id`). Sin tabla nueva.
- **Roles:** configuración y reportes son admin + super-admin (igual que hoy); `employee` no accede.
- **Migración de BD:** Sí — una migración (4 columnas + seed de preservación).

## Non-goals

- No se toca la clasificación de horas ni los 12 buckets de `time_entries`; no hay recálculo histórico.
- No se toca el recargo dominical/festivo **diurno** (modo por hora / por día se mantiene).
- No hay override por empleado ni por reporte; es solo configuración a nivel empresa (sin tabla de decisiones).
- No se cambia `pay_overtime_by_default` (sigue siendo el switch global de compensar horas extra en $0); los nuevos switches solo deciden la **tarifa** del overtime premium cuando sí se paga.
