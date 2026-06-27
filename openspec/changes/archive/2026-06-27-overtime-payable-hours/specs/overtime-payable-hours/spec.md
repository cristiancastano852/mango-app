## ADDED Requirements

### Requirement: Precondición de overtime unificado

El input `overtime_payable_hours` SHALL aplicar únicamente cuando la empresa tiene los tres flags premium de overtime en `false`: `pay_overtime_dominical = false`, `pay_overtime_holiday = false` y `pay_overtime_night = false`. En ese estado `CalculateReportCosts` colapsa las 6 categorías de overtime en una sola bolsa diurna (`overtime_day`) a una única tarifa, de modo que limitar las horas pagables no requiere decidir entre buckets de distinta tarifa.

**Business Rules:**
- Si cualquiera de los tres flags está en `true`, el input no aplica y el cálculo de overtime es el actual (varias categorías con sus tarifas).
- La precondición se evalúa sobre la configuración de la empresa (`surcharge_rules`), no por reporte.

**Authorization:**
- Solo `admin` y `super-admin` ven y modifican estos flags y los reportes; `employee` no accede (403).

#### Scenario: Los tres flags en off habilitan el cap
- **WHEN** una empresa tiene `pay_overtime_dominical`, `pay_overtime_holiday` y `pay_overtime_night` en `false`
- **THEN** el cálculo de costos respeta `overtime_payable_hours` sobre la bolsa única de overtime diurno

#### Scenario: Un flag premium en on ignora el input
- **WHEN** una empresa tiene `pay_overtime_night = true` (o cualquiera de los otros dos)
- **THEN** `overtime_payable_hours` no afecta el cálculo
- **AND** el overtime se paga repartido en sus categorías como hoy

### Requirement: Cálculo de costos con horas extra pagables limitadas

`CalculateReportCosts` SHALL aceptar un parámetro `overtimePayableHours` (float nullable). Cuando la precondición de overtime unificado se cumple y `payOvertime` es `true`, el costo de overtime SHALL calcularse sobre `min`/override de la bolsa única diurna por el número de horas pagables, no por las horas trabajadas.

**Business Rules:**
- `overtimePayableHours = null` paga todas las horas extra de la bolsa (comportamiento actual).
- `overtimePayableHours = 0` paga `$0` de overtime (ninguna hora).
- `overtimePayableHours` puede superar las horas trabajadas; el costo se calcula sobre el número ingresado (para saldar extra pendiente de otra quincena).
- Las horas trabajadas (`*_hours` en `totals`) nunca se modifican: siempre reflejan lo trabajado; el límite solo afecta el costo.
- Cuando `payOvertime = false`, todo el overtime se compensa a `$0` sin importar `overtimePayableHours`.
- La tarifa aplicada es la de `overtime_day` (la bolsa única) por `1 + overtime_day% / 100`.

#### Scenario: Pagar menos horas de las trabajadas
- **WHEN** un empleado tiene 10 horas extra en la bolsa única y `overtimePayableHours = 5`
- **THEN** el costo de overtime es `5 × tarifa × (1 + overtime_day%/100)`
- **AND** las horas trabajadas siguen mostrando 10

#### Scenario: Pagar todas por defecto
- **WHEN** `overtimePayableHours = null` y el empleado tiene 10 horas extra en la bolsa
- **THEN** el costo de overtime es el de 10 horas (comportamiento actual)

#### Scenario: No pagar ninguna hora extra
- **WHEN** `overtimePayableHours = 0`
- **THEN** el costo de overtime es `$0`
- **AND** las horas extra trabajadas siguen visibles

#### Scenario: Sobre-pago por encima de lo trabajado
- **WHEN** un empleado tiene 10 horas extra y `overtimePayableHours = 12`
- **THEN** el costo de overtime es el de 12 horas

#### Scenario: Overtime compensado ignora el input
- **WHEN** `payOvertime = false` y `overtimePayableHours = 5`
- **THEN** el costo de overtime es `$0`

### Requirement: Resolución de las horas extra pagables por periodo

El sistema SHALL resolver el valor efectivo de `overtime_payable_hours` para un empleado y periodo con la precedencia: override explícito del request → decisión guardada del periodo → default `null` (pagar todas).

**Business Rules:**
- Es una decisión por empleado; el reporte de empresa la resuelve empleado por empleado.
- `0` es un valor válido y distinto de `null`.
- Valores negativos se normalizan a `0`.

#### Scenario: El request manda sobre lo guardado
- **WHEN** el request trae `overtime_payable_hours = 4` y existe una decisión guardada con `6`
- **THEN** el valor efectivo es `4`

#### Scenario: Sin request usa lo guardado
- **WHEN** el request no trae el valor y existe una decisión guardada con `6`
- **THEN** el valor efectivo es `6`

#### Scenario: Sin request ni guardado paga todas
- **WHEN** no hay override ni decisión guardada
- **THEN** el valor efectivo es `null` (se pagan todas las horas extra)

### Requirement: Persistencia de las horas extra pagables al exportar

El sistema SHALL persistir el valor efectivo de `overtime_payable_hours` al exportar un reporte a PDF o Excel, en la fila de `overtime_payment_decisions` del periodo `(company_id, employee_id, start_date, end_date)` (con `employee_id` nulo para el reporte de empresa).

**Business Rules:**
- Ver el reporte en pantalla NO persiste el valor; solo el export lo hace.
- El upsert convive con la decisión `pay_overtime` existente en la misma fila.
- Al regenerar el reporte no se congelan horas ni montos: se recalcula desde los `time_entries`.

**Authorization:**
- La tabla lleva `company_id` y usa `BelongsToCompany`; un admin solo crea/lee decisiones de su propia compañía.

#### Scenario: Exportar el desprendible guarda las horas pagables
- **WHEN** un admin exporta a PDF el reporte de un empleado con `overtime_payable_hours = 5`
- **THEN** la fila de `overtime_payment_decisions` del periodo queda con `overtime_payable_hours = 5`

#### Scenario: Ver el reporte no persiste el valor
- **WHEN** un admin abre el reporte en pantalla sin exportar
- **THEN** no se crea ni modifica el valor en `overtime_payment_decisions`

#### Scenario: Aislamiento multi-tenant
- **WHEN** un admin de la compañía A exporta un reporte con horas pagables
- **THEN** el valor se guarda con el `company_id` de A
- **AND** un admin de la compañía B no puede verlo ni sobrescribirlo

### Requirement: Input de horas extra pagables en los reportes

Los reportes de empleado y de empresa SHALL ofrecer un input numérico de horas extra pagables visible solo cuando los tres flags premium de overtime de la empresa están en `false`. El total del reporte SHALL recalcularse según el valor ingresado.

**Business Rules:**
- El input se inicializa con el valor resuelto (request → guardado → null).
- Cuando algún flag premium está en `true`, el input no se muestra.
- Acepta `0` (no pagar) y valores mayores a las horas trabajadas (sobre-pago).
- Pantalla, Excel y PDF reflejan el número efectivo de horas pagadas y el costo recalculado.

#### Scenario: El input aparece con overtime unificado
- **WHEN** un admin abre un reporte de una empresa con los tres flags premium en `false`
- **THEN** se muestra el input de horas extra pagables

#### Scenario: El input se oculta con flags premium activos
- **WHEN** un admin abre un reporte de una empresa con `pay_overtime_dominical = true`
- **THEN** el input de horas extra pagables no se muestra

#### Scenario: El export refleja las horas pagadas
- **WHEN** un admin exporta a Excel un reporte con `overtime_payable_hours = 5`
- **THEN** el costo de overtime exportado corresponde a 5 horas
- **AND** las horas extra trabajadas siguen visibles
