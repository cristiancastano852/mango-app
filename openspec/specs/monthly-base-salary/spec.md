# monthly-base-salary Specification

## Purpose
Define el modelo de salario por empleado (`salary_type` mensual/por hora, `monthly_base_salary` y `hourly_rate` como valor hora editable) y los defaults de salario por compañía sembrados con el SMLV vigente y editables por el admin.
## Requirements
### Requirement: Modo de salario por empleado

El sistema SHALL permitir que cada empleado tenga un `salary_type` con valor `monthly` o `hourly`. El modo `monthly` usa el salario base mensual fijo; el modo `hourly` conserva el cálculo por horas trabajadas existente. El campo `salary_type` ya existe en `employees` (default `hourly`).

**Business Rules:**
- Los empleados existentes conservan `salary_type = hourly`: su comportamiento de costo no cambia.
- `hourly_rate` representa el **valor hora** del empleado y se usa para calcular recargos y horas extra en **ambos** modos.
- En modo `monthly`, `monthly_base_salary` es obligatorio; en modo `hourly` puede ser nulo.

**Authorization:**
- Solo `admin` y `super-admin` pueden ver y editar el `salary_type`, `monthly_base_salary` y `hourly_rate` de un empleado de su propia compañía.
- Un `admin` NO puede editar empleados de otra compañía: el `CompanyScope` los excluye del route-model binding, por lo que la ruta responde `404`.
- `employee` no puede editar estos campos.

#### Scenario: Admin crea un empleado con salario mensual
- **WHEN** un admin crea un empleado con `salary_type = monthly` y `monthly_base_salary = 2000000`
- **THEN** el empleado se guarda con `salary_type = monthly` y `monthly_base_salary = 2000000`
- **AND** su `hourly_rate` queda con el valor hora ingresado o el default de la compañía

#### Scenario: Empleado existente conserva el modo por horas
- **WHEN** se ejecuta la migración sobre empleados existentes
- **THEN** cada empleado conserva `salary_type = hourly`
- **AND** su cálculo de costo en reportes no cambia

#### Scenario: Empleado no puede modificar su salario
- **WHEN** un usuario con rol `employee` intenta actualizar los campos de salario de un empleado
- **THEN** el sistema responde 403

#### Scenario: Admin no puede editar salario de otra compañía
- **WHEN** un admin intenta actualizar el salario de un empleado de otra compañía
- **THEN** el sistema responde `404` (el `CompanyScope` lo excluye del binding)
- **AND** no modifica el registro del empleado de la otra compañía

### Requirement: Defaults de salario por compañía

El sistema SHALL almacenar en `surcharge_rules` los campos `default_monthly_salary` y `default_hourly_rate`, sembrados al crear la compañía con el salario mínimo legal vigente (SMLV) y editables por el admin — mismo patrón que los porcentajes de recargo.

**Business Rules:**
- Al crear una compañía (`CompanyObserver`), `default_monthly_salary` SHALL tomar el SMLV configurado en la aplicación y `default_hourly_rate` SHALL derivarse como `default_monthly_salary / 220` (divisor de horas-mes de la jornada de 42h), redondeado.
- Al crear un empleado, los campos `monthly_base_salary` y `hourly_rate` SHALL precargarse desde los defaults de la compañía cuando no se especifican.
- Las compañías existentes reciben los defaults vía migración con el mismo SMLV.
- El divisor 220 solo aplica al **sembrar** el default; una vez creado, el admin puede editar ambos valores de forma independiente.

**Authorization:**
- Solo `admin` y `super-admin` pueden ver y modificar los defaults, igual que el resto de `surcharge_rules`.
- `employee` no tiene acceso.

#### Scenario: Defaults sembrados al crear la compañía
- **WHEN** se crea una nueva compañía
- **THEN** su `surcharge_rules.default_monthly_salary` queda con el SMLV vigente
- **AND** `default_hourly_rate` queda con `default_monthly_salary / 220` redondeado

#### Scenario: Prellenado al crear empleado
- **WHEN** un admin abre el formulario de creación de empleado
- **THEN** `monthly_base_salary` y `hourly_rate` se precargan con los defaults de su compañía
- **AND** el admin puede modificarlos antes de guardar

#### Scenario: Admin edita los defaults de la compañía
- **WHEN** un admin guarda los ajustes de recargos con un nuevo `default_monthly_salary`
- **THEN** `surcharge_rules.default_monthly_salary` queda actualizado para su compañía
- **AND** los empleados existentes NO se modifican (el default solo aplica a nuevos empleados)

#### Scenario: Empleado no puede modificar los defaults
- **WHEN** un usuario con rol `employee` intenta actualizar los defaults en `surcharge_rules`
- **THEN** el sistema responde 403
