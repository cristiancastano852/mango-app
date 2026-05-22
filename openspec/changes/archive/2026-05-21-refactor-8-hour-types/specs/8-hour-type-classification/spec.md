## ADDED Requirements

### Requirement: Clasificación de horas en 8 tipos mutuamente excluyentes
`CalculateWorkHours` SHALL clasificar cada minuto trabajado en exactamente uno de 8 tipos, determinado por la combinación de tres atributos: (semana vs dom/festivo) × (diurno vs nocturno) × (dentro de límite vs extra). La prioridad de resolución SHALL ser: extra > dom/festivo > nocturno > ordinario.

Los 8 tipos y sus condiciones:

| # | Tipo | Semana/Dom | Diurno/Noc | Extra |
|---|------|-----------|-----------|-------|
| 1 | `regular` | Semana | Diurno | No |
| 2 | `night` | Semana | Nocturno | No |
| 3 | `sunday_holiday` | Dom/Fest | Diurno | No |
| 4 | `night_sunday` | Dom/Fest | Nocturno | No |
| 5 | `overtime_day` | Semana | Diurno | Sí |
| 6 | `overtime_night` | Semana | Nocturno | Sí |
| 7 | `overtime_day_sunday` | Dom/Fest | Diurno | Sí |
| 8 | `overtime_night_sunday` | Dom/Fest | Nocturno | Sí |

#### Scenario: Minuto nocturno en domingo sin extra → night_sunday
- **WHEN** un empleado trabaja el domingo a las 21:30 dentro del límite diario y semanal
- **THEN** ese minuto se clasifica como `night_sunday_hours`, no como `night_hours` ni `sunday_holiday_hours`

#### Scenario: Minuto nocturno en semana con extra → overtime_night
- **WHEN** un empleado ya superó el límite diario y sigue trabajando a las 22:00 un lunes
- **THEN** ese minuto se clasifica como `overtime_night_hours`, no como `overtime_day_hours` ni `night_hours`

#### Scenario: Minuto diurno en domingo con extra → overtime_day_sunday
- **WHEN** un empleado trabaja el domingo más allá del límite diario en horario diurno
- **THEN** ese minuto se clasifica como `overtime_day_sunday_hours`, no como `sunday_holiday_hours` ni `overtime_day_hours`

#### Scenario: Minuto nocturno en domingo con extra → overtime_night_sunday
- **WHEN** un empleado trabaja el domingo más allá del límite diario en horario nocturno (21:00–06:00)
- **THEN** ese minuto se clasifica como `overtime_night_sunday_hours` con recargo del 150%

#### Scenario: Turno completo dominical con los 4 tipos dominicales
- **WHEN** un empleado trabaja el domingo de 06:00 a 23:00 sin pausas con límite diario de 8h
- **THEN** `sunday_holiday_hours = 8.0` (06:00–14:00, dentro de límite, diurno)
- **THEN** `overtime_day_sunday_hours = 7.0` (14:00–21:00, extra, diurno)
- **THEN** `overtime_night_sunday_hours = 2.0` (21:00–23:00, extra, nocturno)
- **THEN** `night_sunday_hours = 0.0` (el límite ya estaba excedido antes de las 21:00)

#### Scenario: Sábado nocturno cruzando medianoche hacia domingo
- **WHEN** un empleado trabaja el sábado de 22:00 a 04:00 del domingo sin horas previas
- **THEN** `night_hours = 2.0` (22:00–00:00, sábado, dentro de límite, nocturno)
- **THEN** `night_sunday_hours = 4.0` (00:00–04:00, domingo, dentro de límite, nocturno)

#### Scenario: Domingo nocturno cruzando medianoche hacia lunes
- **WHEN** un empleado trabaja el domingo de 22:00 a 04:00 del lunes sin horas previas
- **THEN** `night_sunday_hours = 2.0` (22:00–00:00, domingo, dentro de límite, nocturno)
- **THEN** `night_hours = 4.0` (00:00–04:00, lunes, dentro de límite, nocturno)

#### Scenario: Turno largo en semana genera regular + overtime_day + overtime_night
- **WHEN** un empleado trabaja el lunes de 06:00 a 23:00 sin pausas con límite diario de 8h
- **THEN** `regular_hours = 8.0` (06:00–14:00)
- **THEN** `overtime_day_hours = 7.0` (14:00–21:00)
- **THEN** `overtime_night_hours = 2.0` (21:00–23:00)

#### Scenario: Festivo nocturno cruzando a día hábil
- **WHEN** un festivo de semana el empleado trabaja de 21:00 a 01:00 del día siguiente (hábil)
- **THEN** `night_sunday_hours = 3.0` (21:00–00:00, festivo, dentro de límite, nocturno)
- **THEN** `night_hours = 1.0` (00:00–01:00, día hábil, dentro de límite, nocturno)

---

### Requirement: `time_entries` almacena los 8 tipos de hora como columnas independientes
La tabla `time_entries` SHALL tener columnas separadas para los 8 tipos de hora:
- `regular_hours` decimal(5,2) default 0.00
- `night_hours` decimal(5,2) default 0.00
- `sunday_holiday_hours` decimal(5,2) default 0.00
- `night_sunday_hours` decimal(5,2) default 0.00
- `overtime_day_hours` decimal(5,2) default 0.00 *(renombrado desde `overtime_hours`)*
- `overtime_night_hours` decimal(5,2) default 0.00
- `overtime_day_sunday_hours` decimal(5,2) default 0.00
- `overtime_night_sunday_hours` decimal(5,2) default 0.00

La suma de las 8 columnas SHALL ser igual a `net_hours` del turno.

#### Scenario: La suma de los 8 tipos iguala net_hours
- **WHEN** `CalculateWorkHours` procesa un turno de 10h netas
- **THEN** la suma de los 8 campos de horas en `time_entries` es `10.0`

#### Scenario: Columna renombrada
- **WHEN** existe la columna `overtime_day_hours` en `time_entries`
- **THEN** no existe la columna `overtime_hours`

---

### Requirement: `CalculateReportCosts` calcula costos con los 8 recargos
`CalculateReportCosts::execute()` SHALL aceptar los 8 tipos de hora y calcular el costo de cada uno usando el recargo correspondiente de `SurchargeRule`. SHALL retornar un array `details` con 8 items.

| Tipo | Campo en SurchargeRule | Recargo default |
|------|------------------------|-----------------|
| `regular` | — | 0% |
| `night` | `night_surcharge` | 35% |
| `sunday_holiday` | `sunday_holiday` | 75% |
| `night_sunday` | `night_sunday` | 110% |
| `overtime_day` | `overtime_day` | 25% |
| `overtime_night` | `overtime_night` | 75% |
| `overtime_day_sunday` | `overtime_day_sunday` | 100% |
| `overtime_night_sunday` | `overtime_night_sunday` | 150% |

#### Scenario: Extra nocturna aplica recargo del 75%
- **WHEN** `execute(10000, ['overtime_night_hours' => 2.0, ...], $rules)` con `overtime_night = 75`
- **THEN** `result['overtime_night'] = 35000.0` (2 × 10000 × 1.75)

#### Scenario: Nocturna dominical aplica recargo del 110%
- **WHEN** `execute(10000, ['night_sunday_hours' => 2.0, ...], $rules)` con `night_sunday = 110`
- **THEN** `result['night_sunday'] = 42000.0` (2 × 10000 × 2.10)

#### Scenario: Extra diurna dominical aplica recargo del 100%
- **WHEN** `execute(10000, ['overtime_day_sunday_hours' => 2.0, ...], $rules)` con `overtime_day_sunday = 100`
- **THEN** `result['overtime_day_sunday'] = 40000.0` (2 × 10000 × 2.00)

#### Scenario: Extra nocturna dominical aplica recargo del 150%
- **WHEN** `execute(10000, ['overtime_night_sunday_hours' => 2.0, ...], $rules)` con `overtime_night_sunday = 150`
- **THEN** `result['overtime_night_sunday'] = 50000.0` (2 × 10000 × 2.50)

#### Scenario: details contiene exactamente 8 items
- **WHEN** se llama a `execute()` con todos los tipos de hora
- **THEN** `result['details']` tiene exactamente 8 elementos

#### Scenario: Tipos con 0 horas retornan costo 0 pero siguen presentes en details
- **WHEN** varios tipos tienen `0.0` horas
- **THEN** esos tipos aparecen en `details` con `subtotal = 0`
- **THEN** el total general es la suma de los costos no-cero

---

### Requirement: Reportes muestran desglose de 8 tipos de hora
Los reportes de empleado y empresa SHALL mostrar los 8 tipos de hora en el resumen de costos. Los tipos con 0 horas SHALL seguir mostrándose para transparencia.

#### Scenario: Reporte de empleado muestra los 8 tipos
- **WHEN** admin solicita el reporte de un empleado
- **THEN** `cost_summary.details` contiene 8 items con sus respectivos recargos y subtotales

#### Scenario: Export Excel incluye los 8 tipos en la hoja resumen
- **WHEN** admin exporta el reporte de empleado en Excel
- **THEN** la hoja de resumen tiene 8 filas de tipos de hora (más una fila de total)
