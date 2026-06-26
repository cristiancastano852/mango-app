# 8-hour-type-classification Specification

## Purpose
Clasificación de las horas trabajadas en tipos mutuamente excluyentes y su costeo.
## Requirements
### Requirement: Clasificación de horas en 12 tipos mutuamente excluyentes
`CalculateWorkHours` SHALL clasificar cada minuto trabajado en exactamente uno de **12 tipos**, determinado por la combinación de: (semana vs dominical vs festivo) × (diurno vs nocturno) × (dentro de límite vs extra). El día dominical SHALL ser configurable vía `surcharge_rules.dominical_weekday` (no fijo a domingo). La prioridad de resolución SHALL ser: extra > (festivo > dominical) > nocturno > ordinario.

El día se determina así: si el segmento cae en un festivo → familia `*_holiday`; si no, y coincide con `dominical_weekday` → familia `*_dominical`; en otro caso → familia de semana. **Festivo gana sobre dominical** cuando un día es ambos.

Los 12 tipos y sus condiciones:

| # | Tipo | Día | Diurno/Noc | Extra |
|---|------|-----|-----------|-------|
| 1 | `regular` | Semana | Diurno | No |
| 2 | `night` | Semana | Nocturno | No |
| 3 | `dominical` | Dominical | Diurno | No |
| 4 | `night_dominical` | Dominical | Nocturno | No |
| 5 | `holiday` | Festivo | Diurno | No |
| 6 | `night_holiday` | Festivo | Nocturno | No |
| 7 | `overtime_day` | Semana | Diurno | Sí |
| 8 | `overtime_night` | Semana | Nocturno | Sí |
| 9 | `overtime_day_dominical` | Dominical | Diurno | Sí |
| 10 | `overtime_night_dominical` | Dominical | Nocturno | Sí |
| 11 | `overtime_day_holiday` | Festivo | Diurno | Sí |
| 12 | `overtime_night_holiday` | Festivo | Nocturno | Sí |

#### Scenario: Minuto nocturno en dominical sin extra → night_dominical
- **WHEN** un empleado trabaja el día dominical configurado a las 21:30 dentro del límite diario y semanal
- **THEN** ese minuto se clasifica como `night_dominical_hours`, no como `night_hours` ni `dominical_hours`

#### Scenario: Minuto en festivo se clasifica en la familia holiday
- **WHEN** un empleado trabaja un festivo diurno dentro de límite
- **THEN** ese minuto se clasifica como `holiday_hours`, no como `dominical_hours`

#### Scenario: Día festivo que además es el día dominical → gana festivo
- **WHEN** el día dominical configurado coincide con un festivo y el empleado trabaja diurno dentro de límite
- **THEN** ese minuto se clasifica como `holiday_hours`, no como `dominical_hours`

#### Scenario: Dominical configurable en martes
- **WHEN** `dominical_weekday = 2` y un empleado trabaja un martes diurno dentro de límite
- **THEN** ese minuto se clasifica como `dominical_hours`
- **AND** el domingo de esa semana se clasifica como `regular_hours`

#### Scenario: Turno completo dominical con los 4 tipos dominicales
- **WHEN** un empleado trabaja el día dominical de 06:00 a 23:00 sin pausas con límite diario de 8h
- **THEN** `dominical_hours = 8.0` (06:00–14:00, dentro de límite, diurno)
- **THEN** `overtime_day_dominical_hours = 7.0` (14:00–21:00, extra, diurno)
- **THEN** `overtime_night_dominical_hours = 2.0` (21:00–23:00, extra, nocturno)
- **THEN** `night_dominical_hours = 0.0` (el límite ya estaba excedido antes de las 21:00)

#### Scenario: Sábado nocturno cruzando medianoche hacia dominical
- **WHEN** un empleado trabaja el sábado de 22:00 a 04:00 del día dominical sin horas previas
- **THEN** `night_hours = 2.0` (22:00–00:00, sábado, dentro de límite, nocturno)
- **THEN** `night_dominical_hours = 4.0` (00:00–04:00, dominical, dentro de límite, nocturno)

#### Scenario: Festivo nocturno cruzando a día hábil
- **WHEN** un festivo de semana el empleado trabaja de 21:00 a 01:00 del día siguiente (hábil)
- **THEN** `night_holiday_hours = 3.0` (21:00–00:00, festivo, dentro de límite, nocturno)
- **THEN** `night_hours = 1.0` (00:00–01:00, día hábil, dentro de límite, nocturno)

---

### Requirement: `time_entries` almacena los 12 tipos de hora como columnas independientes
La tabla `time_entries` SHALL tener columnas separadas para los **12 tipos de hora**:
- `regular_hours` decimal(5,2) default 0.00
- `night_hours` decimal(5,2) default 0.00
- `dominical_hours` decimal(5,2) default 0.00 *(renombrado desde `sunday_holiday_hours`)*
- `night_dominical_hours` decimal(5,2) default 0.00 *(renombrado desde `night_sunday_hours`)*
- `holiday_hours` decimal(5,2) default 0.00 *(nuevo)*
- `night_holiday_hours` decimal(5,2) default 0.00 *(nuevo)*
- `overtime_day_hours` decimal(5,2) default 0.00
- `overtime_night_hours` decimal(5,2) default 0.00
- `overtime_day_dominical_hours` decimal(5,2) default 0.00 *(renombrado desde `overtime_day_sunday_hours`)*
- `overtime_night_dominical_hours` decimal(5,2) default 0.00 *(renombrado desde `overtime_night_sunday_hours`)*
- `overtime_day_holiday_hours` decimal(5,2) default 0.00 *(nuevo)*
- `overtime_night_holiday_hours` decimal(5,2) default 0.00 *(nuevo)*

La suma de las 12 columnas SHALL ser igual a `net_hours` del turno.

#### Scenario: La suma de los 12 tipos iguala net_hours
- **WHEN** `CalculateWorkHours` procesa un turno de 10h netas
- **THEN** la suma de los 12 campos de horas en `time_entries` es `10.0`

#### Scenario: Columnas premium renombradas y nuevas
- **WHEN** existe la columna `dominical_hours` en `time_entries`
- **THEN** no existe la columna `sunday_holiday_hours`
- **AND** existen las columnas `holiday_hours` y `night_holiday_hours`

#### Scenario: Turnos históricos conservan su valor sin recálculo
- **WHEN** se aplica la migración sobre turnos antiguos (clasificados con el esquema fusionado)
- **THEN** sus horas premium quedan en las columnas renombradas `*_dominical` y las `*_holiday` en 0
- **AND** los reportes de esos periodos siguen mostrando los mismos totales que antes

---

### Requirement: `CalculateReportCosts` calcula costos con los 12 recargos
`CalculateReportCosts::execute()` SHALL aceptar los **12 tipos de hora** y calcular el costo de cada uno usando el recargo correspondiente de `SurchargeRule`. Las familias `*_dominical` y `*_holiday` SHALL usar el mismo porcentaje de recargo dominical (no se acumulan). SHALL retornar un array `details` con 12 items.

| Tipo | Campo en SurchargeRule | Recargo default |
|------|------------------------|-----------------|
| `regular` | — | 0% |
| `night` | `night_surcharge` | 35% |
| `dominical` | `sunday_holiday` | 75% |
| `night_dominical` | `night_sunday` | 110% |
| `holiday` | `sunday_holiday` | 75% |
| `night_holiday` | `night_sunday` | 110% |
| `overtime_day` | `overtime_day` | 25% |
| `overtime_night` | `overtime_night` | 75% |
| `overtime_day_dominical` | `overtime_day_sunday` | 100% |
| `overtime_night_dominical` | `overtime_night_sunday` | 150% |
| `overtime_day_holiday` | `overtime_day_sunday` | 100% |
| `overtime_night_holiday` | `overtime_night_sunday` | 150% |

#### Scenario: Festivo aplica el mismo recargo que dominical
- **WHEN** `execute()` recibe `holiday_hours = 2.0` con `sunday_holiday = 75`
- **THEN** `result['holiday'] = horas × tarifa × 1.75`

#### Scenario: details contiene exactamente 12 items
- **WHEN** se llama a `execute()` con todos los tipos de hora
- **THEN** `result['details']` tiene exactamente 12 elementos

#### Scenario: Tipos con 0 horas retornan costo 0 pero siguen presentes en details
- **WHEN** varios tipos tienen `0.0` horas
- **THEN** esos tipos aparecen en `details` con `subtotal = 0`
- **THEN** el total general es la suma de los costos no-cero

---

### Requirement: Reportes muestran desglose de 12 tipos de hora
Los reportes de empleado y empresa SHALL mostrar los **12 tipos de hora** en el resumen de costos, con festivo y dominical como conceptos separados. Los tipos con 0 horas SHALL seguir mostrándose para transparencia.

#### Scenario: Reporte de empleado muestra los 12 tipos
- **WHEN** admin solicita el reporte de un empleado
- **THEN** `cost_summary.details` contiene 12 items con sus respectivos recargos y subtotales

#### Scenario: El reporte separa festivo de dominical
- **WHEN** un empleado trabajó tanto un dominical como un festivo en el periodo
- **THEN** el reporte muestra el recargo dominical y el festivo en filas distintas

#### Scenario: Export Excel incluye los 12 tipos en la hoja resumen
- **WHEN** admin exporta el reporte de empleado en Excel
- **THEN** la hoja de resumen tiene 12 filas de tipos de hora (más una fila de total)

