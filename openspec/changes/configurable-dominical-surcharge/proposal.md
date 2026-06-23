## Why

Una nueva ley laboral colombiana permite que el "día dominical" de un trabajador no sea forzosamente el domingo (puede pactarse otro día en el contrato), que la empresa decida si paga o no el recargo dominical, y cuántos dominicales reconoce en un periodo. Hoy la app trata el domingo de forma rígida y, peor aún, **fusiona domingo y festivo en los mismos buckets de horas**, lo que impide cumplir la regla de que los festivos siempre se pagan mientras los dominicales se vuelven configurables.

## What Changes

- **BREAKING (datos):** `CalculateWorkHours` separa la clasificación de domingo/dominical de la de festivo. Los 4 buckets premium actuales (`sunday_holiday`, `night_sunday`, `overtime_day_sunday`, `overtime_night_sunday`) se dividen en 8: una familia `*_dominical` y una familia `*_holiday`. Requiere migración de columnas en `time_entries`. **Sin recálculo del histórico**: la separación aplica solo a turnos nuevos/editados; los antiguos conservan el valor fusionado en las columnas renombradas (`*_dominical`).
- **Día dominical configurable por empresa:** nueva columna `dominical_weekday` en `surcharge_rules` (0–6, default `0` = domingo). Cuando el dominical es, p. ej., martes, el domingo pasa a ser día normal sin recargo y el martes recibe el recargo dominical.
- **Switch "pagar dominicales" por empresa:** nueva columna `pay_dominical_by_default` (boolean, default `true`) en `surcharge_rules`, molde idéntico a `pay_overtime_by_default`. Cuando está apagado, los dominicales se tratan como día normal.
- **Modo de pago dominical (por hora vs por día):** replica el patrón salario/tarifa → defaults en empresa (`default_dominical_payment_mode` `hour|day`, `default_dominical_day_value` COP) en `surcharge_rules` que **siembran** al crear el empleado; y valor propio por empleado (`dominical_payment_mode`, `dominical_day_value` en `employees`) que es el que usan los cálculos. `hour` = comportamiento actual (`horas × tarifa × recargo`); `day` = la base por horas se paga siempre (como ordinario/nocturno) y encima un **plus plano** (`dominical_day_value`, solo el recargo) por cada dominical pagado, sin importar las horas.
- **"Pagar K de N dominicales" por periodo:** nueva tabla `dominical_payment_decisions` (con `employee_id` NOT NULL). El **reporte de empleado** muestra los N dominicales del periodo (default paga todos) y permite editar a K (2/1/0) recalculando el total. Precedencia: override del request → decisión guardada → default. Se persiste al exportar el reporte de empleado. **Solo aplica en modo por-día.** El reporte de empresa no tiene control: resuelve la decisión de cada empleado y muestra el total (siempre cuadra con la suma de desprendibles).
- **Festivos siempre se pagan:** las horas de festivo nunca se reducen ni se desactivan; siempre suman al total con su recargo. Si un día es festivo y dominical a la vez, gana festivo (siempre paga).
- **Dominical no pagado** (switch off, fuera del conteo K, o día reclasificado a normal): las horas pasan a ordinarias (el empleado por hora cobra tarifa base sin recargo dominical; el mensual no suma extra). **Conserva el recargo nocturno** si aplica — solo se pierde el recargo dominical.
- Reportes (pantalla, Excel, PDF) muestran festivo y dominical como conceptos separados; el dominical con su control editable de K de N y los festivos fijos aparte.

## Capabilities

### New Capabilities
- `configurable-dominical-surcharge`: Configurar el recargo dominical por empresa (día dominical, si se paga, modo por-hora/por-día con valor por empleado) y elegir por reporte cuántos dominicales se pagan en un periodo (K de N), con persistencia al exportar. Incluye la regla de que los festivos siempre se pagan.

### Modified Capabilities
- `8-hour-type-classification`: La clasificación pasa de 8 a 12 tipos: los 4 buckets premium fusionados (`sunday_holiday`, `night_sunday`, `overtime_day_sunday`, `overtime_night_sunday`) se dividen en las familias `*_dominical` y `*_holiday`, con día dominical configurable y precedencia festivo > dominical. Cambian las columnas de `time_entries` y el contrato de `CalculateReportCosts`.
- `overtime-payment-toggle`: El toggle de overtime compensado pasa a operar sobre las 6 categorías de overtime resultantes del split (`overtime_day`, `overtime_night`, `overtime_day_dominical`, `overtime_night_dominical`, `overtime_day_holiday`, `overtime_night_holiday`).

## Impact

- **Dominios afectados:** Company (config en `surcharge_rules` + nueva tabla de decisiones), Employee (campos de modo/valor dominical + siembra en `CreateEmployee`), TimeTracking (clasificación, cálculo de costos, reportes).
- **Backend:**
  - Migración 1: agregar `dominical_weekday`, `pay_dominical_by_default`, `default_dominical_payment_mode`, `default_dominical_day_value` a `surcharge_rules`.
  - Migración 2: agregar `dominical_payment_mode`, `dominical_day_value` a `employees`.
  - Migración 3: dividir/renombrar las columnas premium de `time_entries` en familias `*_dominical` y `*_holiday`.
  - Migración 4: crear tabla `dominical_payment_decisions`.
  - `CalculateWorkHours` — separar `isDominical` (usando `dominical_weekday`) de `isHoliday`; clasificar en los nuevos buckets; festivo gana sobre dominical.
  - `CalculateReportCosts` — soportar modo por-día, festivo siempre paga, dominical no pagado cae a ordinario conservando nocturno.
  - `GenerateEmployeeReport` / `GenerateCompanyReport` — sumar nuevas columnas y contar días dominicales distintos por periodo.
  - Nuevo modelo `DominicalPaymentDecision` (+ factory) y `ResolveDominicalPaymentDecision` (molde de `OvertimePaymentDecision` / `ResolveOvertimePaymentDecision`).
  - `ReportController` — resolver/persistir la decisión dominical (molde de las funciones de overtime); `ReportFilterRequest` acepta `dominical_payable_count`.
  - `UpdateSurchargeRuleRequest` + `Settings/SurchargeRuleController` — aceptar los nuevos campos; `CreateEmployee` siembra desde defaults; request de empleado acepta modo/valor.
- **Frontend:** `SurchargeRules.vue` (día dominical, switch, modo y valor por día default), formulario de empleado (modo/valor por empleado), `Reports/Employee.vue` y `Reports/Company.vue` (control K de N editable + festivos fijos aparte + recargos dominicales y festivos separados), exports Excel y vistas Blade PDF, i18n.
- **Multi-tenant:** todas las tablas llevan `company_id`; `DominicalPaymentDecision` usa `BelongsToCompany`. `super-admin` (company_id=null) no aplica a estos flujos por compañía.
- **Roles:** configuración y reportes son admin + super-admin (igual que hoy); `employee` no accede.
- **Migración de BD:** Sí — cuatro migraciones de schema (sin recálculo de datos históricos).

## Non-goals

- No se crea un desprendible congelado (snapshot inmutable); el registro es ligero, las horas/montos se recalculan desde `time_entries`.
- **No se recalcula el histórico** de `time_entries` en este change. La reclasificación retroactiva (separar festivos pasados, reclasificar tras cambiar configuración) se hará con una funcionalidad futura aparte: un botón "Recalcular" para el admin en la zona de registros. Ese feature es un change independiente.
- No se reduce ni desactiva el pago de festivos: siempre se pagan completos.
- El conteo "K de N" no aplica en modo por-hora (en por-hora siempre se pagan todos los dominicales trabajados).
- El día dominical, el switch de pago y el conteo K se manejan solo a nivel empresa; no hay día dominical ni conteo por empleado (solo el modo y el valor por día son por empleado).
- No se cambia el modelo de roles ni el acceso a reportes.
- No se modifica la lógica de overtime compensado más allá de adaptarla al nuevo set de columnas.
