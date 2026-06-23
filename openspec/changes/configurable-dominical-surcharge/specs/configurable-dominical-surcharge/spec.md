## ADDED Requirements

### Requirement: Día dominical configurable por compañía

El sistema SHALL permitir que cada compañía defina qué día de la semana es el "dominical" mediante el campo `dominical_weekday` en `surcharge_rules` (entero 0–6, `0` = domingo, default `0`). `CalculateWorkHours` SHALL clasificar como dominical los segmentos cuyo día de la semana coincida con `dominical_weekday`, en lugar de asumir siempre el domingo.

**Business Rules:**
- Cuando `dominical_weekday` apunta a otro día (p. ej. martes), el domingo deja de tener recargo dominical y se clasifica como día de semana.
- Las compañías existentes mantienen `0` (domingo), preservando el comportamiento actual.

**Authorization:**
- Solo `admin` y `super-admin` pueden ver y modificar el campo; `employee` no accede.

#### Scenario: Dominical configurado en martes
- **WHEN** una compañía fija `dominical_weekday = 2` (martes) y un empleado trabaja un martes diurno dentro de límite
- **THEN** esas horas se clasifican como `dominical_hours`
- **AND** las horas trabajadas el domingo de esa semana se clasifican como `regular_hours`

#### Scenario: Compañía existente conserva domingo
- **WHEN** se ejecuta la migración sobre una compañía existente
- **THEN** `dominical_weekday` toma el valor `0` (domingo)

#### Scenario: Empleado no puede modificar el día dominical
- **WHEN** un usuario con rol `employee` intenta actualizar `surcharge_rules`
- **THEN** el sistema responde 403

---

### Requirement: Switch de pago de dominicales por compañía

El sistema SHALL permitir que cada compañía defina si por defecto paga el recargo dominical, mediante `pay_dominical_by_default` en `surcharge_rules` (boolean, default `true`). Cuando es `false`, los reportes sin decisión guardada SHALL tratar las horas dominicales como ordinarias.

**Business Rules:**
- El default aplica cuando no existe una decisión guardada para el periodo.
- Las compañías existentes mantienen `true` (se pagan).

#### Scenario: Admin desactiva el pago dominical por defecto
- **WHEN** un admin guarda los ajustes con "Pagar dominicales por defecto" desactivado
- **THEN** `surcharge_rules.pay_dominical_by_default` queda en `false`
- **AND** los reportes sin decisión guardada no suman el recargo dominical al total

#### Scenario: Compañía existente conserva el pago dominical
- **WHEN** se ejecuta la migración sobre una compañía existente
- **THEN** `pay_dominical_by_default` toma el valor `true`

---

### Requirement: Modo de pago dominical por hora o por día

El sistema SHALL soportar dos modos de pago dominical: `hour` (recargo por hora trabajada, comportamiento actual) y `day` (monto fijo por cada día dominical trabajado, sin importar las horas). El modo y el valor por día SHALL seguir el patrón salario: defaults de compañía (`default_dominical_payment_mode`, `default_dominical_day_value` en `surcharge_rules`) que **siembran** el valor propio del empleado (`dominical_payment_mode`, `dominical_day_value` en `employees`) al crearlo; los cálculos SHALL usar el valor del empleado.

**Business Rules:**
- `default_dominical_payment_mode` default `hour`; `dominical_day_value` en COP.
- `CreateEmployee` siembra `dominical_payment_mode`/`dominical_day_value` desde los defaults cuando no se especifican (igual que `hourly_rate`).
- En modo `hour`: costo dominical = `dominical_hours × tarifa × (1 + recargo%)` (hourly) o solo el % (monthly).
- En modo `day`: `dominical_day_value` es **solo el recargo** (plus plano), no el pago total del día. La base por horas SIEMPRE se paga: `dominical_hours` como `regular` y `night_dominical_hours` como `night`; y encima se suma `min(K, N) × dominical_day_value`. Las `overtime_*_dominical` no se afectan por el modo (siguen por hora, gobernadas por el toggle de overtime).

#### Scenario: Modo por hora (comportamiento actual)
- **WHEN** un empleado por hora con `dominical_payment_mode = hour` trabaja 5h dominicales con tarifa 10000 y recargo 75%
- **THEN** el costo dominical es `5 × 10000 × 1.75 = 87500`

#### Scenario: Modo por día suma base por horas más el plus plano
- **WHEN** un empleado por hora con `dominical_payment_mode = day`, `dominical_day_value = 60000` y tarifa 10000 trabaja un día dominical de 5h y otro de 7h diurnos, ambos pagados
- **THEN** la base se cobra como ordinaria: `(5 + 7) × 10000 = 120000`
- **AND** se suma el plus: `2 × 60000 = 120000`
- **AND** el costo dominical total es `240000`

#### Scenario: Modo por día con empleado mensual solo suma el plus
- **WHEN** un empleado mensual con `dominical_payment_mode = day` y `dominical_day_value = 60000` trabaja 2 dominicales pagados
- **THEN** la base no suma extra (ya está en el salario) y el costo dominical es `2 × 60000 = 120000`

#### Scenario: El empleado hereda el default al crearse
- **WHEN** se crea un empleado sin especificar modo/valor dominical y la compañía tiene `default_dominical_payment_mode = day` y `default_dominical_day_value = 50000`
- **THEN** el empleado queda con `dominical_payment_mode = day` y `dominical_day_value = 50000`

---

### Requirement: Selección de cuántos dominicales pagar por periodo

El sistema SHALL ofrecer, **únicamente en el reporte de empleado**, un control para elegir cuántos dominicales (K) se pagan de los N dominicales trabajados en el periodo. El valor inicial SHALL resolverse con la precedencia: override del request (`dominical_payable_count`) → decisión guardada del periodo → default (todos los N). El control SHALL aplicar solo en modo `day`. El **reporte de empresa NO ofrece control**: resuelve la decisión guardada de cada empleado (o el default) y muestra el total resultante.

**Business Rules:**
- N = número de días dominicales distintos trabajados (por `entry.date`) en el periodo.
- Se pagan `min(K, N)` plus de `dominical_day_value`; los `(N − K)` no pagados no suman plus (la base de sus horas ya se paga como ordinario).
- En modo `hour` el control se ignora y se pagan todas las horas dominicales (la UI lo deshabilita).
- El control no afecta los festivos.
- El total del reporte de empresa siempre cuadra con la suma de los desprendibles de empleado (misma resolución por empleado).

**Authorization:**
- Disponible para `admin` y `super-admin`; `employee` no accede.

#### Scenario: Reducir el conteo recalcula el total (modo por día, reporte de empleado)
- **WHEN** un empleado por hora tiene 3 días dominicales (tarifa 10000, ~6h c/u), modo `day`, `dominical_day_value = 60000`, y el admin elige pagar 2
- **THEN** la base de las horas dominicales se sigue pagando como ordinaria
- **AND** solo se suman `2 × 60000 = 120000` de plus
- **AND** el tercer dominical no aporta plus

#### Scenario: Por defecto se pagan todos
- **WHEN** un admin abre el reporte de empleado con 3 dominicales y sin decisión guardada
- **THEN** el control muestra 3 de 3 y el total incluye los 3 plus

#### Scenario: El control no aplica en modo por hora
- **WHEN** el empleado está en modo `hour`
- **THEN** el control de conteo aparece deshabilitado y todas las horas dominicales se pagan

#### Scenario: El reporte de empresa no tiene control y respeta cada empleado
- **WHEN** un admin abre el reporte de empresa donde un empleado tiene decisión guardada de pagar 1 de 3 y otro no tiene decisión
- **THEN** no se muestra un control de conteo global
- **AND** el primero suma 1 plus y el segundo suma todos sus dominicales

#### Scenario: Precarga desde decisión guardada
- **WHEN** un admin abre el reporte de un empleado con una decisión guardada de pagar 1 de 3
- **THEN** el control se inicializa en 1

---

### Requirement: Persistencia de la decisión dominical al exportar

El sistema SHALL registrar la decisión efectiva de cuántos dominicales pagar al exportar el **reporte de empleado** a PDF o Excel, mediante un upsert en `dominical_payment_decisions` por `(company_id, employee_id, start_date, end_date)` con `employee_id` siempre presente. El reporte de empresa NO persiste decisiones.

**Business Rules:**
- Ver el reporte en pantalla NO persiste nada; solo el export del reporte de empleado lo hace.
- El upsert gana la última exportación (sobrescribe `payable_count`, `exported_by`, `exported_at`).
- El periodo se guarda con las fechas resueltas del rango, no el nombre del preset.
- El registro es ligero: no congela horas ni montos.

**Authorization:**
- La tabla lleva `company_id` y usa `BelongsToCompany`; un admin solo crea/lee decisiones de su compañía.

#### Scenario: Exportar el reporte de empleado guarda la decisión
- **WHEN** un admin exporta a PDF el reporte de un empleado eligiendo pagar 2 de 3 dominicales
- **THEN** se crea o actualiza una fila con `employee_id` del empleado, las fechas del periodo y `payable_count = 2`
- **AND** se registran `exported_by` y `exported_at`

#### Scenario: Exportar el reporte de empresa no persiste decisiones
- **WHEN** un admin exporta a Excel el reporte de empresa
- **THEN** no se crea ni modifica ninguna fila en `dominical_payment_decisions`

#### Scenario: Ver el reporte no persiste nada
- **WHEN** un admin abre el reporte en pantalla sin exportar
- **THEN** no se crea ni modifica ninguna fila en `dominical_payment_decisions`

#### Scenario: Aislamiento multi-tenant
- **WHEN** un admin de la compañía A exporta un reporte
- **THEN** la decisión se guarda con el `company_id` de A
- **AND** un admin de la compañía B no puede verla ni sobrescribirla

---

### Requirement: Dominical no pagado se trata como día ordinario conservando el recargo nocturno

Cuando un dominical no se paga (switch desactivado, fuera del conteo K en modo por día, o día sin recargo por configuración), `CalculateReportCosts` SHALL cobrar esas horas como ordinarias: las diurnas como `regular` y las nocturnas como `night` (conservando el recargo nocturno). SHALL perderse únicamente el recargo dominical, nunca el nocturno.

**Business Rules:**
- Empleado por hora: las horas dominicales no pagadas se cobran a tarifa base (diurnas) o tarifa base + recargo nocturno (nocturnas).
- Empleado mensual: las horas ordinarias ya están en el salario base, por lo que no suman costo adicional, salvo el recargo nocturno que sí suma.
- Difiere del overtime compensado: aquí el día trabajado sí se paga, solo sin el plus dominical.

#### Scenario: Dominical diurno no pagado cae a ordinario (por hora)
- **WHEN** un empleado por hora con tarifa 10000 trabaja 5h dominicales diurnas y la compañía no paga dominicales
- **THEN** esas 5h se cobran como `regular` a `5 × 10000 = 50000`

#### Scenario: Dominical nocturno no pagado conserva el recargo nocturno
- **WHEN** un empleado por hora trabaja horas dominicales nocturnas no pagadas, con recargo nocturno del 35%
- **THEN** esas horas se cobran con el recargo nocturno (no con el dominical)

---

### Requirement: Los festivos siempre se pagan

El sistema SHALL pagar siempre el recargo de las horas festivas, independientemente de la configuración de dominicales (`pay_dominical_by_default`, conteo K o modo). Las horas festivas SHALL clasificarse y costearse aparte de las dominicales y nunca reducirse.

**Business Rules:**
- El switch de pago dominical y el conteo K no afectan los festivos.
- Si un día es festivo y dominical a la vez, gana festivo (siempre se paga).

#### Scenario: Festivo se paga aunque los dominicales estén desactivados
- **WHEN** una compañía tiene `pay_dominical_by_default = false` y un empleado trabaja un festivo
- **THEN** las horas festivas se pagan con su recargo y suman al total

#### Scenario: Día festivo y dominical a la vez gana festivo
- **WHEN** el día dominical configurado coincide con un festivo y el empleado trabaja ese día
- **THEN** las horas se clasifican como festivas y se pagan siempre, sin verse afectadas por el conteo K
