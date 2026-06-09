# transport-allowance Specification

## Purpose
Define el auxilio de transporte colombiano en la nómina: su configuración (default global + valor por empresa), la elegibilidad por flag de empleado restringida a modo `monthly`, el prorrateo por mes comercial y su exposición como concepto propio en los reportes.

## Requirements

### Requirement: Configuración del valor del auxilio de transporte

El sistema SHALL definir un valor por defecto global del auxilio de transporte mensual en `config('payroll.transport_allowance_monthly')`, con override por la variable de entorno `PAYROLL_TRANSPORT_ALLOWANCE_MONTHLY` y valor base `249095` (Colombia 2026). Cada empresa SHALL tener su propio valor en `surcharge_rules.transport_allowance`, sembrado del default global al crearse la empresa y editable desde la configuración de recargos.

**Business Rules:**
- `CompanyObserver` SHALL sembrar `transport_allowance` con `config('payroll.transport_allowance_monthly')` al crear la empresa, junto al resto de defaults de `SurchargeRule`.
- El valor por empresa SHALL ser un monto mensual no negativo en COP.
- El valor editable es el de la empresa; el default global solo aplica al sembrar.

**Authorization:**
- Solo `admin` (de su empresa) y `super-admin` SHALL editar el valor del auxilio, con las mismas reglas que el resto de la configuración de recargos.
- Un `admin` NO SHALL modificar el auxilio de otra empresa (cross-company rechazado).

#### Scenario: Se siembra el auxilio por defecto al crear empresa
- **WHEN** se crea una nueva empresa
- **THEN** su `surcharge_rules.transport_allowance` queda en `config('payroll.transport_allowance_monthly')`

#### Scenario: Admin edita el valor del auxilio de su empresa
- **WHEN** un `admin` actualiza el valor del auxilio de transporte en la configuración de recargos
- **THEN** el nuevo valor se guarda en `surcharge_rules.transport_allowance` de su empresa

#### Scenario: Admin no puede editar el auxilio de otra empresa
- **WHEN** un `admin` intenta actualizar el auxilio de una empresa distinta a la suya
- **THEN** la operación es rechazada por las reglas cross-company existentes

### Requirement: Elegibilidad del auxilio por flag de empleado en modo mensual

El sistema SHALL determinar quién recibe el auxilio de transporte mediante el flag `employees.receives_transport_allowance`, aplicable únicamente a empleados en modo `monthly`. Los empleados en modo `hourly` NUNCA SHALL recibir auxilio, sin importar el flag.

**Business Rules:**
- El flag `receives_transport_allowance` SHALL tener valor por defecto `true` para empleados nuevos creados en modo `monthly`.
- El flag SHALL ser editable por el `admin` desde la edición del empleado.
- En modo `hourly`, el auxilio SHALL ser `0` y no SHALL mostrarse, independientemente del flag.
- No se SHALL aplicar verificación automática del tope de 2 SMLV; la decisión final es el flag.
- Al desplegar, los empleados `monthly` existentes SHALL recibir `receives_transport_allowance = true` (backfill).

**Authorization:**
- `admin` (de su empresa) y `super-admin` SHALL editar el flag; `employee` no.
- Cross-company: un `admin` NO SHALL editar el flag de un empleado de otra empresa.

#### Scenario: Empleado mensual nuevo nace con auxilio activado
- **WHEN** se crea un empleado en modo `monthly`
- **THEN** `receives_transport_allowance` es `true` por defecto

#### Scenario: Empleado por horas no recibe auxilio
- **WHEN** se genera el reporte de un empleado en modo `hourly`
- **THEN** el auxilio de transporte es `0`
- **AND** no aparece como concepto en el reporte

#### Scenario: Admin desactiva el auxilio de un empleado mensual
- **WHEN** un `admin` apaga `receives_transport_allowance` de un empleado `monthly`
- **THEN** los reportes de ese empleado dejan de sumar y mostrar el auxilio

#### Scenario: Backfill activa el auxilio a empleados mensuales existentes
- **WHEN** se ejecuta la migración del cambio
- **THEN** todos los empleados `monthly` existentes quedan con `receives_transport_allowance = true`

### Requirement: Prorrateo del auxilio de transporte por mes comercial de 30 días

En modo `monthly` y con el flag activo, el sistema SHALL prorratear el auxilio de transporte del periodo usando el mismo mes comercial de 30 días (15 por quincena) que el salario base, de modo que el monto no dependa de los días calendario reales.

**Business Rules:**
- `auxilio_periodo = transport_allowance × (días_comerciales_pagables / 30)`, con la misma cuenta de días comerciales que el salario base (ver `payroll-pay-period`).
- Para un periodo completo (quincena o mes íntegro) el auxilio se paga proporcional completo: quincena = `transport_allowance / 2`, mes = `transport_allowance`.
- Un rango parcial prorratea proporcional a los días comerciales del rango.
- En esta fase los días pagables son los del rango; NO se descuentan ausencias (misma simplificación del salario base).
- El auxilio NO SHALL ser base de recargos ni de horas extra, y NUNCA SHALL multiplicarse por horas.

**Authorization:**
- Sin cambios respecto al acceso a reportes existente.

#### Scenario: Quincena completa paga medio auxilio
- **WHEN** se calcula el reporte de un empleado `monthly` con auxilio para una quincena completa
- **THEN** el auxilio del periodo es `transport_allowance / 2`
- **AND** es el mismo monto en febrero (28 días) y en octubre (31 días)

#### Scenario: Mes completo paga auxilio íntegro
- **WHEN** se calcula el reporte de un empleado `monthly` con auxilio para un mes completo
- **THEN** el auxilio del periodo es `transport_allowance`

#### Scenario: Rango parcial prorratea el auxilio
- **WHEN** se calcula el reporte de un empleado `monthly` con auxilio que trabajó del día 1 al día 8 de una quincena
- **THEN** el auxilio del periodo es `transport_allowance × (8 / 30)`

### Requirement: Exposición del auxilio como concepto propio en los reportes

El sistema SHALL exponer el auxilio de transporte como una línea propia "Auxilio de transporte" en los reportes de empleado y de empresa, tanto en PDF como en Excel, separada del salario base, los recargos y las horas extra. El auxilio SHALL sumarse al `total` del costo.

**Business Rules:**
- En el reporte de empleado, la línea del auxilio SHALL mostrarse solo en modo `monthly` cuando el empleado lo recibe, junto a la línea de salario base.
- En el reporte de empresa, el auxilio agregado SHALL ser la suma del auxilio de todos los empleados que lo reciben en el periodo.
- El `total` del reporte SHALL incluir el auxilio del periodo.
- Si el monto del auxilio del periodo es `0` (modo `hourly`, flag apagado o valor cero), la línea NO SHALL mostrarse.

**Authorization:**
- El acceso a los reportes mantiene las reglas de rol existentes.

#### Scenario: La línea del auxilio aparece en el reporte de empleado mensual
- **WHEN** se genera el PDF o Excel de un empleado `monthly` con auxilio
- **THEN** aparece una línea "Auxilio de transporte" con el monto del periodo
- **AND** el `total` del reporte incluye ese monto

#### Scenario: El reporte de empresa suma el auxilio de todos los empleados
- **WHEN** se genera el reporte de empresa de un periodo
- **THEN** el auxilio de transporte agregado es la suma del auxilio de los empleados que lo reciben
- **AND** se muestra como concepto propio en PDF y Excel

#### Scenario: No se muestra la línea cuando el auxilio es cero
- **WHEN** se genera el reporte de un empleado `hourly` o de un empleado `monthly` con el flag apagado
- **THEN** no aparece la línea "Auxilio de transporte"
