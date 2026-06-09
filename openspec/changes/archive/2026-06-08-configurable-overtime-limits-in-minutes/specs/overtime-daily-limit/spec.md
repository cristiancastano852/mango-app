## MODIFIED Requirements

### Requirement: Límite diario de horas ordinarias almacenado por empresa
Cada empresa SHALL tener un valor configurable `max_daily_minutes` en su `SurchargeRule`, almacenado como **entero de minutos**. El valor por defecto SHALL ser `480` (equivalente a 8 horas). Este valor representa cuántos minutos netos puede trabajar un empleado en un día calendario antes de que el resto se clasifique como overtime, y SHALL permitir precisión de minutos (ej. `440` = 7 h 20 min).

#### Scenario: Empresa tiene SurchargeRule con valor por defecto
- **WHEN** se crea una nueva empresa (o existe SurchargeRule sin `max_daily_minutes`)
- **THEN** su `SurchargeRule.max_daily_minutes` es `480`

#### Scenario: Empresa sin SurchargeRule (caso borde)
- **WHEN** `CalculateWorkHours` se ejecuta y la empresa no tiene `SurchargeRule`
- **THEN** se usa el fallback `max_daily_minutes = 480` sin lanzar error

#### Scenario: Empresa configura un límite con minutos
- **WHEN** una empresa configura su jornada diaria en 7 h 20 min
- **THEN** su `SurchargeRule.max_daily_minutes` es `440`

### Requirement: CalculateWorkHours usa límite diario para detectar overtime
`CalculateWorkHours` SHALL clasificar como overtime cualquier minuto neto que supere `max_daily_minutes` en el día calendario actual, incluso si el acumulado semanal no ha alcanzado `max_weekly_minutes`. El límite SHALL usarse directamente en minutos (sin convertir desde horas). El sub-tipo de overtime SHALL determinarse por los atributos del minuto: diurno/nocturno y semana/dom-festivo, produciendo `overtime_day`, `overtime_night`, `overtime_day_sunday`, o `overtime_night_sunday`.

#### Scenario: Turno largo en día aislado dispara overtime diario (diurno)
- **WHEN** `max_daily_minutes = 480` y `max_weekly_minutes = 2520`
- **WHEN** un empleado trabaja 10h netas en un solo día hábil diurno sin horas previas esa semana
- **THEN** `regular_hours = 8.0` y `overtime_day_hours = 2.0`

#### Scenario: Límite con minutos define el breakpoint diario
- **WHEN** `max_daily_minutes = 440` (7 h 20 min)
- **WHEN** un empleado trabaja 8h netas diurnas en un solo día hábil sin horas previas esa semana
- **THEN** `regular_hours = 7.333…` (440 min) y `overtime_day_hours = 0.667…` (40 min)

#### Scenario: Overtime diario en horario nocturno → overtime_night
- **WHEN** `max_daily_minutes = 480` y el empleado ya acumuló 8h diurnas ese lunes
- **WHEN** trabaja adicionalmente de 21:00 a 23:00 el mismo lunes
- **THEN** `overtime_night_hours = 2.0` (no `night_hours`, no `overtime_day_hours`)

#### Scenario: Overtime diario en domingo diurno → overtime_day_sunday
- **WHEN** `max_daily_minutes = 480` y el empleado trabaja el domingo de 06:00 a 18:00
- **THEN** `sunday_holiday_hours = 8.0` y `overtime_day_sunday_hours = 4.0`

#### Scenario: Límite semanal sigue activo como trigger independiente
- **WHEN** `max_daily_minutes = 480` y `max_weekly_minutes = 2520`
- **WHEN** un empleado trabaja 7h por día durante 6 días (42h) y trabaja 1h diurna el séptimo día hábil
- **THEN** `overtime_day_hours = 1.0` (semanal agotado)

#### Scenario: No se cobra overtime doble
- **WHEN** un minuto ya fue clasificado como overtime por el trigger diario
- **THEN** ese minuto NO vuelve a contabilizarse como overtime por el trigger semanal

#### Scenario: Turno que cruza medianoche reinicia el contador diario
- **WHEN** un turno va de 22:00 a 08:00 (10h brutas) con `max_daily_minutes = 480`
- **WHEN** el empleado no tiene horas previas ese día ni el siguiente
- **THEN** el contador diario se reinicia a las 00:00
- **THEN** el sistema calcula overtime por separado para cada día calendario

### Requirement: Admin puede editar el límite diario de horas ordinarias
El formulario `Configuración → Reglas de recargo` SHALL mostrar y permitir editar el límite diario de la empresa del admin autenticado mediante **dos campos separados: Horas y Minutos** (minutos entre 0 y 59), que SHALL combinarse en `max_daily_minutes` al guardar y descomponerse desde `max_daily_minutes` al mostrar.

#### Scenario: Admin actualiza el límite diario con horas y minutos
- **WHEN** admin envía Horas `7` y Minutos `20`
- **THEN** `surcharge_rules.max_daily_minutes` de su empresa se actualiza a `440`
- **THEN** la respuesta redirige con mensaje de éxito

#### Scenario: Admin ve valor actual pre-cargado descompuesto
- **WHEN** admin abre la página de Reglas de recargo y su `max_daily_minutes` es `440`
- **THEN** el campo Horas muestra `7` y el campo Minutos muestra `20`

#### Scenario: Valor fuera de rango es rechazado
- **WHEN** admin envía un total de `0` minutos o un total mayor a `1440` minutos
- **THEN** la respuesta tiene errores de validación para el límite diario

### Requirement: Super-admin puede editar el límite diario de cualquier empresa
El super-admin SHALL poder actualizar `max_daily_minutes` de cualquier empresa.

#### Scenario: Super-admin actualiza límite diario de empresa ajena
- **WHEN** super-admin envía actualización con `company_id` de cualquier empresa y un límite diario válido
- **THEN** la `SurchargeRule` de esa empresa se actualiza correctamente

### Requirement: Validación del límite diario
El campo `max_daily_minutes` SHALL ser entero requerido entre `1` y `1440` inclusive (máximo 24 horas en minutos).

#### Scenario: Valor no entero es rechazado
- **WHEN** se envía `max_daily_minutes = 440.5`
- **THEN** la respuesta tiene errores de validación

#### Scenario: Valor mínimo y máximo son aceptados
- **WHEN** se envía `max_daily_minutes = 1` o `max_daily_minutes = 1440`
- **THEN** el valor se guarda correctamente

#### Scenario: Valor mayor a 1440 es rechazado
- **WHEN** se envía `max_daily_minutes = 1441`
- **THEN** la respuesta tiene errores de validación

### Requirement: Protección cross-company para admin en el límite diario
Un admin NO SHALL poder modificar el `max_daily_minutes` de otra empresa.

#### Scenario: Admin intenta modificar empresa ajena
- **WHEN** admin envía actualización con `company_id` de otra empresa
- **THEN** la respuesta tiene errores de sesión
- **THEN** la base de datos no cambia
