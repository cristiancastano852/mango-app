## Context

El costo laboral en los reportes (`GenerateEmployeeReport`, `GenerateCompanyReport`) se compone hoy en `CalculateReportCosts`, que en modo `monthly` suma el salario base prorrateado (`CalculatePeriodBaseSalary`) más recargos y horas extra. El SMLV vive en `config/payroll.php` y se siembra por empresa en `SurchargeRule.default_monthly_salary` vía `CompanyObserver`. El auxilio de transporte está explícitamente fuera de alcance (comentario en `config/payroll.php`).

El auxilio es estructuralmente **gemelo del salario base**: se prorratea con el mismo mes comercial de 30 días y se expone como concepto propio, pero NO es base de recargos/extras ni se multiplica por horas. La diferencia es que su elegibilidad se controla con un flag por empleado y su valor es configurable por empresa.

Restricciones: multi-tenancy (`company_id` scope vía `BelongsToCompany`), `super-admin` con `company_id = null`, observers usan `withoutGlobalScopes()`, `CarbonImmutable` activo (cuidado con mutación de fechas), tests obligatorios por rol.

## Goals / Non-Goals

**Goals:**
- Configurar el auxilio: default global en `config/payroll.php` + valor por empresa en `surcharge_rules.transport_allowance`.
- Flag por empleado `receives_transport_allowance` como interruptor de elegibilidad (solo `monthly`).
- Prorratear el auxilio reutilizando la lógica de mes comercial existente.
- Sumar el auxilio al `total` y exponerlo como línea propia en reportes de empleado y empresa (PDF + Excel).
- Backfill seguro de empleados `monthly` existentes.

**Non-Goals:**
- Verificación automática del tope de 2 SMLV (lo decide el flag).
- Descuento por ausencias/incapacidades.
- Impacto en prestaciones (cesantías/prima).
- Auxilio en modo `hourly`.

## Decisions

### 1. Reutilizar el prorrateo de mes comercial en lugar de duplicarlo
`CalculatePeriodBaseSalary` ya expone `commercialDaysBetween()` y la fórmula `monto × días/30`. El auxilio usa exactamente la misma cuenta de días. **Decisión:** reutilizar `CalculatePeriodBaseSalary::execute()` pasándole `transport_allowance` como "monto mensual" para obtener el auxilio del periodo, en `GenerateEmployeeReport`/`GenerateCompanyReport`. No se crea una clase nueva de prorrateo.
- *Alternativa descartada:* nueva `CalculatePeriodTransportAllowance` — sería un duplicado exacto de la fórmula; el método actual es semánticamente "prorratear monto mensual por mes comercial", aplicable a ambos.

### 2. El auxilio entra a `CalculateReportCosts` como parámetro, no se calcula adentro
`CalculateReportCosts` es una función pura de cálculo de costo por horas; el prorrateo y la elegibilidad (flag, modo) se resuelven en los Generators que tienen acceso al `Employee` y al `SurchargeRule`. **Decisión:** añadir parámetro `float $transportAllowance = 0.0` a `CalculateReportCosts::execute()`, sumarlo al `total` y exponerlo como **clave top-level** `transport_allowance` del retorno. Los Generators calculan el monto (0 si `hourly`, flag off, o valor cero) y lo pasan.
- *Nota de implementación:* el auxilio NO se agrega a `details[]`; se expone como clave propia igual que `base`, para preservar el invariante de que `details[]` contiene exactamente los 8 tipos de hora (hay un test que lo afirma) y para ser consistente con cómo ya se trata el salario base.
- *Alternativa descartada:* pasar el `Employee`/`SurchargeRule` a `CalculateReportCosts` — rompería su pureza y su firma actual basada en primitivas.

### 3. Flag en `employees`, valor en `surcharge_rules`
El valor es política de empresa (uniforme) → `surcharge_rules.transport_allowance` (decimal), sembrado por `CompanyObserver` desde `config('payroll.transport_allowance_monthly')`. La elegibilidad es por persona → `employees.receives_transport_allowance` (boolean, default `true`).
- *Alternativa descartada:* monto por empleado — el auxilio legal es un valor único; un monto por empleado invita a inconsistencias sin necesidad.

### 4. Default del flag y backfill
Nuevos empleados `monthly` nacen con el flag en `true` (en `CreateEmployee`/Form Request defaults). La migración hace `update` de `receives_transport_allowance = true` para empleados `monthly` existentes. Para `hourly` el flag es irrelevante (el cálculo lo ignora), así que su valor de columna no afecta.

### 5. Exposición en reportes: línea condicional como el salario base
El patrón ya existe: la fila "Salario base del periodo" se pinta solo en modo `monthly`. **Decisión:** añadir una fila/row hermana "Auxilio de transporte" condicionada a `cost_summary['transport_allowance'] > 0`, en los 2 blades y los 2 Exports. `cost_summary` gana la clave `transport_allowance`. El reporte de empresa agrega la suma del auxilio de todos los empleados que lo reciben.

## Risks / Trade-offs

- **[Prorrateo legal del auxilio solo cubre días trabajados]** → El auxilio sigue la misma simplificación del salario base (prorratea por rango, ausencias fuera de alcance). Consistente y documentado; se revisará en la fase de novedades/ausencias.
- **[Backfill activa auxilio a empleados que quizá no deberían recibirlo]** → Es la decisión explícita ("ON a monthly existentes"); el admin puede apagar el flag caso por caso tras el despliegue. La migración es reversible (la columna se elimina en `down`).
- **[Doble fuente de verdad valor: config vs surcharge_rules]** → El config solo siembra; la verdad operativa es `surcharge_rules.transport_allowance`. Mismo patrón ya aceptado para el SMLV/`default_monthly_salary`.
- **[Cambia el `total` de reportes históricos para empleados monthly]** → Esperado: el `total` ahora refleja el costo real. Los reportes se generan on-demand, no hay datos persistidos que migrar.

## Migration Plan

1. Migración: `surcharge_rules.transport_allowance` (decimal, default 0 o nullable) y `employees.receives_transport_allowance` (boolean, default true). En el mismo `up`, backfill `update` de `transport_allowance` a las `surcharge_rules` existentes con `config('payroll.transport_allowance_monthly')` y `receives_transport_allowance = true` a empleados `monthly` existentes.
2. `down`: eliminar ambas columnas.
3. Sin downtime: columnas con default; el cálculo trata `0`/`false` como "sin auxilio".
4. Rollback: revertir migración elimina columnas; el código tolera su ausencia solo en versiones previas (desplegar código y migración juntos).

## Open Questions

- Ninguna pendiente. Las decisiones de elegibilidad (sin tope 2 SMLV, flag por empleado), default (ON) y backfill (ON a monthly) fueron confirmadas en exploración.
