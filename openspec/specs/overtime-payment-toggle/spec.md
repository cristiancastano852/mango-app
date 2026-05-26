# overtime-payment-toggle Specification

## Purpose
TBD - created by archiving change configurable-overtime-payment. Update Purpose after archive.
## Requirements
### Requirement: Default de pago de horas extra por compañía

El sistema SHALL permitir que cada compañía defina si, por defecto, las horas extra se pagan en dinero o se compensan con tiempo, mediante el campo `pay_overtime_by_default` en `surcharge_rules` (boolean, default `true`).

**Business Rules:**
- El default aplica cuando no existe una decisión guardada para el periodo del reporte.
- Las compañías existentes mantienen `true` (comportamiento actual: se pagan).

**Authorization:**
- Solo `admin` y `super-admin` pueden ver y modificar este campo, igual que el resto de `surcharge_rules`.
- `employee` no tiene acceso.

#### Scenario: Admin activa la compensación por defecto
- **WHEN** un admin guarda los ajustes de recargos con "Pagar horas extra por defecto" desactivado
- **THEN** `surcharge_rules.pay_overtime_by_default` queda en `false` para su compañía
- **AND** los reportes sin decisión guardada precargan el switch en "no pagar"

#### Scenario: Compañía existente conserva el comportamiento previo
- **WHEN** se ejecuta la migración sobre una compañía existente
- **THEN** `pay_overtime_by_default` toma el valor `true`
- **AND** el total de los reportes incluye el costo de las horas extra como antes

#### Scenario: Empleado no puede modificar el default
- **WHEN** un usuario con rol `employee` intenta actualizar `surcharge_rules`
- **THEN** el sistema responde 403

### Requirement: Cálculo de costos con horas extra compensadas

`CalculateReportCosts` SHALL aceptar un flag `payOvertime`. Cuando es `false`, los costos de las 4 categorías de hora extra (`overtime_day`, `overtime_night`, `overtime_day_sunday`, `overtime_night_sunday`) SHALL calcularse en `0`, excluirse del `total`, y marcarse con `compensated: true` en `details[]`, conservando las horas y el porcentaje de recargo originales.

**Business Rules:**
- Las horas extra (`*_hours` en `totals`) nunca se modifican: siempre reflejan lo trabajado.
- El flag es único y cubre las 4 categorías de overtime a la vez; las horas no-overtime nunca se afectan.
- Cuando `payOvertime` es `true`, el comportamiento es idéntico al actual.

#### Scenario: No se pagan las horas extra
- **WHEN** se calculan los costos con `payOvertime = false` para un empleado con 8 horas extra nocturnas
- **THEN** el reporte muestra 8 horas extra nocturnas
- **AND** el subtotal de esas horas es `0`
- **AND** el `details[]` de overtime nocturno tiene `compensated: true`
- **AND** el `total` no incluye el costo de las horas extra

#### Scenario: Se pagan las horas extra (comportamiento por defecto)
- **WHEN** se calculan los costos con `payOvertime = true`
- **THEN** cada subtotal de overtime es `horas × tarifa × (1 + recargo%)`
- **AND** el `total` incluye esos subtotales
- **AND** ningún `details[]` queda marcado como `compensated`

#### Scenario: Las horas no-overtime no se afectan
- **WHEN** se calculan los costos con `payOvertime = false`
- **THEN** los costos de horas ordinarias, nocturnas, dominicales y nocturnas-dominicales se calculan normalmente y suman al `total`

### Requirement: Override de pago de horas extra por reporte

El sistema SHALL ofrecer un switch "Pagar horas extra" en el reporte de empleado y en el reporte de empresa. El valor inicial SHALL resolverse con la precedencia: override explícito del request → decisión guardada del periodo → `pay_overtime_by_default` de la compañía.

**Business Rules:**
- El switch del reporte de empresa es independiente de las decisiones de cada empleado y aplica un único criterio a todo el total agregado.
- El switch del reporte de empleado aplica solo a ese empleado y periodo.

**Authorization:**
- Disponible para `admin` y `super-admin` (acceso actual a reportes); `employee` no accede.

#### Scenario: El reporte de empleado precarga la decisión guardada
- **WHEN** un admin abre el reporte de un empleado para un periodo que ya tiene una decisión guardada de "no pagar"
- **THEN** el switch "Pagar horas extra" aparece desactivado
- **AND** las horas extra se muestran con costo `$0`

#### Scenario: El reporte de empleado precarga el default cuando no hay decisión
- **WHEN** un admin abre el reporte de un empleado para un periodo sin decisión guardada
- **THEN** el switch se inicializa con `pay_overtime_by_default` de la compañía

#### Scenario: El reporte de empresa usa su propio criterio
- **WHEN** un admin desactiva el switch en el reporte de empresa
- **THEN** el total de empresa excluye el costo de todas las horas extra de todos los empleados
- **AND** la decisión no depende de las decisiones individuales guardadas por empleado

### Requirement: Persistencia de la decisión al exportar

El sistema SHALL registrar la decisión efectiva de pago de horas extra al exportar un reporte a PDF o Excel, mediante un upsert en `overtime_payment_decisions` por `(company_id, employee_id, start_date, end_date)` donde `employee_id` es nulo para el reporte de empresa.

**Business Rules:**
- Ver el reporte en pantalla NO persiste ninguna decisión; solo el export lo hace.
- El upsert gana la última exportación (sobrescribe `pay_overtime`, `exported_by`, `exported_at`).
- El periodo se guarda con las fechas resueltas del rango, no con el nombre del preset.
- El registro es ligero: no congela horas ni montos; al regenerar se recalcula desde los `time_entries`.

**Authorization:**
- La tabla lleva `company_id` y usa `BelongsToCompany`; un admin solo crea/lee decisiones de su propia compañía.

#### Scenario: Exportar el desprendible de un empleado guarda la decisión
- **WHEN** un admin exporta a PDF el reporte de un empleado con el switch en "no pagar"
- **THEN** se crea o actualiza una fila en `overtime_payment_decisions` con `employee_id` del empleado, las fechas del periodo y `pay_overtime = false`
- **AND** se registran `exported_by` y `exported_at`

#### Scenario: Exportar el reporte de empresa guarda una decisión global
- **WHEN** un admin exporta a Excel el reporte de empresa con el switch en "no pagar"
- **THEN** se crea o actualiza una fila con `employee_id` nulo, las fechas del periodo y `pay_overtime = false`

#### Scenario: Ver el reporte no persiste nada
- **WHEN** un admin abre el reporte en pantalla sin exportar
- **THEN** no se crea ni modifica ninguna fila en `overtime_payment_decisions`

#### Scenario: Reexportar sobrescribe la decisión anterior
- **WHEN** un admin exporta el mismo empleado y periodo primero con "no pagar" y luego con "pagar"
- **THEN** la fila correspondiente queda con `pay_overtime = true` y los nuevos `exported_by`/`exported_at`

#### Scenario: Aislamiento multi-tenant de las decisiones
- **WHEN** un admin de la compañía A exporta un reporte
- **THEN** la decisión se guarda con el `company_id` de A
- **AND** un admin de la compañía B no puede ver ni sobrescribir esa decisión

### Requirement: Visualización de horas extra compensadas

Las vistas de reporte (pantalla, Excel y PDF) SHALL mostrar las horas extra trabajadas aun cuando no se paguen, indicando el costo `$0` y la etiqueta "Compensado con tiempo".

#### Scenario: La pantalla muestra horas con costo cero
- **WHEN** un reporte se renderiza con horas extra compensadas
- **THEN** cada fila de hora extra muestra las horas trabajadas y su recargo
- **AND** la columna de costo muestra `$0` con la etiqueta "Compensado con tiempo"

#### Scenario: El export refleja la compensación
- **WHEN** se exporta un reporte con horas extra compensadas a Excel o PDF
- **THEN** las horas extra aparecen con sus horas reales y costo `0`
- **AND** el total exportado excluye el costo de las horas extra

