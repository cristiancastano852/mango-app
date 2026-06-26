## ADDED Requirements

### Requirement: Modo de acumulación de overtime por empresa

Cada empresa SHALL tener un campo configurable `overtime_accrual_mode` en su `SurchargeRule`, con valores permitidos `daily` y `weekly` y default `daily`. En modo `daily` el comportamiento de clasificación de overtime es el actual (doble trigger diario/semanal). En modo `weekly`, el tope diario no clasifica overtime y solo el tope semanal lo hace.

**Business Rules:**
- El default `daily` preserva el comportamiento existente; las empresas existentes conservan `daily` tras la migración.
- `max_daily_minutes` se conserva en BD aunque quede inerte en modo `weekly`.

**Authorization:**
- Solo `admin` y `super-admin` pueden ver y modificar el campo, igual que el resto de `surcharge_rules`.
- `employee` no tiene acceso (403).

#### Scenario: Empresa nueva usa el default
- **WHEN** se crea una nueva empresa (o existe SurchargeRule sin `overtime_accrual_mode`)
- **THEN** su `SurchargeRule.overtime_accrual_mode` es `daily`

#### Scenario: Empresa existente conserva el comportamiento previo
- **WHEN** se ejecuta la migración sobre una empresa existente
- **THEN** `overtime_accrual_mode` toma el valor `daily`

#### Scenario: Admin cambia a modo semanal
- **WHEN** un admin guarda las Reglas de recargo con el modo de acumulación en `weekly`
- **THEN** `surcharge_rules.overtime_accrual_mode` queda en `weekly` para su empresa

#### Scenario: Valor inválido es rechazado
- **WHEN** se envía `overtime_accrual_mode = monthly`
- **THEN** la respuesta tiene errores de validación

#### Scenario: Empleado no puede modificar el modo
- **WHEN** un usuario con rol `employee` intenta actualizar `surcharge_rules`
- **THEN** el sistema responde 403

#### Scenario: Super-admin actualiza el modo de empresa ajena
- **WHEN** super-admin envía actualización con `company_id` de cualquier empresa y un modo válido
- **THEN** la `SurchargeRule` de esa empresa se actualiza correctamente

#### Scenario: Admin no puede modificar empresa ajena
- **WHEN** un admin envía actualización con `company_id` de otra empresa
- **THEN** la respuesta tiene errores de sesión
- **AND** la base de datos no cambia

---

### Requirement: Clasificación de overtime solo-semanal

En modo `weekly`, `CalculateWorkHours` SHALL clasificar como overtime únicamente los minutos netos que superen `max_weekly_minutes` acumulado en la semana ISO (lunes–domingo), sin considerar el tope diario. La distribución de horas entre los días de la semana NO SHALL afectar la cantidad total de overtime. El sub-tipo de overtime SHALL seguir determinándose por los atributos del minuto (diurno/nocturno, semana/dominical/festivo).

**Business Rules:**
- Los minutos por debajo del tope semanal se clasifican en sus buckets ordinarios aunque un día concreto exceda `max_daily_minutes`.
- El overtime cae naturalmente en los últimos tramos cronológicos de la semana (donde el acumulado cruza el tope).

#### Scenario: Días desbalanceados sin exceder el tope semanal no generan overtime
- **WHEN** `overtime_accrual_mode = weekly`, `max_weekly_minutes = 2520` (42h)
- **WHEN** un empleado trabaja 10h un día y 5h otro y el total de la semana es 40h
- **THEN** no se clasifica ninguna hora como overtime

#### Scenario: El excedente semanal se clasifica como overtime
- **WHEN** `overtime_accrual_mode = weekly`, `max_weekly_minutes = 2520` (42h)
- **WHEN** un empleado acumula 45h netas diurnas de semana en la semana ISO
- **THEN** `overtime_day_hours` totaliza 3.0 y el resto se clasifica como ordinario

#### Scenario: El tope diario queda inerte en modo semanal
- **WHEN** `overtime_accrual_mode = weekly`, `max_daily_minutes = 480` (8h), `max_weekly_minutes = 2520` (42h)
- **WHEN** un empleado trabaja 10h netas diurnas en un solo día sin más horas esa semana
- **THEN** `overtime_day_hours = 0` y `regular_hours = 10.0`

---

### Requirement: Liquidación de overtime por dueño del domingo

En modo `weekly`, los reportes de empleado y de empresa SHALL liquidar las horas extra de una semana ISO en el periodo que contiene el **domingo** de esa semana. Las horas extra del reporte SHALL sumarse sobre una ventana de fechas distinta a la de las horas base: la **ventana de extra** abarca desde el lunes de la primera semana cuyo domingo cae en el periodo, hasta el último domingo que cae en el periodo. Las horas base/noche/dominical/festivo SHALL seguir sumándose sobre el rango `[inicio, fin]` del periodo.

**Business Rules:**
- Solo se difiere el **recargo extra**; el salario ordinario de los días de la semana de cierre se paga por fecha en su periodo.
- La ventana de extra puede iniciar antes del `inicio` del periodo, capturando el extra diferido de la semana de cierre del periodo anterior.
- Con periodos contiguos no hay solapamiento ni doble conteo entre ventanas de extra de periodos vecinos.
- En modo `daily` la ventana de extra coincide con `[inicio, fin]` (sin cambio de comportamiento).
- Esta regla opera sobre los buckets ya almacenados por turno; no recalcula `time_entries`.

#### Scenario: Quincena que cierra a mitad de semana paga hasta el domingo anterior
- **WHEN** `overtime_accrual_mode = weekly` y el periodo termina un miércoles
- **THEN** las horas extra del reporte solo incluyen semanas cuyo domingo cae en el periodo
- **AND** el extra de la semana de cierre (en curso) no se incluye

#### Scenario: El periodo siguiente cobra el extra diferido de la semana partida
- **WHEN** `overtime_accrual_mode = weekly` y se genera el periodo siguiente
- **THEN** la ventana de extra arranca el lunes de la semana de cierre del periodo anterior
- **AND** el extra de esos días (lunes–miércoles previos) se incluye en este periodo

#### Scenario: Periodo sin ningún domingo difiere todo el extra
- **WHEN** `overtime_accrual_mode = weekly` y el rango seleccionado no contiene ningún domingo
- **THEN** las horas extra del periodo son 0

#### Scenario: Las horas base no se difieren
- **WHEN** `overtime_accrual_mode = weekly` y un empleado trabaja lunes–miércoles de la semana de cierre
- **THEN** sus horas ordinarias de esos días se incluyen por fecha en el periodo
- **AND** solo el recargo extra de esa semana se difiere

#### Scenario: Modo diario no cambia la ventana del reporte
- **WHEN** `overtime_accrual_mode = daily`
- **THEN** las horas extra se suman por el rango `[inicio, fin]` del periodo igual que hoy

---

### Requirement: Banner de rango de overtime liquidado

En modo `weekly`, los reportes (pantalla, PDF y Excel) SHALL mostrar un indicador con el rango de semanas cuyo overtime se liquida en el periodo, y SHALL avisar cuando una semana en curso se difiere al próximo periodo.

#### Scenario: El banner muestra las semanas liquidadas
- **WHEN** un admin abre un reporte en modo `weekly` que liquida 2 semanas completas
- **THEN** el banner muestra el rango de fechas de esas semanas

#### Scenario: El banner avisa de extra diferido
- **WHEN** el periodo termina a mitad de una semana ISO
- **THEN** el banner indica que el overtime de la semana en curso se liquidará en el próximo periodo

#### Scenario: El modo diario no muestra el banner
- **WHEN** un reporte se genera en modo `daily`
- **THEN** no se muestra el banner de liquidación semanal

---

### Requirement: Marcado de overtime diferido en el desglose diario

En modo `weekly`, el desglose diario del reporte de empleado SHALL marcar las filas cuyos días pertenecen a la semana de cierre diferida, indicando que su recargo extra no se paga en este periodo.

#### Scenario: Fila de la semana de cierre marca el extra como diferido
- **WHEN** `overtime_accrual_mode = weekly` y un día con horas extra pertenece a la semana de cierre diferida
- **THEN** su fila en el desglose diario indica que el overtime se difiere al próximo periodo

#### Scenario: Días ya liquidados no se marcan
- **WHEN** un día con horas extra pertenece a una semana cuyo domingo cae en el periodo
- **THEN** su fila no se marca como diferida
