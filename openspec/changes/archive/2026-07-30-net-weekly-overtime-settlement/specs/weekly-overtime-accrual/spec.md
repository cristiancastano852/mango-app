## MODIFIED Requirements

### Requirement: Liquidación de overtime por dueño del domingo

En modo `weekly`, los reportes de empleado y de empresa SHALL liquidar las horas extra de una semana ISO en el periodo que contiene el **domingo** de esa semana. La ventana de extra SHALL abarcar desde el lunes de la primera semana cuyo domingo cae en el periodo hasta el último domingo que cae en el periodo. Para cada semana completa de esa ventana, el sistema SHALL calcular el saldo firmado `minutos netos trabajados - max_weekly_minutes`; el overtime liquidable del periodo SHALL ser el resultado positivo de sumar todos los saldos semanales. Las horas base, noche, dominical y festivo SHALL seguir sumándose sobre el rango `[inicio, fin]` del periodo.

**Business Rules:**
- Solo participan semanas completas cuyo domingo pertenece al periodo.
- Una semana por debajo del límite aporta un saldo negativo y compensa excedentes positivos de otras semanas de la misma ventana.
- El overtime liquidable SHALL limitarse al overtime realmente clasificado en los buckets de la ventana.
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

## ADDED Requirements

### Requirement: Resumen del balance semanal neto

En modo `weekly`, los reportes de empleado y empresa, PDF y Excel SHALL informar de forma consistente el overtime trabajado, el tiempo compensado entre semanas, el overtime liquidado y el déficit informativo. El reporte de empleado SHALL exponer además el rango, los minutos trabajados y el saldo de cada semana completa incluida.

**Business Rules:**
- El overtime trabajado corresponde a los buckets positivos ya clasificados en la ventana.
- La compensación corresponde a la diferencia entre overtime trabajado y overtime liquidado.
- El déficit informativo se muestra únicamente cuando el balance combinado es negativo.
- Los valores del resumen SHALL usar minutos enteros para evitar presentar centésimas de hora como minutos de reloj.

**Authorization:**
- El resumen SHALL respetar los mismos permisos y alcance de compañía del reporte que lo contiene.

#### Scenario: Reporte muestra la conciliación positiva
- **WHEN** una ventana tiene `63m` de overtime trabajado, `24m` compensados y `39m` liquidables
- **THEN** pantalla, PDF y Excel muestran los tres valores de forma consistente

#### Scenario: Reporte muestra déficit informativo
- **WHEN** el balance de la ventana es `-3h`
- **THEN** pantalla, PDF y Excel muestran `3h` de tiempo faltante informativo
- **THEN** muestran `0h` de overtime liquidado

#### Scenario: Reporte de empresa no mezcla empleados
- **WHEN** un empleado tiene déficit y otro tiene overtime en la misma ventana
- **THEN** cada empleado se balancea por separado
- **THEN** el déficit de un empleado no reduce el overtime de otro
