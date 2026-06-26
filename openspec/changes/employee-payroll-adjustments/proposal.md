## Why

En la operación real de la empresa se hacen préstamos/adelantos a los empleados (una deuda que se descuenta de su pago) y se otorgan bonificaciones puntuales. Hoy el reporte individual termina en el "neto a pagar" (después de salud y pensión) y no permite reflejar estos ajustes, por lo que el valor final que efectivamente se le entrega al empleado no queda registrado en el sistema.

## What Changes

- Crear un módulo de **ajustes de nómina** por empleado: un CRUD donde el admin registra bonificaciones (suman) y préstamos/adelantos/descuentos (restan), cada uno con fecha, concepto y monto.
- Aplicar los ajustes en el reporte individual **después del neto a pagar**, sin afectar la base de seguridad social (salud/pensión): `final_pay = net_pay + Σ bonificaciones − Σ deducciones`.
- Sumar al reporte solo los ajustes cuya `date` cae dentro del periodo consultado.
- Exponer en `cost_summary` los nuevos campos (`bonus_total`, `deduction_total`, `final_pay`) y el detalle de ajustes, y mostrarlos en la vista, el PDF y el Excel del reporte individual.

Alcance acordado: **monto manual por periodo** (sin seguimiento de saldo acumulado ni cuotas automáticas) y **módulo dedicado** por empleado.

## Capabilities

### New Capabilities
- `employee-payroll-adjustments`: registro (CRUD) de ajustes de nómina por empleado — bonificaciones y deducciones (préstamos/adelantos) — y su aplicación en el reporte individual después del neto a pagar.

### Modified Capabilities
<!-- Ninguna spec existente cambia sus requisitos; la deducción de seguridad social (net_pay) se conserva tal cual y este change añade sobre ella. -->

## Impact

- **Dominio afectado**: Employee (gestión de los ajustes del empleado) + TimeTracking (consumo en el reporte).
- **Migración de BD**: SÍ. Nueva tabla `employee_adjustments` con `company_id`, `employee_id`, `date`, `type` (`Bonus`|`Deduction`), `amount` (decimal 12,2), `concept` (string), `note` (text nullable), `created_by` (FK users nullable), timestamps. Índice `(company_id, employee_id, date)`. Actualizar `ai-specs/specs/data-model.md`.
- **Código**:
  - Modelo `EmployeeAdjustment` (trait `BelongsToCompany`) + factory + enum de tipo.
  - Action(s) en `App\Domain\Employee\Actions` para crear/actualizar/eliminar ajustes.
  - Form Request con reglas (monto positivo, tipo válido, fecha) y mensajes.
  - Controller delgado + rutas (CRUD anidado al empleado) + `wayfinder:generate`.
  - `GenerateEmployeeReport` agrega los ajustes del periodo; `CalculateReportCosts` (o el report) calcula `final_pay`.
  - Vista del empleado (gestión de ajustes) + `Reports/Employee.vue` (filas de ajustes y total final) + PDF + Excel.
  - i18n `es.json` / `en.json`.
- **Multi-tenant**: la tabla lleva `company_id` y scope de tenant; un admin solo gestiona ajustes de empleados de su empresa (cross-company rechazado).
- **Roles**: `admin` (su empresa) y `super-admin` gestionan ajustes; `employee` no.

## Non-goals

- Seguimiento de saldo de préstamos ni cuotas automáticas multi-quincena (se eligió monto manual por periodo).
- Ausencias/novedades de prorrateo del salario base (`docs/novedades-y-prorrateo-por-ausencias.md`, Fase 3) — fuera de alcance.
- Reporte de empresa y sus exports.
- Recalcular o afectar la base de salud/pensión con estos ajustes (van estrictamente después del neto).
