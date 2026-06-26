# social-security-deduction Specification

## Purpose
Define la deducción de seguridad social a cargo del empleado (4% salud + 4% pensión) sobre el Ingreso Base de Cotización (IBC) del periodo, su cálculo dentro del reporte individual de empleado y su presentación en la vista y los exports (PDF y Excel). El IBC se define como el total devengado menos el auxilio de transporte, que no integra el IBC por ley colombiana.

## Requirements

### Requirement: Cálculo de la deducción de seguridad social del empleado

El sistema SHALL calcular, para el reporte individual de un empleado en un periodo, la deducción de seguridad social a cargo del empleado, compuesta por el aporte a salud y el aporte a pensión, aplicando cada tasa sobre el Ingreso Base de Cotización (IBC) del periodo.

El IBC SHALL definirse como el total devengado del periodo menos el auxilio de transporte (`social_security_base = total − transport_allowance`), porque el auxilio de transporte no hace parte del IBC por ley colombiana.

Las tasas de salud y pensión SHALL leerse de la configuración (`config('payroll.social_security.health')` y `config('payroll.social_security.pension')`), expresadas como porcentaje, con valor por defecto de 4 cada una.

El neto a pagar SHALL calcularse como `net_pay = total − health_deduction − pension_deduction`. El total devengado (`total`) NO cambia con esta funcionalidad.

#### Scenario: IBC en modo monthly excluye el auxilio de transporte

- **WHEN** se genera el reporte de un empleado `monthly` cuyo total devengado es la suma de salario base, recargos, horas extras y auxilio de transporte
- **THEN** `social_security_base` es igual a `total − transport_allowance`
- **AND** `health_deduction` es `round(social_security_base × 0.04, 2)`
- **AND** `pension_deduction` es `round(social_security_base × 0.04, 2)`
- **AND** `net_pay` es `total − health_deduction − pension_deduction`

#### Scenario: IBC en modo hourly equivale al total

- **WHEN** se genera el reporte de un empleado `hourly` (sin auxilio de transporte) con horas ordinarias, recargos y extras
- **THEN** `social_security_base` es igual a `total`
- **AND** `health_deduction` y `pension_deduction` son cada uno `round(total × 0.04, 2)`

#### Scenario: Sin horas trabajadas no genera deducción

- **WHEN** un empleado `hourly` no tiene horas en el periodo y su total devengado es 0
- **THEN** `social_security_base`, `health_deduction`, `pension_deduction` son 0
- **AND** `net_pay` es 0

#### Scenario: Tasas tomadas de configuración

- **WHEN** la configuración define salud en 4 y pensión en 4
- **THEN** la deducción total aplicada al IBC es del 8%
- **AND** cambiar las tasas en `config/payroll.php` cambia el cálculo sin modificar la lógica de dominio

### Requirement: Presentación de la deducción en el reporte individual y sus exports

El sistema SHALL exponer en el `cost_summary` del reporte individual los campos `social_security_base`, `health_deduction`, `pension_deduction` y `net_pay`.

La vista del reporte individual (`Reports/Employee.vue`), el export PDF y el export Excel SHALL mostrar, debajo del total devengado, las filas de aporte a salud, aporte a pensión y neto a pagar. La etiqueta del total existente SHALL dejar claro que corresponde a lo devengado (antes de deducción).

El reporte de empresa y sus exports NO SHALL incluir esta deducción (fuera de alcance).

#### Scenario: Vista del reporte individual muestra deducciones y neto

- **WHEN** un admin abre el reporte individual de un empleado con total devengado mayor a 0
- **THEN** se muestran las filas "Salud (4%)", "Pensión (4%)" y "Neto a pagar"
- **AND** el neto a pagar coincide con `cost_summary.net_pay`

#### Scenario: Export Excel incluye las filas de deducción

- **WHEN** un admin exporta el reporte individual a Excel
- **THEN** el archivo incluye las filas de salud, pensión y neto a pagar con los mismos valores que la vista

#### Scenario: Export PDF incluye las filas de deducción

- **WHEN** un admin exporta el reporte individual a PDF
- **THEN** el documento incluye las filas de salud, pensión y neto a pagar con los mismos valores que la vista

### Requirement: Autorización y multi-tenancy

La funcionalidad SHALL reutilizar la autorización existente del reporte individual de empleado: accesible para `admin` (sobre empleados de su propia empresa) y `super-admin`; un `employee` no accede a reportes de otros. La deducción se calcula sobre datos ya scopeados por `company_id` y NO persiste registros nuevos.

#### Scenario: Admin no accede a reportes de otra empresa

- **WHEN** un admin intenta generar el reporte individual de un empleado de otra empresa
- **THEN** la solicitud falla con error de validación/autorización igual que hoy (sin exponer la deducción)
