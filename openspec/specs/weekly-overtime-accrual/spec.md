# weekly-overtime-accrual Specification

## Purpose
Permite que una empresa acumule y liquide las horas extra por semana ISO (no por día). En modo `weekly` solo el tope semanal genera overtime, y el recargo extra de cada semana se paga en la quincena que contiene su domingo (regla "dueño del domingo"), difiriendo la semana en curso al cierre del periodo.

## Requirements
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
- **THEN** la base de datos no cambia

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

### Requirement: Liquidación de overtime por dueño del domingo

En modo `weekly`, los reportes de empleado y de empresa SHALL liquidar las horas extra de una semana ISO en el periodo que contiene el **domingo** de esa semana. La ventana de extra SHALL abarcar desde el lunes de la primera semana cuyo domingo cae en el periodo hasta el último domingo que cae en el periodo. Para cada semana completa de esa ventana, el sistema SHALL calcular el saldo firmado `minutos netos trabajados - max_weekly_minutes`; el overtime liquidable del periodo SHALL ser el resultado positivo de sumar todos los saldos semanales. Las horas base, noche, dominical y festivo SHALL seguir sumándose sobre el rango `[inicio, fin]` del periodo.

**Business Rules:**
- Solo participan semanas completas cuyo domingo pertenece al periodo.
- Una semana por debajo del límite aporta un saldo negativo y compensa excedentes positivos de otras semanas de la misma ventana.
- El overtime trabajado SHALL ser la suma de los saldos semanales positivos, incluso si los buckets persistidos quedaron desactualizados.
- El overtime liquidable SHALL ser el saldo combinado positivo después de compensar semanas deficitarias.
- Las categorías de overtime del reporte SHALL normalizarse al total liquidable; si no existe ningún bucket clasificado, SHALL usarse extra diurna como respaldo.
- Los buckets persistidos y el detalle diario SHALL conservar el overtime trabajado antes de la compensación.
- Si el saldo combinado es negativo, el overtime liquidable SHALL ser cero.
- Solo se difiere el **recargo extra** de la semana abierta; el salario ordinario de sus días se paga por fecha en su periodo.
- La ventana de extra puede iniciar antes del `inicio` del periodo, capturando la semana cuyo domingo cae en el periodo actual.
- Con periodos contiguos no hay solapamiento ni doble conteo entre ventanas de extra de periodos vecinos.
- En modo `daily` la ventana de extra coincide con `[inicio, fin]` y no aplica compensación entre semanas.
- Esta regla deriva el balance al generar el reporte y no recalcula ni guarda valores negativos en `time_entries`.

**Authorization:**
- El cambio no amplía acceso: `admin` y `super-admin` SHALL conservar el acceso existente a reportes y exports.
- `employee` SHALL continuar sin acceso a los reportes administrativos.
- El balance de un empleado SHALL usar solamente registros de su compañía; un admin no SHALL consultar ni compensar datos de otra compañía.

#### Scenario: Déficit de una semana compensa excedente de otra
- **WHEN** el límite es `42h`, una semana completa totaliza `41h36m` y otra semana completa de la misma ventana totaliza `43h03m`
- **THEN** los saldos semanales son `-24m` y `+63m`
- **THEN** el reporte liquida `39m` de overtime

#### Scenario: Dos semanas deficitarias no descuentan dinero
- **WHEN** el límite es `42h` y dos semanas completas totalizan `40h` y `41h`
- **THEN** el balance del periodo es `-3h`
- **THEN** el overtime liquidable es `0h`
- **THEN** el salario y las deducciones no cambian por ese déficit

#### Scenario: Dos semanas excedidas conservan todo su overtime
- **WHEN** dos semanas completas superan el límite en `1h` y `2h`
- **THEN** el reporte liquida `3h` de overtime
- **THEN** la compensación entre semanas es `0h`

#### Scenario: Quincena que cierra a mitad de semana paga hasta el domingo anterior
- **WHEN** `overtime_accrual_mode = weekly` y el periodo termina un miércoles
- **THEN** el balance solo incluye semanas cuyo domingo cae en el periodo
- **THEN** el saldo de la semana de cierre todavía abierta no se incluye

#### Scenario: El periodo siguiente incorpora la semana diferida
- **WHEN** `overtime_accrual_mode = weekly` y se genera el periodo siguiente
- **THEN** la ventana de extra arranca el lunes de la semana de cierre del periodo anterior
- **THEN** el saldo completo de esa semana participa en el balance del nuevo periodo

#### Scenario: Periodo sin ningún domingo difiere todo el balance
- **WHEN** `overtime_accrual_mode = weekly` y el rango seleccionado no contiene ningún domingo
- **THEN** no existen semanas completas para balancear
- **THEN** el overtime liquidable y el déficit informativo son cero

#### Scenario: Las horas base no se difieren
- **WHEN** `overtime_accrual_mode = weekly` y un empleado trabaja lunes a miércoles de la semana de cierre
- **THEN** sus horas ordinarias se incluyen por fecha en el periodo
- **THEN** el saldo semanal no participa hasta el periodo que contiene su domingo

#### Scenario: Modo diario no cambia
- **WHEN** `overtime_accrual_mode = daily`
- **THEN** las horas extra se suman por el rango `[inicio, fin]` igual que antes
- **THEN** no se compensan saldos entre semanas

### Requirement: Resumen del balance semanal neto

En modo `weekly`, los reportes de empleado y empresa, PDF y Excel SHALL informar de forma consistente el overtime trabajado, el tiempo compensado entre semanas, el overtime liquidado y el déficit informativo. El reporte de empleado SHALL exponer además el rango, los minutos trabajados y el saldo de cada semana completa incluida.

**Business Rules:**
- El overtime trabajado corresponde a la suma de los excedentes positivos de las semanas completas de la ventana.
- La compensación corresponde a la diferencia entre overtime trabajado y overtime liquidado.
- El déficit informativo se muestra únicamente cuando el balance combinado es negativo.
- Los valores del resumen SHALL usar minutos enteros para evitar presentar centésimas de hora como minutos de reloj.

**Authorization:**
- El resumen SHALL respetar los mismos permisos y alcance de compañía del reporte que lo contiene.

#### Scenario: Reporte muestra la conciliación positiva
- **WHEN** una ventana tiene `63m` de overtime trabajado, `24m` compensados y `39m` liquidables
- **THEN** pantalla, PDF y Excel muestran los tres valores de forma consistente

#### Scenario: Buckets persistidos no limitan el balance semanal
- **WHEN** una semana excede el límite en `4h38m`, otra tiene un déficit de `21m` y los buckets persistidos solo contienen `1m` de overtime
- **THEN** el reporte muestra `4h38m` de overtime trabajado
- **THEN** muestra `21m` compensados y `4h17m` liquidados
- **THEN** las categorías de overtime mostradas totalizan `4h17m`

#### Scenario: Reporte muestra déficit informativo
- **WHEN** el balance de la ventana es `-3h`
- **THEN** pantalla, PDF y Excel muestran `3h` de tiempo faltante informativo
- **THEN** muestran `0h` de overtime liquidado

#### Scenario: Reporte de empresa no mezcla empleados
- **WHEN** un empleado tiene déficit y otro tiene overtime en la misma ventana
- **THEN** cada empleado se balancea por separado
- **THEN** el déficit de un empleado no reduce el overtime de otro

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

### Requirement: Marcado de overtime diferido en el desglose diario

En modo `weekly`, el desglose diario del reporte de empleado SHALL marcar las filas cuyos días pertenecen a la semana de cierre diferida, indicando que su recargo extra no se paga en este periodo.

#### Scenario: Fila de la semana de cierre marca el extra como diferido
- **WHEN** `overtime_accrual_mode = weekly` y un día con horas extra pertenece a la semana de cierre diferida
- **THEN** su fila en el desglose diario indica que el overtime se difiere al próximo periodo

#### Scenario: Días ya liquidados no se marcan
- **WHEN** un día con horas extra pertenece a una semana cuyo domingo cae en el periodo
- **THEN** su fila no se marca como diferida
