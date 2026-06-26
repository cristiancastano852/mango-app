# employee-payroll-adjustments Specification

## Purpose
Define los ajustes de nómina por empleado (bonificaciones y préstamos/deducciones) que el admin registra desde el reporte individual para el periodo visible, y su aplicación en el reporte después del neto a pagar (`final_pay = net_pay + bonos − deducciones`) sin afectar la base de seguridad social. Son montos manuales por periodo (sin saldo acumulado ni cuotas automáticas).

## Requirements

### Requirement: Registro de ajustes de nómina por empleado

El sistema SHALL permitir registrar y eliminar ajustes de nómina asociados a un empleado **desde el reporte individual del empleado**, para el periodo que se está visualizando. Cada ajuste SHALL tener un tipo (`Bonus` que suma, o `Deduction` que resta), un monto positivo y una fecha que determina en qué periodo aplica; el concepto y la nota SHALL ser opcionales.

**Business Rules:**
- La gestión de ajustes SHALL realizarse en la vista del reporte individual (no en la ficha del empleado); el ajuste registrado allí SHALL quedar dentro del periodo visible (su `date` se asigna al periodo del reporte).
- El `amount` SHALL ser un valor monetario positivo en COP; el signo lo determina el `type`.
- El `type` SHALL ser uno de `Bonus` o `Deduction` (enum, TitleCase por convención).
- Cada ajuste SHALL pertenecer a una empresa (`company_id`) y a un empleado (`employee_id`) de esa misma empresa.
- Los ajustes son montos manuales por periodo; el sistema NO lleva saldo acumulado ni cuotas automáticas.
- Se SHALL registrar `created_by` para auditoría cuando haya usuario autenticado.

**Authorization:**
- Solo `admin` (sobre empleados de su empresa) y `super-admin` SHALL gestionar ajustes.
- Un `admin` NO SHALL crear, editar ni eliminar ajustes de empleados de otra empresa (cross-company rechazado).
- Un `employee` NO SHALL acceder a la gestión de ajustes.

#### Scenario: Admin registra una bonificación

- **WHEN** un admin crea un ajuste de tipo `Bonus` por $100.000 con concepto "Bono productividad" para un empleado de su empresa
- **THEN** el ajuste se persiste con `company_id` de su empresa, `amount` 100000, `type` Bonus y `created_by` del admin

#### Scenario: Admin registra un préstamo como deducción

- **WHEN** un admin crea un ajuste de tipo `Deduction` por $50.000 con concepto "Préstamo"
- **THEN** el ajuste se persiste como deducción asociada al empleado

#### Scenario: Concepto y nota son opcionales

- **WHEN** un admin crea un ajuste con tipo, monto y fecha pero sin concepto ni nota
- **THEN** el ajuste se persiste correctamente con `concept` y `note` nulos

#### Scenario: Monto inválido es rechazado

- **WHEN** un admin intenta crear un ajuste con monto 0 o negativo
- **THEN** la operación falla con error de validación

#### Scenario: Admin no puede gestionar ajustes de otra empresa

- **WHEN** un admin intenta crear o editar un ajuste de un empleado de otra empresa
- **THEN** la operación es rechazada (cross-company)

#### Scenario: Empleado no accede a la gestión de ajustes

- **WHEN** un usuario con rol `employee` intenta acceder a la gestión de ajustes
- **THEN** el acceso es denegado

### Requirement: Aplicación de ajustes en el reporte individual después del neto a pagar

El reporte individual SHALL sumar los ajustes cuya `date` cae dentro del periodo consultado y aplicarlos después del neto a pagar, sin afectar la base de seguridad social. El sistema SHALL calcular `final_pay = net_pay + bonus_total − deduction_total`, donde `bonus_total` es la suma de los ajustes `Bonus` del periodo y `deduction_total` la suma de los `Deduction`.

El `cost_summary` SHALL exponer `bonus_total`, `deduction_total` y `final_pay`, junto con el detalle de los ajustes del periodo. Los ajustes NO SHALL modificar `total`, `social_security_base`, `health_deduction`, `pension_deduction` ni `net_pay`.

La vista del reporte individual, el export PDF y el export Excel SHALL mostrar, debajo del neto a pagar, las bonificaciones, las deducciones y el total final a pagar.

#### Scenario: Bonificación y deducción ajustan el total final

- **WHEN** el neto a pagar es $1.000.000 y en el periodo hay un Bonus de $100.000 y un Deduction de $50.000
- **THEN** `bonus_total` es 100000, `deduction_total` es 50000 y `final_pay` es 1050000
- **AND** `net_pay`, `health_deduction` y `pension_deduction` no cambian

#### Scenario: Ajustes fuera del periodo no se aplican

- **WHEN** un ajuste tiene `date` fuera del rango del reporte
- **THEN** ese ajuste no se incluye en `bonus_total` ni en `deduction_total`

#### Scenario: Sin ajustes el total final iguala el neto

- **WHEN** no hay ajustes en el periodo
- **THEN** `bonus_total` y `deduction_total` son 0 y `final_pay` es igual a `net_pay`

#### Scenario: Exports muestran el total final

- **WHEN** un admin exporta el reporte individual a Excel o PDF y hay ajustes en el periodo
- **THEN** el archivo incluye las filas de bonificaciones, deducciones y total final a pagar
