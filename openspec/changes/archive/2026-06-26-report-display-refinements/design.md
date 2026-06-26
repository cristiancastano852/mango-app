## Context

El resumen de costos del reporte individual se renderiza iterando `cost_summary.details` (12 tipos de hora) en tres consumidores: `Reports/Employee.vue`, `resources/views/exports/employee-report.blade.php` y `EmployeeReportExport`. El `cost_summary` ya incluye todo lo necesario: los flags `pay_dominical`, `pay_night_dominical`, `pay_night_holiday`, `pay_overtime_dominical`, `pay_overtime_holiday`, `salary_type`, los modos `dominical_mode`/`holiday_mode` y los conteos `dominical_paid_days`/`holiday_worked_days`. No falta dato; falta lógica de presentación.

Hoy la lógica de colapso del backend funde las horas de un recargo premium desactivado en su fila base (nocturno/extra/regular) y deja la fila premium en 0h/$0, que igual se renderiza. La excepción es el dominical en modo `hourly` desactivado: NO se colapsa, sino que se paga a tarifa ordinaria en su propia fila.

## Goals / Non-Goals

**Goals:**
- Ocultar las filas premium desactivadas sin perder información (las horas ya están en las filas base).
- Mostrar días en vez de horas cuando el pago es por día.
- Mantener vista, PDF y Excel consistentes.

**Non-Goals:**
- Tocar el cálculo de costos o el colapso (backend intacto).
- Reporte de empresa.

## Decisions

**1. Regla única de visibilidad de fila premium.** Una fila premium se oculta cuando su flag de pago está OFF, con dos salvaguardas: (a) `holiday` (festivo diurno) nunca se oculta; (b) `dominical` solo se oculta si NO representa pago real — es decir, se oculta cuando `dominical_mode === 'day'`-colapsado o cuando el costo es 0; en modo `hourly` con dominical OFF la fila muestra horas a tarifa ordinaria y permanece. En la práctica el criterio operativo más simple y seguro es: **ocultar la fila premium si su flag está OFF y su subtotal es 0 y sus horas mostradas son 0**. Esto cubre night_dominical/night_holiday/overtime_* colapsados (0h/$0) y el dominical monthly OFF ($0), pero conserva el dominical hourly OFF (subtotal > 0).

**2. Helper de visibilidad compartido conceptualmente.** En Vue se implementa con un `computed visibleDetails` que filtra `details`. En Blade/Excel se replica la misma condición por fila. Como la fuente (`cost_summary`) es la misma, las tres vistas coinciden.

**3. Formato días vs horas en la celda de "horas".** Para las filas `dominical` y `holiday`, si el modo correspondiente es `day`, la celda muestra `{n} día(s)` usando `dominical_paid_days` / `holiday_worked_days`; si es `hour`, muestra las horas como hoy. Se añade una clave i18n `reports.costs.days_count` con pluralización.

**4. Sin cambios de backend.** Toda la lógica vive en la capa de presentación. Esto mantiene el riesgo bajo y no requiere migración ni tests de dominio nuevos; la verificación es por tests de export (Excel rows + HTML del PDF) que ya existen como patrón.

## Risks / Trade-offs

- **Criterio "subtotal 0 → ocultar"**: una fila premium legítimamente en 0h por ausencia de horas (no por toggle) también se ocultaría — pero eso ya es deseable (no aporta nada mostrar un recargo sin horas). Riesgo bajo y alineado con la intención.
- **Triple replicación de la condición**: la misma regla se escribe en Vue, Blade y Excel. Mitigado por tests de export que verifican presencia/ausencia de filas; si divergen, fallan.
- **Pluralización día/días**: se cubre con la clave i18n; cuidar singular (1 día) vs plural (N días) en es y en.
