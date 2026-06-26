## ADDED Requirements

### Requirement: Switches de pago de recargos premium por compañía

El sistema SHALL permitir que cada compañía configure, mediante 4 campos boolean en `surcharge_rules` (default `true` = pagar el premium), si ciertos recargos premium se pagan como tales o se colapsan hacia su recargo base:

| Campo | Si `false`, las horas se pagan como |
|---|---|
| `pay_night_dominical` | `night` (recargo nocturno normal) |
| `pay_night_holiday` | `night` |
| `pay_overtime_dominical` | `overtime_day` / `overtime_night` |
| `pay_overtime_holiday` | `overtime_day` / `overtime_night` |

**Business Rules:**
- Default `true` para todos: el comportamiento por defecto paga los premiums (como hoy).
- `pay_overtime_dominical` cubre la extra diurna y nocturna dominical; `pay_overtime_holiday` cubre la diurna y nocturna festiva.
- No afectan el recargo dominical/festivo **diurno** (`dominical` / `holiday`), que conserva su modo por hora / por día.

**Authorization:**
- Solo `admin` y `super-admin` pueden ver y modificar estos campos; `employee` no accede.

#### Scenario: Empresa no paga nocturno dominical
- **WHEN** una compañía tiene `pay_night_dominical = false` y un empleado trabaja horas nocturnas en un día dominical (modo hora)
- **THEN** esas horas se costean con el recargo `night` (nocturno normal), no con `night_sunday`
- **AND** el reporte muestra esas horas dentro del renglón de recargo nocturno

#### Scenario: Empresa no paga extra festiva
- **WHEN** una compañía tiene `pay_overtime_holiday = false` y un empleado hace horas extra diurnas y nocturnas en un festivo (y el overtime sí se paga)
- **THEN** las extra diurnas festivas se costean como `overtime_day` y las nocturnas como `overtime_night`

#### Scenario: Default paga los premiums
- **WHEN** una compañía nueva no toca estos switches
- **THEN** los 4 quedan en `true` y los recargos premium se pagan como tales (comportamiento actual)

#### Scenario: Empleado no puede modificar los switches
- **WHEN** un usuario con rol `employee` intenta actualizar `surcharge_rules`
- **THEN** el sistema responde 403

---

### Requirement: Colapso de recargos premium en el cálculo de costos

`CalculateReportCosts` SHALL aplicar el colapso en cost-time: las horas premium cuyo switch esté en `false` SHALL sumarse a su bucket base **antes** de multiplicar por la tarifa, sin calcular el premium descartado. SHALL NO modificarse la clasificación ni los buckets de `time_entries`.

**Business Rules:**
- Horas nocturnas efectivas = `night` + (¬`pay_night_dominical` ? `night_dominical` : 0) + (¬`pay_night_holiday` ? `night_holiday` : 0); el costo nocturno se calcula una sola vez sobre ese total con el recargo nocturno.
- Overtime diurno efectivo = `overtime_day` + las extras dominicales/festivas diurnas colapsadas; análogo para el nocturno.
- El colapso de overtime aplica solo cuando el overtime se paga (si `pay_overtime` es `false`, todo overtime va a $0 y los switches no aplican).
- No hay queries adicionales ni recálculo de turnos.

#### Scenario: Las horas colapsadas suman al bucket base, no se duplican
- **WHEN** un empleado tiene 2h `night` y 4h `night_dominical`, con `pay_night_dominical = false`, tarifa 10000 y recargo nocturno 35%
- **THEN** el costo nocturno es `(2 + 4) × 10000 × 1.35 = 81000`
- **AND** el costo de `night_dominical` reportado es `0`

#### Scenario: Premium pagado no se colapsa
- **WHEN** `pay_night_dominical = true`
- **THEN** las horas `night_dominical` se costean con el recargo `night_sunday` y no se suman a `night`

#### Scenario: Sin queries extra
- **WHEN** se genera el reporte con cualquier combinación de switches
- **THEN** no se ejecutan consultas adicionales respecto al cálculo sin switches (las horas ya vienen agregadas)

---

### Requirement: `pay_dominical_by_default` controla solo el recargo dominical diurno

Tras este cambio, `pay_dominical_by_default` SHALL afectar únicamente el recargo dominical **diurno** (`dominical`). La noche dominical (`night_dominical`) y la extra dominical SHALL regirse por `pay_night_dominical` y `pay_overtime_dominical` respectivamente, de forma independiente.

**Business Rules:**
- Antes, `pay_dominical_by_default = false` colapsaba toda la familia dominical (diurno + noche + extra). Ahora solo el diurno.
- Los switches son independientes: se puede pagar el diurno dominical y colapsar la noche, o viceversa.

#### Scenario: Pagar diurno dominical pero colapsar la noche
- **WHEN** `pay_dominical_by_default = true`, `pay_night_dominical = false`
- **THEN** las horas `dominical` (diurnas) se pagan con recargo dominical
- **AND** las horas `night_dominical` se pagan como `night`

#### Scenario: Colapsar diurno dominical pero pagar la noche
- **WHEN** `pay_dominical_by_default = false`, `pay_night_dominical = true`
- **THEN** las horas `dominical` (diurnas) se pagan como `regular`
- **AND** las horas `night_dominical` se pagan con recargo `night_sunday`

---

### Requirement: Migración preserva el comportamiento de pago de cada compañía

La migración SHALL sembrar `pay_night_dominical` y `pay_overtime_dominical` con el valor actual de `pay_dominical_by_default` de cada compañía, y `pay_night_holiday` / `pay_overtime_holiday` en `true`, de modo que ninguna compañía cambie lo que paga al aplicar el cambio.

**Business Rules:**
- Compañía con `pay_dominical_by_default = false` → `pay_night_dominical = false` y `pay_overtime_dominical = false` (sigue colapsando, como antes).
- Compañía con `pay_dominical_by_default = true` → los nuevos quedan en `true` (sigue pagando los premiums).
- Los festivos hoy siempre se pagan → `pay_night_holiday` / `pay_overtime_holiday` en `true` sin cambio.

#### Scenario: Compañía que no pagaba dominicales conserva su pago
- **WHEN** una compañía tenía `pay_dominical_by_default = false` antes de la migración
- **THEN** tras migrar, `pay_night_dominical = false` y `pay_overtime_dominical = false`
- **AND** sus reportes pagan la noche dominical como `night` y la extra dominical como overtime normal (igual que antes del cambio)

#### Scenario: Compañía que pagaba todo conserva su pago
- **WHEN** una compañía tenía `pay_dominical_by_default = true`
- **THEN** tras migrar, los 4 switches quedan en `true`

---

### Requirement: Visualización del recargo colapsado en reportes y exports

Los reportes de empleado y empresa, y los exports (Excel y PDF), SHALL mostrar las horas de un recargo colapsado dentro de su renglón base (`night` / `overtime_day` / `overtime_night`), con el renglón premium correspondiente en `0h / $0`.

#### Scenario: El renglón premium colapsado aparece en cero
- **WHEN** se renderiza un reporte con `pay_night_dominical = false` y horas `night_dominical > 0`
- **THEN** el renglón de recargo nocturno incluye esas horas y su costo
- **AND** el renglón de nocturno dominical muestra `0h` y `$0`

#### Scenario: Export refleja el colapso
- **WHEN** se exporta a Excel o PDF un reporte con recargos colapsados
- **THEN** los montos exportados coinciden con el reporte en pantalla (premium fundido en su base)
