# deferred-night-settlement Specification

## Purpose
TBD - created by archiving change deferred-night-settlement. Update Purpose after archive.
## Requirements
### Requirement: Modo de liquidación nocturna por empresa

Cada empresa SHALL tener un campo configurable `night_settlement_mode` en su `SurchargeRule`, con valores permitidos `immediate` y `deferred` y default `immediate`. En modo `immediate` el recargo nocturno se liquida por fecha exacta del periodo (comportamiento actual). En modo `deferred` el recargo nocturno del día de corte se difiere al periodo siguiente.

**Business Rules:**
- El default `immediate` preserva el comportamiento existente; las empresas existentes conservan `immediate` tras la migración.
- Es independiente de `overtime_accrual_mode`: una empresa puede activar ambos, uno o ninguno.

**Authorization:**
- Solo `admin` y `super-admin` pueden ver y modificar el campo, igual que el resto de `surcharge_rules`.
- `employee` no tiene acceso (403).

#### Scenario: Empresa nueva usa el default
- **WHEN** se crea una nueva empresa (o existe SurchargeRule sin `night_settlement_mode`)
- **THEN** su `SurchargeRule.night_settlement_mode` es `immediate`

#### Scenario: Empresa existente conserva el comportamiento previo
- **WHEN** se ejecuta la migración sobre una empresa existente
- **THEN** `night_settlement_mode` toma el valor `immediate`

#### Scenario: Admin cambia a modo diferido
- **WHEN** un admin guarda las Reglas de recargo con el modo de liquidación nocturna en `deferred`
- **THEN** `surcharge_rules.night_settlement_mode` queda en `deferred` para su empresa

#### Scenario: Valor inválido es rechazado
- **WHEN** se envía `night_settlement_mode = monthly`
- **THEN** la respuesta tiene errores de validación

#### Scenario: Empleado no puede modificar el modo
- **WHEN** un usuario con rol `employee` intenta actualizar `surcharge_rules`
- **THEN** el sistema responde 403

### Requirement: Ventana de liquidación del recargo nocturno

En modo `deferred`, el sistema SHALL resolver una **ventana de recargo nocturno** corrida un día hacia atrás respecto al periodo: desde `inicio − 1 día` hasta `fin − 1 día`. El componente de recargo nocturno se liquida sobre esa ventana; en modo `immediate` la ventana coincide con `[inicio, fin]`.

**Business Rules:**
- La ventana excluye el día de corte (`fin`) del periodo actual y captura el día de corte (`fin` del periodo anterior = `inicio − 1`) cuyo recargo se difirió.
- Con periodos contiguos (quincenas/meses consecutivos) no hay solapamiento ni doble conteo del recargo nocturno entre periodos vecinos.
- `deferred = true` indica que el día de corte del periodo actual difiere su recargo al próximo periodo.
- Una nueva action `ResolveNightSettlementWindow` SHALL exponer `{start, end, deferred}`, hermana de `ResolveOvertimeSettlementWindow`.

#### Scenario: Modo diferido corre la ventana un día
- **WHEN** `night_settlement_mode = deferred` y el periodo es del 16 al 30
- **THEN** la ventana de recargo nocturno es del 15 al 29

#### Scenario: La ventana captura el corte del periodo anterior
- **WHEN** `night_settlement_mode = deferred` y el periodo es del 1 al 15
- **THEN** la ventana de recargo nocturno arranca el último día del periodo anterior (día 30/31 del mes previo)
- **AND** termina el día 14

#### Scenario: Modo inmediato no corre la ventana
- **WHEN** `night_settlement_mode = immediate` y el periodo es del 1 al 15
- **THEN** la ventana de recargo nocturno es del 1 al 15

### Requirement: Diferimiento del componente de recargo nocturno en costos

En modo `deferred`, `CalculateReportCosts` SHALL pagar el componente `night_surcharge`% de las horas nocturnas (`night`, `night_dominical`, `night_holiday`) sobre la ventana de recargo nocturno, mientras la **base** de la hora y el **remanente premium** dominical/festivo (por hora o por día) se pagan por fecha sobre `[inicio, fin]`.

**Business Rules:**
- El componente diferible es siempre `night_surcharge`% aplicado a las horas nocturnas; nunca se difiere la base ni el recargo dominical/festivo.
- El recargo dominical/festivo pagado **por día completo** se liquida por fecha en su periodo aunque el día caiga en el corte (se conoce al pagar).
- Cuando `pay_night_dominical` o `pay_night_holiday` está apagado (el bucket colapsa a nocturno normal), el componente diferible es el recargo nocturno normal.
- Las horas trabajadas (`*_hours` en `totals`) nunca se modifican; el diferimiento solo reubica el componente de recargo entre periodos.
- En modo `immediate` el costo nocturno es idéntico al actual.

#### Scenario: El recargo nocturno del día de corte se difiere
- **WHEN** `night_settlement_mode = deferred` y un empleado trabaja horas nocturnas el día de corte (fin del periodo)
- **THEN** ese día se paga la base de esas horas (más el recargo dominical/festivo si aplica)
- **AND** el componente `night_surcharge`% de esas horas NO se paga en este periodo

#### Scenario: El recargo nocturno diferido se cobra en el periodo siguiente
- **WHEN** `night_settlement_mode = deferred` y se genera el periodo siguiente
- **THEN** el componente `night_surcharge`% de las horas nocturnas del día de corte anterior se paga en este periodo

#### Scenario: La base nocturna no se difiere
- **WHEN** `night_settlement_mode = deferred` y un empleado trabaja horas nocturnas el día de corte
- **THEN** la base de esas horas se paga por fecha en el periodo del día de corte

#### Scenario: El recargo dominical por día completo no se difiere
- **WHEN** el día de corte cae domingo, el dominical se paga por día completo y `night_settlement_mode = deferred`
- **THEN** el recargo dominical de ese día se paga completo en su periodo
- **AND** solo el componente nocturno (`night_surcharge`%) se difiere al periodo siguiente

#### Scenario: Modo inmediato no difiere nada
- **WHEN** `night_settlement_mode = immediate`
- **THEN** el recargo nocturno se paga por fecha exacta como hoy

### Requirement: Indicadores de liquidación nocturna en el reporte

En modo `deferred`, los reportes (pantalla, PDF y Excel) SHALL mostrar el rango de noches cuyo recargo nocturno se liquida en el periodo, avisar cuando el recargo del día de corte se difiere al próximo periodo, y marcar en el desglose diario del reporte de empleado la fila del día de corte como diferida.

#### Scenario: El banner muestra el rango nocturno liquidado
- **WHEN** un admin abre un reporte en modo `deferred`
- **THEN** el banner muestra el rango de fechas cuyo recargo nocturno se liquida en el periodo

#### Scenario: El banner avisa del recargo diferido
- **WHEN** el periodo difiere el recargo nocturno de su día de corte
- **THEN** el banner indica que ese recargo se liquidará en el próximo periodo

#### Scenario: La fila del día de corte marca el recargo diferido
- **WHEN** `night_settlement_mode = deferred` y el día de corte tiene horas nocturnas
- **THEN** su fila en el desglose diario indica que el recargo nocturno se difiere al próximo periodo

#### Scenario: El modo inmediato no muestra el banner
- **WHEN** un reporte se genera en modo `immediate`
- **THEN** no se muestra el banner de liquidación nocturna

