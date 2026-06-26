## Context

El reporte individual de empleado se genera en `GenerateEmployeeReport`, que delega el desglose de costos a `CalculateReportCosts::execute()`. Este último produce el `cost_summary` con el `total` devengado (base + recargos + horas extras + auxilio de transporte). El mismo `cost_summary` alimenta tres consumidores: la vista `Reports/Employee.vue`, el PDF (`resources/views/exports/employee-report.blade.php`) y el Excel (`EmployeeReportExport`). Las constantes de nómina (SMLV, auxilio, divisor) ya viven en `config/payroll.php`.

Falta reflejar la deducción de seguridad social a cargo del empleado (4% salud + 4% pensión sobre el IBC) y el neto a pagar resultante.

## Goals / Non-Goals

**Goals:**
- Calcular `social_security_base` (IBC), `health_deduction`, `pension_deduction` y `net_pay` dentro de `CalculateReportCosts`, sin alterar el `total` devengado.
- Mantener las tasas en `config/payroll.php` (env-overridable), hoy fijas en 4% y 4%.
- Mostrar las nuevas filas en vista, PDF y Excel del reporte individual.

**Non-Goals:**
- Reporte de empresa y sus exports.
- Piso/techo del IBC, fondo de solidaridad pensional, salario integral.
- Toggle por empresa (se asume universo colombiano).
- Persistir datos o migrar BD.

## Decisions

**1. IBC = total − auxilio de transporte.** Es la única partida no salarial del `total`, y por ley el auxilio no integra el IBC. La equivalencia funciona uniforme en `monthly` (IBC = base+recargos+extras) y `hourly` (IBC = total, sin auxilio). Evita re-sumar línea por línea y se mantiene robusto si en el futuro cambian los renglones de recargo.

**2. Las tasas se inyectan, no se leen dentro del Action.** `CalculateReportCosts` recibe un nuevo parámetro `array $socialSecurity = ['health' => float, 'pension' => float]` (porcentajes). `GenerateEmployeeReport` lo arma desde `config('payroll.social_security')`. Esto respeta la regla del proyecto de no usar `config()`/`env()` dentro del dominio y mantiene el Action determinista para tests.

**3. Forma de los nuevos campos en `cost_summary`.** Se añaden cuatro claves planas: `social_security_base`, `health_deduction`, `pension_deduction`, `net_pay`, todas redondeadas a 2 decimales. No se tocan `total`, `details` ni los renglones existentes — los consumidores actuales siguen igual.

**4. Redondeo.** Cada deducción se redondea independientemente (`round(IBC × rate/100, 2)`) y `net_pay = round(total − health_deduction − pension_deduction, 2)`. Consistente con el resto de `CalculateReportCosts`.

**5. Config nuevo.** En `config/payroll.php`:
```php
'social_security' => [
    'health'  => (float) env('PAYROLL_SS_HEALTH_RATE', 4),
    'pension' => (float) env('PAYROLL_SS_PENSION_RATE', 4),
],
```
con su bloque de comentario explicando que es el aporte del empleado sobre el IBC y que el auxilio de transporte se excluye.

**6. Presentación.** En los tres consumidores, debajo del renglón "Total" se agregan: aporte salud, aporte pensión y "Neto a pagar". La etiqueta i18n del total existente se ajusta para denotar "devengado" (antes de deducción). Nuevas claves i18n en `es.json` y `en.json`.

**7. Sin Form Request.** No hay nueva entrada de usuario ni endpoint nuevo; se reutilizan los controladores y `ReportFilterRequest` actuales. La regla de "incluir Form Request" no aplica porque no hay validación nueva.

## Risks / Trade-offs

- **IBC simplificado**: omitir piso/techo y fondo de solidaridad puede dar deducciones ligeramente distintas a la nómina real en casos extremos (salario mínimo prorrateado por debajo del piso, o ingresos ≥ 4 SMLMV). Aceptado para v1 y documentado en Non-goals.
- **Confusión "Total"**: si la etiqueta del total no se ajusta, el usuario podría leerlo como neto final. Mitigado renombrando la etiqueta a "Total devengado" y mostrando explícitamente "Neto a pagar".
- **Triple presentación**: vista, PDF y Excel deben mantenerse en sync. Mitigado porque los tres leen el mismo `cost_summary`; el riesgo se limita a etiquetas/formato, cubierto por tests de export.
