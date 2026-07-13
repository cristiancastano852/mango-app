## ADDED Requirements

### Requirement: Cálculo del descuento por días en el costo del reporte

`CalculateReportCosts` SHALL aceptar los parámetros `deductedDays` (int, default `0`) y `normalDayValue` (float). El sistema SHALL calcular `day_deduction = max(0, deductedDays) × normalDayValue` y restarlo del pago final después del neto: `final_pay = net_pay + bonus_total − deduction_total − day_deduction`.

**Business Rules:**
- El descuento es una resta plana **después del neto**; NO afecta el `total`, el IBC (`social_security_base`) ni las deducciones de salud/pensión.
- `deductedDays = 0` (o valor no provisto) produce `day_deduction = 0` (sin efecto).
- Valores negativos de `deductedDays` se normalizan a `0`.
- El valor por día es `normalDayValue` (proveniente de `employees.normal_day_value`), independiente del `salary_type`.
- Si `normalDayValue = 0`, el descuento es `$0` aunque `deductedDays > 0`.
- Las horas trabajadas y todos los subtotales de horas nunca se modifican; solo cambia `final_pay`.
- El output SHALL exponer `deducted_days`, `day_deduction_value` (valor por día usado) y `day_deduction` para presentación. El campo `normal_day_value` del empleado se refleja en el reporte vía el resumen existente.

**Authorization:**
- Solo `admin` y `super-admin` acceden a los reportes; `employee` no accede (403).

#### Scenario: Descontar días reduce el pago final
- **WHEN** un empleado tiene `net_pay = 500000`, sin bonos ni deducciones, `normal_day_value = 33333` y `deducted_days = 2`
- **THEN** `day_deduction = 66666`
- **AND** `final_pay = 433334`
- **AND** el `total` y las deducciones de seguridad social no cambian

#### Scenario: Cero días no descuenta nada
- **WHEN** `deducted_days = 0`
- **THEN** `day_deduction = 0`
- **AND** `final_pay = net_pay + bonus_total − deduction_total`

#### Scenario: Valor por día en cero
- **WHEN** `deducted_days = 3` y `normal_day_value = 0`
- **THEN** `day_deduction = 0`

#### Scenario: Días negativos se normalizan
- **WHEN** `deducted_days = -2`
- **THEN** `day_deduction = 0`

#### Scenario: El descuento no altera el IBC
- **WHEN** un empleado con seguridad social tiene `deducted_days = 2`
- **THEN** `social_security_base`, `health_deduction` y `pension_deduction` son idénticos al reporte sin descuento
- **AND** solo `final_pay` disminuye

### Requirement: Resolución de los días a descontar por periodo

El sistema SHALL resolver el valor efectivo de `deducted_days` para un empleado y periodo con la precedencia: override explícito del request → decisión guardada del periodo → default `0`.

**Business Rules:**
- Es una decisión por empleado y periodo `(company_id, employee_id, start_date, end_date)`.
- `0` es el default y significa "no descontar".
- Valores negativos del request se normalizan a `0`.
- La resolución consulta la decisión guardada solo cuando el request no trae un override.

**Authorization:**
- La resolución opera sobre la compañía del empleado; para `super-admin` (`company_id = null`) no se aplica filtro `where('company_id')`.

#### Scenario: El request manda sobre lo guardado
- **WHEN** el request trae `deducted_days = 1` y existe una decisión guardada con `3`
- **THEN** el valor efectivo es `1`

#### Scenario: Sin request usa lo guardado
- **WHEN** el request no trae el valor y existe una decisión guardada con `3`
- **THEN** el valor efectivo es `3`

#### Scenario: Sin request ni guardado no descuenta
- **WHEN** no hay override ni decisión guardada
- **THEN** el valor efectivo es `0`

### Requirement: Persistencia de los días descontados al exportar

El sistema SHALL persistir el valor efectivo de `deducted_days` al exportar un reporte de empleado a PDF o Excel, en una fila de `day_deduction_decisions` del periodo `(company_id, employee_id, start_date, end_date)` mediante upsert.

**Business Rules:**
- Ver el reporte en pantalla NO persiste el valor; solo el export lo hace.
- Al regenerar el reporte no se congelan montos: se recalcula desde los `time_entries` y el `normal_day_value` vigente.
- La tabla lleva `company_id` y usa `BelongsToCompany`.

**Authorization:**
- Un admin solo crea/lee decisiones de su propia compañía; un admin de otra compañía no puede verlas ni sobrescribirlas.

#### Scenario: Exportar guarda los días descontados
- **WHEN** un admin exporta a PDF el reporte de un empleado con `deducted_days = 2`
- **THEN** la fila de `day_deduction_decisions` del periodo queda con `deducted_days = 2`

#### Scenario: Ver el reporte no persiste el valor
- **WHEN** un admin abre el reporte en pantalla sin exportar
- **THEN** no se crea ni modifica ninguna fila en `day_deduction_decisions`

#### Scenario: Aislamiento multi-tenant
- **WHEN** un admin de la compañía A exporta un reporte con días descontados
- **THEN** el valor se guarda con el `company_id` de A
- **AND** un admin de la compañía B no puede verlo ni sobrescribirlo

### Requirement: Input de días a descontar en el reporte de empleado

El reporte de empleado SHALL ofrecer un input numérico entero "Descontar días" (`≥ 0`). El total del reporte SHALL recalcularse según el valor ingresado, y la línea de descuento SHALL aparecer en pantalla, PDF y Excel.

**Business Rules:**
- El input se inicializa con el valor resuelto (request → guardado → `0`).
- Acepta solo enteros `≥ 0`; no admite fracciones.
- Muestra un preview del monto descontado (`deducted_days × normal_day_value`).
- Pantalla, Excel y PDF reflejan el `day_deduction` y el `final_pay` recalculado.

#### Scenario: El input aparece en el reporte de empleado
- **WHEN** un admin abre el reporte de un empleado
- **THEN** se muestra el input "Descontar días"

#### Scenario: Cambiar el input recalcula el total
- **WHEN** un admin ingresa `deducted_days = 2` con `normal_day_value = 33333`
- **THEN** el reporte muestra una línea de descuento de `66666`
- **AND** el `final_pay` disminuye en `66666`

#### Scenario: El export refleja el descuento
- **WHEN** un admin exporta a Excel un reporte con `deducted_days = 2`
- **THEN** el archivo muestra la línea de descuento por días
- **AND** el `final_pay` exportado ya tiene el descuento aplicado
