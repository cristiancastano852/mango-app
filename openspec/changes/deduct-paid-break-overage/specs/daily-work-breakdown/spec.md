## MODIFIED Requirements

### Requirement: Datos del desglose diario

El desglose diario (`daily_breakdown`) del reporte de empleado SHALL incluir, por cada día con registro activo en el rango: la fecha, la hora de entrada y de salida (formato ISO 8601 con offset de zona horaria), el estado del registro, las horas brutas/de descanso/netas, las horas de pausas pagadas (`paid_break_hours`) y el exceso de pausas pagadas descontado (`paid_break_overage_hours`), los 8 tipos de hora clasificados, y el detalle de pausas finalizadas con nombre, icono, color, indicador de pagada, inicio, fin, duración en minutos y el exceso en minutos de esa pausa (`overage_minutes`, 0 si no aplica). Los registros soft-deleted MUST NOT aparecer. Los días SHALL retornarse en orden cronológico ascendente.

#### Scenario: Día con pausas

- **WHEN** un empleado trabajó un día con dos pausas finalizadas de tipos distintos
- **THEN** el desglose de ese día incluye `clock_in`, `clock_out`, horas brutas/descanso/netas, los tipos de hora del día y las dos pausas con nombre, icono, color, `is_paid`, inicio, fin, `duration_minutes` y `overage_minutes`

#### Scenario: Día con exceso de pausa pagada

- **WHEN** un empleado tomó una pausa pagada que superó su `max_duration_minutes`
- **THEN** el desglose del día expone `paid_break_overage_hours` con el exceso descontado y la pausa correspondiente incluye su `overage_minutes` mayor a cero

#### Scenario: Orden cronológico

- **WHEN** el rango contiene registros de varios días creados en cualquier orden
- **THEN** el desglose los retorna ordenados por fecha ascendente

#### Scenario: Registro eliminado excluido

- **WHEN** existe un registro soft-deleted dentro del rango
- **THEN** ese día no aparece en el desglose ni sus pausas en ningún agregado

#### Scenario: Pausa en curso

- **WHEN** una pausa del día no tiene `ended_at`
- **THEN** la pausa se incluye marcada como en curso, sin duración ni exceso, y no altera las horas persistidas del registro

### Requirement: Tabla de detalle diario en el reporte de empleado

La vista del reporte de empleado SHALL renderizar, debajo del resumen de costos, una tabla de detalle diario con columnas: día, horario (entrada → salida en formato 12h AM/PM), tiempo trabajado, descansos pagados y descansos no pagados en formato `Xh Ym`. La tabla SHALL diferenciar visualmente los descansos no pagados (descuentan tiempo trabajado) de los pagados, y SHALL indicar de forma clara el exceso de pausas pagadas que se descuenta del tiempo trabajado cuando es mayor a cero, con una nota explicativa para el usuario. Cada fila con registro SHALL ser expandible para mostrar el detalle de pausas (con icono y color del tipo, y el exceso descontado de la pausa que superó su límite) y los tipos de hora del día con valor mayor a cero. Los días con horas en domingo/festivo SHALL mostrar un indicador visual distintivo, y los días con horas extra SHALL mostrar un indicador de extras.

#### Scenario: Tabla visible con datos

- **WHEN** el admin consulta el reporte de un empleado con días trabajados en el rango
- **THEN** debajo del resumen de costos aparece la tabla diaria con horario en AM/PM y duraciones en `Xh Ym`

#### Scenario: Fila expandible

- **WHEN** el admin expande la fila de un día con pausas y horas nocturnas
- **THEN** ve cada pausa con su icono, color, horario y duración, y un chip por cada tipo de hora con valor > 0

#### Scenario: Exceso de pausa pagada visible y comprensible

- **WHEN** un día tiene una pausa pagada que superó su límite y el admin lo consulta
- **THEN** la tabla indica el exceso descontado del tiempo trabajado de ese día, y al expandir la fila la pausa excedida muestra cuántos minutos se descontaron

#### Scenario: Indicador de domingo/festivo

- **WHEN** un día del desglose tiene horas clasificadas como dominicales/festivas
- **THEN** la fila muestra el indicador visual de domingo/festivo

### Requirement: Detalle enriquecido en el listado de registros

El listado de `/admin/time-entries` SHALL mostrar encabezados de columna (empleado, horario, trabajado, descansos pagados, descansos no pagados, estado) y por cada registro el horario en formato 12h AM/PM, el tiempo trabajado, los descansos pagados y los descansos no pagados en formato `Xh Ym`, y SHALL permitir expandir el registro para ver el detalle de sus pausas (icono, color, horario, duración, y exceso descontado cuando la pausa pagada superó su límite) sin navegación adicional. Cuando un registro tenga exceso de pausas pagadas descontado, el listado SHALL indicarlo de forma comprensible. Los registros en curso SHALL seguir mostrándose con su hora de entrada. La obtención de las pausas SHALL realizarse con eager loading acotado a la página (sin N+1).

#### Scenario: Fila enriquecida

- **WHEN** el admin abre el listado de registros
- **THEN** la tabla muestra encabezados de columna y cada fila muestra horario AM/PM (ej. `7:00 AM → 4:11 PM`), trabajado `7h 11m`, descansos pagados `0h 15m` y descansos no pagados `1h 0m`

#### Scenario: Expandir pausas

- **WHEN** el admin expande un registro con pausas
- **THEN** ve cada pausa con icono y color de su tipo, horario y duración, sin salir del listado

#### Scenario: Exceso de pausa pagada en el listado

- **WHEN** un registro tiene una pausa pagada que superó su límite
- **THEN** el listado indica el exceso descontado del tiempo trabajado y, al expandir, la pausa excedida muestra los minutos descontados

#### Scenario: Sin N+1

- **WHEN** se carga una página del listado con 20 registros con pausas
- **THEN** las pausas se obtienen con un número constante de queries (eager loading), no una query por registro
