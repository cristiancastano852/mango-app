# Spec: overtime-daily-limit

## Requirement: Límite diario de horas ordinarias almacenado por empresa
Cada empresa SHALL tener un valor configurable `max_daily_hours` en su `SurchargeRule`. El valor por defecto SHALL ser `8` (horas enteras). Este valor representa cuántas horas netas puede trabajar un empleado en un día calendario antes de que el resto se clasifique como overtime.

#### Scenario: Empresa tiene SurchargeRule con valor por defecto
- **WHEN** se crea una nueva empresa (o existe SurchargeRule sin max_daily_hours)
- **THEN** su `SurchargeRule.max_daily_hours` es `8`

#### Scenario: Empresa sin SurchargeRule (caso borde)
- **WHEN** `CalculateWorkHours` se ejecuta y la empresa no tiene `SurchargeRule`
- **THEN** se usa el fallback `max_daily_hours = 8` sin lanzar error

---

## Requirement: CalculateWorkHours usa límite diario para detectar overtime
`CalculateWorkHours` SHALL clasificar como overtime cualquier minuto neto que supere `max_daily_hours` en el día calendario actual, incluso si el acumulado semanal no ha alcanzado `max_weekly_hours`. El sub-tipo de overtime SHALL determinarse por los atributos del minuto: diurno/nocturno y semana/dom-festivo, produciendo `overtime_day`, `overtime_night`, `overtime_day_sunday`, o `overtime_night_sunday`.

#### Scenario: Turno largo en día aislado dispara overtime diario (diurno)
- **WHEN** `max_daily_hours = 8` y `max_weekly_hours = 42`
- **WHEN** un empleado trabaja 10h netas en un solo día hábil diurno sin horas previas esa semana
- **THEN** `regular_hours = 8.0` y `overtime_day_hours = 2.0`

#### Scenario: Overtime diario en horario nocturno → overtime_night
- **WHEN** `max_daily_hours = 8` y el empleado ya acumuló 8h diurnas ese lunes
- **WHEN** trabaja adicionalmente de 21:00 a 23:00 el mismo lunes
- **THEN** `overtime_night_hours = 2.0` (no `night_hours`, no `overtime_day_hours`)

#### Scenario: Overtime diario en domingo diurno → overtime_day_sunday
- **WHEN** `max_daily_hours = 8` y el empleado trabaja el domingo de 06:00 a 18:00
- **THEN** `sunday_holiday_hours = 8.0` y `overtime_day_sunday_hours = 4.0`

#### Scenario: Overtime diario en domingo nocturno → overtime_night_sunday
- **WHEN** `max_daily_hours = 8` y el empleado trabaja el domingo de 06:00 a 23:00
- **THEN** `overtime_night_sunday_hours = 2.0` (21:00–23:00, límite ya excedido)

#### Scenario: Límite semanal sigue activo como trigger independiente
- **WHEN** `max_daily_hours = 8` y `max_weekly_hours = 42`
- **WHEN** un empleado trabaja 7h por día durante 6 días (42h) y trabaja 1h diurna el séptimo día hábil
- **THEN** `overtime_day_hours = 1.0` (semanal agotado)

#### Scenario: No se cobra overtime doble
- **WHEN** un minuto ya fue clasificado como overtime por el trigger diario
- **THEN** ese minuto NO vuelve a contabilizarse como overtime por el trigger semanal

#### Scenario: El breakpoint diario se calcula con precisión de segundos
- **WHEN** el empleado tiene 6h previas ese día y `max_daily_hours = 8`
- **WHEN** trabaja un turno de 4h diurnas en ese día hábil
- **THEN** las primeras 2h del turno son `regular_hours` y las últimas 2h son `overtime_day_hours`

#### Scenario: Turno que cruza medianoche reinicia el contador diario
- **WHEN** un turno va de 22:00 a 08:00 (10h brutas) con `max_daily_hours = 8`
- **WHEN** el empleado no tiene horas previas ese día ni el siguiente
- **THEN** el contador diario se reinicia a las 00:00
- **THEN** el sistema calcula overtime por separado para cada día calendario

---

## Requirement: Admin puede editar el límite diario de horas ordinarias
El formulario `Configuración → Reglas de recargo` SHALL mostrar y permitir editar `max_daily_hours` de la empresa del admin autenticado.

#### Scenario: Admin actualiza max_daily_hours con valor válido
- **WHEN** admin envía `max_daily_hours = 10`
- **THEN** `surcharge_rules.max_daily_hours` de su empresa se actualiza a `10`
- **THEN** la respuesta redirige con mensaje de éxito

#### Scenario: Admin ve valor actual pre-cargado
- **WHEN** admin abre la página de Reglas de recargo
- **THEN** el campo `max_daily_hours` muestra el valor actual de la empresa

#### Scenario: Valor fuera de rango es rechazado
- **WHEN** admin envía `max_daily_hours = 0` o `max_daily_hours = 25`
- **THEN** la respuesta tiene errores de validación para `max_daily_hours`

---

## Requirement: Super-admin puede editar max_daily_hours de cualquier empresa
El super-admin SHALL poder actualizar `max_daily_hours` de cualquier empresa.

#### Scenario: Super-admin actualiza límite diario de empresa ajena
- **WHEN** super-admin envía actualización con `company_id` de cualquier empresa y `max_daily_hours = 9`
- **THEN** la `SurchargeRule` de esa empresa se actualiza correctamente

---

## Requirement: Validación de max_daily_hours
El campo `max_daily_hours` SHALL ser entero requerido entre 1 y 24 inclusive.

#### Scenario: Valor no entero es rechazado
- **WHEN** se envía `max_daily_hours = 8.5`
- **THEN** la respuesta tiene errores de validación

#### Scenario: Valor mínimo y máximo son aceptados
- **WHEN** se envía `max_daily_hours = 1`
- **THEN** el valor se guarda correctamente

#### Scenario: Valor mayor a 24 es rechazado
- **WHEN** se envía `max_daily_hours = 25`
- **THEN** la respuesta tiene errores de validación

---

## Requirement: Protección cross-company para admin en max_daily_hours
Un admin NO SHALL poder modificar el `max_daily_hours` de otra empresa.

#### Scenario: Admin intenta modificar empresa ajena
- **WHEN** admin envía actualización con `company_id` de otra empresa
- **THEN** la respuesta tiene errores de sesión
- **THEN** la base de datos no cambia
