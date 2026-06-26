## Why

El resumen de costos del reporte individual muestra hoy filas de recargo que no aportan información: cuando una empresa desactiva el pago de un recargo premium (dominical, nocturno dominical, nocturno festivo, extra dominical, extra festivo), la fila igual aparece en `$0`, generando ruido. Además, cuando el modo de pago dominical o festivo es **por día**, el reporte muestra la cantidad de horas, cuando lo que realmente se paga (y se quiere ver) son los **días**.

## What Changes

- Ocultar en el resumen de costos las filas de recargo premium cuyo toggle de pago está desactivado en la configuración de la empresa, ya que esas horas ya se reflejan en las filas base (regular/nocturno/extra). Aplica a: dominical (`pay_dominical_by_default`), nocturno dominical (`pay_night_dominical`), nocturno festivo (`pay_night_holiday`), extra dominical (`pay_overtime_dominical`) y extra festivo (`pay_overtime_holiday`).
- Conservar siempre la fila de festivo diurno (se paga por ley, sin toggle) y nunca ocultar plata real: en modo `hourly` con dominical desactivado las horas se pagan a tarifa ordinaria en su propia fila, que debe seguir visible.
- En modo de pago **por día** (`dominical_mode === 'day'` / `holiday_mode === 'day'`), mostrar en la fila de recargo dominical/festivo la cantidad de **días pagados** (ej. "2 días") en lugar de las horas.
- Aplicar ambos ajustes de forma consistente en la vista (`Reports/Employee.vue`), el export PDF y el export Excel.

## Capabilities

### New Capabilities
- `report-cost-summary-display`: reglas de presentación del resumen de costos del reporte individual — qué filas de recargo se muestran/ocultan según los toggles de la empresa y cómo se expresan las horas vs. días según el modo de pago.

### Modified Capabilities
<!-- Ninguna: no cambia el cálculo de costos ni las reglas de recargo; solo su presentación. -->

## Impact

- **Dominio afectado**: TimeTracking (presentación del reporte). Sin backend nuevo ni cálculo nuevo: los flags (`pay_*`) y los modos (`dominical_mode`, `holiday_mode`) y conteos (`dominical_paid_days`, `holiday_worked_days`) ya vienen en `cost_summary`.
- **Código**:
  - `resources/js/pages/Reports/Employee.vue` — filtrar filas y formatear días vs horas.
  - `resources/views/exports/employee-report.blade.php` — mismas reglas en PDF.
  - `app/Exports/EmployeeReportExport.php` — mismas reglas en Excel.
  - `resources/js/locales/es.json` / `en.json` — etiqueta de días si hace falta.
- **Multi-tenant**: sin cambios; usa datos ya scopeados por `company_id`.
- **Roles**: igual que el reporte actual (admin/super-admin). Sin cambios de autorización.
- **Migración de BD**: NO.

## Non-goals

- Cambiar el cálculo de costos, recargos o el colapso de horas (eso ya existe y se respeta).
- El reporte de empresa y sus exports.
- Préstamos/bonificaciones (van en el change `employee-payroll-adjustments`).
