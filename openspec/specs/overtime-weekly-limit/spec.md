# Spec: overtime-weekly-limit

## Requirement: Límite semanal de horas ordinarias almacenado por empresa
Cada empresa SHALL tener un valor configurable `max_weekly_minutes` en su `SurchargeRule`, almacenado como **entero de minutos**. El valor por defecto SHALL ser `2520` (equivalente a 42 horas). Representa cuántos minutos netos puede acumular un empleado en la semana ISO (lunes–domingo) antes de que el resto se clasifique como overtime, y SHALL permitir precisión de minutos.

#### Scenario: Empresa tiene SurchargeRule con valor por defecto
- **WHEN** se crea una nueva empresa (o existe SurchargeRule sin `max_weekly_minutes`)
- **THEN** su `SurchargeRule.max_weekly_minutes` es `2520`

#### Scenario: Empresa sin SurchargeRule (caso borde)
- **WHEN** `CalculateWorkHours` se ejecuta y la empresa no tiene `SurchargeRule`
- **THEN** se usa el fallback `max_weekly_minutes = 2520` sin lanzar error

#### Scenario: Empresa configura un límite semanal con minutos
- **WHEN** una empresa configura su semana en 44 h 30 min
- **THEN** su `SurchargeRule.max_weekly_minutes` es `2670`

---

## Requirement: CalculateWorkHours usa el límite semanal en minutos
`CalculateWorkHours` SHALL clasificar como overtime cualquier minuto neto que supere `max_weekly_minutes` acumulado en la semana ISO, como trigger independiente del límite diario. El límite SHALL usarse directamente en minutos (sin convertir desde horas), y no SHALL haber doble cobro: un minuto ya clasificado como overtime por el trigger diario no vuelve a contarse por el semanal.

#### Scenario: Límite semanal con minutos dispara overtime
- **WHEN** `max_weekly_minutes = 2520` (42 h) y `max_daily_minutes = 600` (10 h)
- **WHEN** un empleado acumula 42h en la semana sin disparar el límite diario y trabaja 30 min diurnos adicionales el mismo periodo
- **THEN** esos 30 min se clasifican como `overtime_day_hours = 0.5`

#### Scenario: No se cobra overtime doble entre triggers
- **WHEN** un minuto ya fue clasificado como overtime por el trigger diario
- **THEN** ese minuto NO vuelve a contabilizarse como overtime por el trigger semanal

---

## Requirement: Admin puede editar el límite semanal de horas ordinarias
El formulario `Configuración → Reglas de recargo` SHALL mostrar y permitir editar el límite semanal de la empresa del admin autenticado mediante **dos campos separados: Horas y Minutos** (minutos entre 0 y 59), que SHALL combinarse en `max_weekly_minutes` al guardar y descomponerse desde `max_weekly_minutes` al mostrar.

#### Scenario: Admin actualiza el límite semanal con horas y minutos
- **WHEN** admin envía Horas `44` y Minutos `30`
- **THEN** `surcharge_rules.max_weekly_minutes` de su empresa se actualiza a `2670`
- **THEN** la respuesta redirige con mensaje de éxito

#### Scenario: Admin ve valor actual pre-cargado descompuesto
- **WHEN** admin abre la página de Reglas de recargo y su `max_weekly_minutes` es `2520`
- **THEN** el campo Horas muestra `42` y el campo Minutos muestra `0`

---

## Requirement: Validación del límite semanal
El campo `max_weekly_minutes` SHALL ser entero requerido entre `1` y `10080` inclusive (máximo 168 horas en minutos).

#### Scenario: Valor no entero es rechazado
- **WHEN** se envía `max_weekly_minutes = 2520.5`
- **THEN** la respuesta tiene errores de validación

#### Scenario: Valor mínimo y máximo son aceptados
- **WHEN** se envía `max_weekly_minutes = 1` o `max_weekly_minutes = 10080`
- **THEN** el valor se guarda correctamente

#### Scenario: Valor mayor a 10080 es rechazado
- **WHEN** se envía `max_weekly_minutes = 10081`
- **THEN** la respuesta tiene errores de validación

---

## Requirement: Super-admin puede editar el límite semanal de cualquier empresa
El super-admin SHALL poder actualizar `max_weekly_minutes` de cualquier empresa.

#### Scenario: Super-admin actualiza límite semanal de empresa ajena
- **WHEN** super-admin envía actualización con `company_id` de cualquier empresa y un límite semanal válido
- **THEN** la `SurchargeRule` de esa empresa se actualiza correctamente

---

## Requirement: Protección cross-company para admin en el límite semanal
Un admin NO SHALL poder modificar el `max_weekly_minutes` de otra empresa.

#### Scenario: Admin intenta modificar empresa ajena
- **WHEN** admin envía actualización con `company_id` de otra empresa
- **THEN** la respuesta tiene errores de sesión
- **THEN** la base de datos no cambia
