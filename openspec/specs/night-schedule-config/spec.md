# Spec: night-schedule-config

## Requirement: Configuración de horario nocturno almacenada por empresa
Cada empresa SHALL tener valores configurables `night_start_time` y `night_end_time` en su `SurchargeRule`. Los valores por defecto son `21:00` y `06:00` respectivamente (formato `HH:MM` 24h).

### Scenario: Empresa tiene SurchargeRule con valores por defecto
- **WHEN** se crea una nueva empresa
- **THEN** su `SurchargeRule` tiene `night_start_time = '21:00'` y `night_end_time = '06:00'`

### Scenario: Empresa sin SurchargeRule (caso borde)
- **WHEN** `CalculateWorkHours` se ejecuta y la empresa no tiene `SurchargeRule`
- **THEN** se usa el fallback `21:00`/`06:00` sin lanzar error

---

## Requirement: Admin puede editar el horario nocturno de su empresa
El formulario de `Configuración → Reglas de recargo` SHALL mostrar y permitir editar `night_start_time` y `night_end_time` de la empresa del admin autenticado.

### Scenario: Admin actualiza horario nocturno con valores válidos
- **WHEN** admin envía `night_start_time = '22:00'` y `night_end_time = '05:00'`
- **THEN** `surcharge_rules` de su empresa se actualiza con esos valores
- **THEN** la respuesta redirige con mensaje de éxito

### Scenario: Admin ve valores actuales pre-cargados
- **WHEN** admin abre la página de Reglas de recargo
- **THEN** los campos de horario nocturno muestran los valores actuales de su empresa

---

## Requirement: Super-admin puede editar horario nocturno de cualquier empresa
El super-admin SHALL poder actualizar `night_start_time` y `night_end_time` de cualquier empresa.

### Scenario: Super-admin actualiza horario nocturno de empresa ajena
- **WHEN** super-admin envía actualización para cualquier `company_id`
- **THEN** la `SurchargeRule` de esa empresa se actualiza correctamente

---

## Requirement: Validación de formato HH:MM
Los campos `night_start_time` y `night_end_time` SHALL ser requeridos y con formato `HH:MM` (24h).

### Scenario: Formato de hora inválido
- **WHEN** se envía `night_start_time = '25:00'` o `night_start_time = 'abc'`
- **THEN** la respuesta tiene errores de sesión para `night_start_time`

### Scenario: Hora con minutos válida
- **WHEN** se envía `night_start_time = '21:30'`
- **THEN** el valor se guarda correctamente

---

## Requirement: Protección cross-company para admin
Un admin NO SHALL poder modificar la `SurchargeRule` de otra empresa.

### Scenario: Admin intenta modificar empresa ajena
- **WHEN** admin envía actualización con `company_id` de otra empresa
- **THEN** la respuesta tiene errores de sesión (no 404)
- **THEN** la base de datos no cambia

---

## Requirement: CalculateWorkHours usa horario nocturno configurable
`CalculateWorkHours` SHALL leer `night_start_time` y `night_end_time` desde la `SurchargeRule` de la empresa. El algoritmo SHALL leer `max_daily_hours` y `max_weekly_hours` de la misma `SurchargeRule` para determinar cuándo un minuto es overtime. Un minuto es overtime si el acumulado diario neto supera `max_daily_hours` **o** el acumulado semanal neto supera `max_weekly_hours`, lo que ocurra primero.

Cuando un minuto es overtime, el sub-tipo SHALL determinarse por la combinación de `$isNight` y `$isSundayOrHoliday`, produciendo `overtime_night`, `overtime_day_sunday`, o `overtime_night_sunday` según corresponda, en lugar de colapsar todos a un único tipo de overtime.

### Scenario: Minutos dentro del rango nocturno configurado se clasifican como nocturnos
- **WHEN** `night_start_time = '22:00'` y `night_end_time = '05:00'`
- **WHEN** un empleado trabaja entre 22:00 y 05:00 en día hábil dentro del límite
- **THEN** esos minutos se clasifican como `night_hours`

### Scenario: Minutos fuera del rango nocturno no se clasifican como nocturnos
- **WHEN** `night_start_time = '22:00'` y `night_end_time = '05:00'`
- **WHEN** un empleado trabaja entre 21:00 y 22:00
- **THEN** esos minutos NO se clasifican como `night_hours`

### Scenario: Overtime en horario nocturno → overtime_night (no overtime genérico)
- **WHEN** un empleado supera `max_daily_hours` durante un rango nocturno en día hábil
- **THEN** esos minutos se clasifican como `overtime_night_hours`, no como `overtime_day_hours`

### Scenario: Overtime en horario nocturno en domingo → overtime_night_sunday
- **WHEN** un empleado supera `max_daily_hours` durante un rango nocturno en domingo o festivo
- **THEN** esos minutos se clasifican como `overtime_night_sunday_hours`
