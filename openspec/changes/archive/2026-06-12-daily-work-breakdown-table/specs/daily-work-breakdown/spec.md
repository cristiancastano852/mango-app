## ADDED Requirements

### Requirement: Authorization and tenancy

El desglose diario de trabajo SHALL estar disponible únicamente para los roles `admin` y `super-admin` en las vistas donde se renderiza (reporte de empleado y listado de registros), y SHALL contener exclusivamente datos de la empresa correspondiente. Un `admin` MUST NOT poder obtener el desglose diario de un empleado de otra empresa.

#### Scenario: Empleado sin acceso

- **WHEN** un usuario con rol `employee` intenta acceder al reporte de empleado o al listado de registros
- **THEN** el sistema responde 403 y no expone ningún desglose diario

#### Scenario: Cross-company bloqueado

- **WHEN** un `admin` solicita el reporte de un empleado que pertenece a otra empresa
- **THEN** el sistema rechaza la petición con error de sesión (comportamiento existente, sin cambio)

### Requirement: Datos del desglose diario

El desglose diario (`daily_breakdown`) del reporte de empleado SHALL incluir, por cada día con registro activo en el rango: la fecha, la hora de entrada y de salida (formato ISO 8601 con offset de zona horaria), el estado del registro, las horas brutas/de descanso/netas, los 8 tipos de hora clasificados, y el detalle de pausas finalizadas con nombre, icono, color, indicador de pagada, inicio, fin y duración en minutos. Los registros soft-deleted MUST NOT aparecer. Los días SHALL retornarse en orden cronológico ascendente.

#### Scenario: Día con pausas

- **WHEN** un empleado trabajó un día con dos pausas finalizadas de tipos distintos
- **THEN** el desglose de ese día incluye `clock_in`, `clock_out`, horas brutas/descanso/netas, los tipos de hora del día y las dos pausas con nombre, icono, color, `is_paid`, inicio, fin y `duration_minutes`

#### Scenario: Orden cronológico

- **WHEN** el rango contiene registros de varios días creados en cualquier orden
- **THEN** el desglose los retorna ordenados por fecha ascendente

#### Scenario: Registro eliminado excluido

- **WHEN** existe un registro soft-deleted dentro del rango
- **THEN** ese día no aparece en el desglose ni sus pausas en ningún agregado

#### Scenario: Pausa en curso

- **WHEN** una pausa del día no tiene `ended_at`
- **THEN** la pausa se incluye marcada como en curso, sin duración, y no altera las horas persistidas del registro

### Requirement: Turnos en curso en el reporte

Un registro sin `clock_out` dentro del rango SHALL aparecer en el desglose diario del reporte identificado como "En curso", sin horas trabajadas atribuidas, y MUST NOT sumar a los totales del período (`totals`) ni al resumen de costos, que siguen considerando solo turnos finalizados.

#### Scenario: Turno abierto visible pero no contabilizado

- **WHEN** el empleado tiene un turno abierto hoy y el rango del reporte incluye hoy
- **THEN** el desglose muestra el día con badge "En curso" y hora de entrada, sin horas netas, y `totals.days_worked`/`totals.net_hours`/costos no lo incluyen

#### Scenario: Exports solo con días finalizados

- **WHEN** se exporta el reporte a PDF o Excel con un turno abierto en el rango
- **THEN** el detalle diario exportado incluye únicamente los días con turno finalizado

### Requirement: Tabla de detalle diario en el reporte de empleado

La vista del reporte de empleado SHALL renderizar, debajo del resumen de costos, una tabla de detalle diario con columnas: día, horario (entrada → salida en formato 12h AM/PM), tiempo trabajado, descansos pagados y descansos no pagados en formato `Xh Ym`, diferenciando visualmente los descansos pagados (no descuentan tiempo trabajado) de los no pagados (sí descuentan), con una nota explicativa para el usuario. Cada fila con registro SHALL ser expandible para mostrar el detalle de pausas (con icono y color del tipo) y los tipos de hora del día con valor mayor a cero. Los días con horas en domingo/festivo SHALL mostrar un indicador visual distintivo, y los días con horas extra SHALL mostrar un indicador de extras.

#### Scenario: Tabla visible con datos

- **WHEN** el admin consulta el reporte de un empleado con días trabajados en el rango
- **THEN** debajo del resumen de costos aparece la tabla diaria con horario en AM/PM y duraciones en `Xh Ym`

#### Scenario: Fila expandible

- **WHEN** el admin expande la fila de un día con pausas y horas nocturnas
- **THEN** ve cada pausa con su icono, color, horario y duración, y un chip por cada tipo de hora con valor > 0

#### Scenario: Indicador de domingo/festivo

- **WHEN** un día del desglose tiene horas clasificadas como dominicales/festivas
- **THEN** la fila muestra el indicador visual de domingo/festivo

### Requirement: Días no laborados en el reporte

La tabla de detalle diario del reporte SHALL mostrar los días del rango sin registro como filas atenuadas "No laborado", únicamente para fechas menores o iguales a la fecha actual. Las fechas futuras del rango MUST NOT generar filas. Las filas "No laborado" MUST NOT afectar totales ni exports.

#### Scenario: Hueco en la quincena

- **WHEN** el rango es una quincena ya transcurrida y el empleado no trabajó dos de esos días
- **THEN** esos dos días aparecen como filas atenuadas "No laborado" en su posición cronológica

#### Scenario: Días futuros omitidos

- **WHEN** el rango es el mes actual y quedan días por transcurrir
- **THEN** los días posteriores a hoy no aparecen en la tabla

### Requirement: Detalle enriquecido en el listado de registros

El listado de `/admin/time-entries` SHALL mostrar encabezados de columna (empleado, horario, trabajado, descansos pagados, descansos no pagados, estado) y por cada registro el horario en formato 12h AM/PM, el tiempo trabajado, los descansos pagados y los descansos no pagados en formato `Xh Ym`, y SHALL permitir expandir el registro para ver el detalle de sus pausas (icono, color, horario, duración) sin navegación adicional. Los registros en curso SHALL seguir mostrándose con su hora de entrada. La obtención de las pausas SHALL realizarse con eager loading acotado a la página (sin N+1).

#### Scenario: Fila enriquecida

- **WHEN** el admin abre el listado de registros
- **THEN** la tabla muestra encabezados de columna y cada fila muestra horario AM/PM (ej. `7:00 AM → 4:11 PM`), trabajado `7h 11m`, descansos pagados `0h 15m` y descansos no pagados `1h 0m`

#### Scenario: Expandir pausas

- **WHEN** el admin expande un registro con pausas
- **THEN** ve cada pausa con icono y color de su tipo, horario y duración, sin salir del listado

#### Scenario: Sin N+1

- **WHEN** se carga una página del listado con 20 registros con pausas
- **THEN** las pausas se obtienen con un número constante de queries (eager loading), no una query por registro
