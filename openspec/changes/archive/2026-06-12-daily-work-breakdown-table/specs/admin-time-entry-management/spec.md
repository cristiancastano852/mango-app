## ADDED Requirements

### Requirement: Detalle visual de cada registro en el listado

El listado de registros SHALL incluir, por cada registro y sin queries adicionales por fila: las horas brutas y de descanso (columnas ya persistidas del registro), las horas de entrada/salida en formato ISO 8601 para su presentación en 12h AM/PM, y las pausas del registro (nombre, icono, color, pagada, inicio, fin, duración) cargadas con eager loading por página. El front SHALL presentar trabajado y descansos en formato `Xh Ym` y SHALL ofrecer un detalle expandible de pausas por fila.

#### Scenario: Props del listado incluyen el detalle

- **WHEN** el admin abre el listado de registros
- **THEN** cada registro del prop paginado incluye `gross_hours`, `break_hours`, `clock_in`/`clock_out` en ISO 8601 y el arreglo `breaks` con los datos de cada pausa

#### Scenario: Pausas con eager loading

- **WHEN** se sirve una página de 20 registros con pausas
- **THEN** las pausas y sus tipos se resuelven con un número constante de queries (sin N+1)

#### Scenario: Registro en curso en el listado

- **WHEN** un registro no tiene `clock_out`
- **THEN** la fila muestra la hora de entrada en 12h y el registro permanece visible en el listado (comportamiento existente, ahora con el nuevo formato)
