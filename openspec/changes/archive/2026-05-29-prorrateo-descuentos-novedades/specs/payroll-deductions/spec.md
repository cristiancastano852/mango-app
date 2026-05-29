## ADDED Requirements

### Requirement: Registro de descuentos por novedad

El sistema SHALL permitir que un administrador registre descuentos por novedad contra un empleado `monthly`, indicando un número de `días`, un `motivo` y una `fecha efectiva`, persistidos en `payroll_deductions` con scope de tenant (`company_id`) y auditoría (`created_by`).

**Business Rules:**
- `days` SHALL ser un número positivo (admite fracciones, p. ej. medio día).
- `reason` SHALL ser uno de: `FaltaInjustificada`, `LicenciaNoRemunerada`, `PermisoNoRemunerado`, `Otro`.
- `effective_date` SHALL ser la fecha que ubica el descuento dentro de un periodo de reporte.
- Solo SHALL registrarse contra empleados con `salary_type = monthly`; un empleado `hourly` SHALL ser rechazado por validación.
- `notes` es opcional (texto libre); recomendado cuando `reason = Otro`.
- El registro NO afecta recargos ni horas extra: solo el salario base.

**Authorization:**
- `admin` y `super-admin` (con company resuelta) SHALL poder crear y eliminar descuentos de su empresa.
- `employee` NO SHALL tener acceso.
- Un `admin` NO SHALL crear ni eliminar descuentos de un empleado de otra empresa (cross-company → error de validación, no 404).

#### Scenario: Admin registra un descuento de días
- **WHEN** un admin registra un descuento de 2 días con motivo `FaltaInjustificada` y fecha efectiva dentro de la quincena para un empleado `monthly`
- **THEN** se persiste una fila en `payroll_deductions` con `company_id`, `employee_id`, `days = 2`, `reason`, `effective_date` y `created_by` del admin

#### Scenario: Rechazo de descuento sobre empleado por horas
- **WHEN** un admin intenta registrar un descuento para un empleado `hourly`
- **THEN** la petición falla con error de validación
- **AND** no se persiste ninguna fila

#### Scenario: Empleado sin acceso
- **WHEN** un usuario con rol `employee` intenta registrar un descuento
- **THEN** la petición es rechazada (403)

#### Scenario: Cross-company bloqueado
- **WHEN** un admin intenta registrar un descuento para un empleado de otra empresa
- **THEN** la petición falla con error de validación (assertSessionHasErrors)

#### Scenario: Admin elimina un descuento
- **WHEN** un admin elimina un descuento existente de su empresa
- **THEN** la fila se borra de `payroll_deductions`
- **AND** el reporte del periodo recalcula el salario base sin ese descuento

### Requirement: Efecto del descuento sobre el salario base del periodo

En modo `monthly`, el sistema SHALL restar del salario base prorrateado del periodo el valor de los días descontados, donde cada día vale `monthly_base_salary / 30`, de modo que el descuento no dependa de los días calendario reales del mes.

**Business Rules:**
- `días_descontados_periodo` = suma de `days` de los descuentos cuya `effective_date` cae dentro del rango `[start, end]` del reporte.
- `base_periodo = max(0, salario_base_prorrateado − (días_descontados_periodo × monthly_base_salary / 30))`.
- El descuento SHALL aplicar por igual en meses de distinta duración (febrero = octubre).
- Cuando el descuento supera el base prorrateado, el `base_periodo` SHALL ser `0` (sin valor negativo) y el reporte SHALL señalar que el descuento fue topado.
- En modo `hourly` los descuentos NO SHALL afectar el cálculo (no hay base que prorratear).
- El descuento SHALL exponerse en el reporte como una línea propia, separada del salario base bruto.

**Authorization:**
- El cálculo se ejecuta dentro de los reportes; el acceso mantiene las reglas de rol existentes.

#### Scenario: Dos faltas en una quincena descuentan proporcional
- **WHEN** se calcula el reporte de un empleado `monthly` en una quincena completa con un descuento de 2 días
- **THEN** el salario base del periodo es `(monthly_base_salary / 2) − (2 × monthly_base_salary / 30)`
- **AND** equivale a `monthly_base_salary × 13 / 30`

#### Scenario: Mismo descuento en meses de distinta duración
- **WHEN** se calcula el reporte de un empleado `monthly` con un descuento de 1 día en una quincena de febrero (28 días) y otro de 1 día en una quincena de octubre (31 días)
- **THEN** el descuento monetario es el mismo en ambas: `monthly_base_salary / 30`

#### Scenario: Descuento topado en cero
- **WHEN** los días descontados de un empleado `monthly` superan los días pagables del periodo
- **THEN** el salario base del periodo es `0`
- **AND** el reporte indica que el descuento fue topado

#### Scenario: Descuento fuera del rango no aplica
- **WHEN** un descuento tiene `effective_date` fuera del rango del reporte
- **THEN** no se resta del salario base de ese reporte

#### Scenario: Modo por horas ignora descuentos
- **WHEN** se calcula el reporte de un empleado `hourly` que tiene descuentos registrados
- **THEN** el costo es el cálculo por horas existente, sin restar nada

#### Scenario: El descuento se muestra como concepto separado
- **WHEN** se genera el reporte de un empleado `monthly` con descuentos en el periodo
- **THEN** el descuento aparece como una línea propia
- **AND** el salario base bruto del periodo aparece como concepto separado del descuento
