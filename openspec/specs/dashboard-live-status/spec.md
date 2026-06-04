## ADDED Requirements

### Requirement: Dashboard muestra solo estado en vivo

El dashboard del administrador SHALL presentar el estado actual de cada empleado (avatar, nombre y estado), sin exponer tiempos por empleado ni acciones de edición de registros. La gestión y edición de registros SHALL realizarse exclusivamente desde la sección de registros.

#### Scenario: Panel de empleados sin tiempos

- **WHEN** el admin abre el dashboard
- **THEN** cada empleado se muestra con avatar, nombre y un indicador de estado (`working`/`on_break`/`absent`/`done`)
- **AND** no se muestran las horas de entrada/salida, ni el neto de horas del día, ni un enlace de edición por empleado

#### Scenario: Sin acceso a edición desde el dashboard

- **WHEN** el admin visualiza el panel de empleados del dashboard
- **THEN** no existe ningún control que enlace a la edición de un registro de tiempo

### Requirement: KPIs y check-in manual conservados

El dashboard SHALL conservar los KPIs agregados en vivo y la acción de check-in manual.

#### Scenario: KPIs visibles

- **WHEN** el admin abre el dashboard
- **THEN** se muestran los KPIs agregados (presentes, en pausa, horas netas del día y promedio) calculados sobre registros activos del día

#### Scenario: Check-in manual disponible

- **WHEN** el admin usa el control de check-in manual y selecciona un empleado
- **THEN** el sistema marca la entrada del empleado y refleja el cambio en el estado del dashboard

#### Scenario: Estado en vivo se actualiza

- **WHEN** transcurre el intervalo de actualización del dashboard
- **THEN** el sistema refresca los KPIs y el estado de los empleados sin recargar la página completa
