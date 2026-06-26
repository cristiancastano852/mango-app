## Context

El reporte individual (`GenerateEmployeeReport` → `CalculateReportCosts`) hoy termina en `net_pay` (total devengado − salud − pensión). No existe forma de registrar préstamos ni bonificaciones. El reporte se genera sobre un rango/quincena sin entidad propia: las horas vienen de `time_entries`. Por tanto los ajustes necesitan su propia tabla, keyed por empleado y fecha, y el reporte los suma por periodo.

Existe el patrón `BelongsToCompany` + global scope de tenant para todos los modelos, y un diseño documentado de "novedades" (`docs/novedades-y-prorrateo-por-ausencias.md`) del que estos ajustes son la variante monetaria.

## Goals / Non-Goals

**Goals:**
- Tabla y CRUD de ajustes (`Bonus`/`Deduction`) por empleado, tenant-scoped.
- Aplicar ajustes del periodo después del neto: `final_pay = net_pay + bonus_total − deduction_total`.
- Mostrar en vista + PDF + Excel.

**Non-Goals:**
- Saldo de préstamos / cuotas automáticas.
- Ausencias y prorrateo del base (Fase 3).
- Reporte de empresa.

## Decisions

**1. Tabla `employee_adjustments`.** Columnas: `company_id`, `employee_id`, `date`, `type` (`Bonus`|`Deduction`), `amount` (decimal 12,2, positivo), `concept` (string), `note` (text nullable), `created_by` (FK users nullable), timestamps. Índice `(company_id, employee_id, date)`. El signo no se persiste; lo determina `type` al sumar.

**2. Enum de tipo.** `App\Domain\Employee\Enums\AdjustmentType` con casos `Bonus` y `Deduction` (string-backed, TitleCase). El modelo castea `type` al enum.

**3. Suma a nivel de BD en el reporte.** `GenerateEmployeeReport` agrega los ajustes del periodo con una sola query agregada (`SUM(CASE WHEN type='Bonus' ...)`), siguiendo el patrón de `aggregateTotals`. Pasa `bonus_total`/`deduction_total` (y el detalle para listarlos) al cálculo. Evita iterar en PHP para los totales; el detalle sí se trae como lista para mostrarlo.

**4. `final_pay` se calcula sobre `net_pay`, no sobre `total`.** Los ajustes van estrictamente después del neto y NO tocan `social_security_base` ni las deducciones. `CalculateReportCosts` recibe `bonus_total`/`deduction_total` y añade `final_pay = round(net_pay + bonus_total − deduction_total, 2)` y los totales al `cost_summary`. Mantiene una sola fuente de verdad del cálculo.

**5. CRUD anidado al empleado.** Rutas tipo `employees/{employee}/adjustments` (index/store/update/destroy), controller delgado + Form Request. Autorización: tenant scope + rol admin/super-admin; cross-company rechazado vía `assertSessionHasErrors` (no 404), consistente con el resto.

**6. Presentación.** Debajo del "Neto a pagar" se listan las bonificaciones (concepto + monto, en verde/+) y deducciones (concepto + monto, en rojo/−), y una fila final "Total a pagar" con `final_pay`. Mismas filas en PDF y Excel. Nuevas claves i18n.

## Risks / Trade-offs

- **Periodo por `date` del ajuste**: si el admin pone una fecha fuera de la quincena, el ajuste no aparece donde esperaba. Mitigado mostrando claramente la fecha en el CRUD y sumando solo por rango; es el comportamiento esperado y simple.
- **Sin saldo de préstamo**: registrar la misma deuda en varias quincenas es manual. Aceptado explícitamente (alcance elegido); deja la puerta abierta a una fase futura de saldos.
- **final_pay sobre net_pay**: hay que asegurar el orden de cálculo (net_pay ya resuelto antes de aplicar ajustes). Cubierto por tests unit de `CalculateReportCosts` y feature de `GenerateEmployeeReport`.
- **Triple presentación (vista/PDF/Excel)**: misma mitigación que cambios previos — tests de export verifican las filas.
