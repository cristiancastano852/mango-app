## 1. Configuración del modo (BD + modelo)

- [x] 1.1 Migración: agregar `overtime_accrual_mode` string default `'daily'` a `surcharge_rules`; actualizar `ai-specs/specs/data-model.md`
- [x] 1.2 `SurchargeRule`: agregar `overtime_accrual_mode` a `$fillable` (string, sin cast especial)
- [x] 1.3 `SurchargeRuleFactory`: default `overtime_accrual_mode = 'daily'` + estado `weekly()`
- [x] 1.4 Test: empresa nueva y migración de empresa existente quedan en `daily`

## 2. Motor de cálculo (modo semanal)

- [x] 2.1 `CalculateWorkHours`: leer `overtime_accrual_mode` de las reglas y hacer `$isOvertime` condicional (en `weekly` solo dispara el tope semanal)
- [x] 2.2 `CalculateWorkHours`: omitir el breakpoint diario cuando el modo es `weekly` (conservar semanal/noche/medianoche)
- [x] 2.3 Test: días desbalanceados sin exceder tope semanal → 0 overtime (modo `weekly`)
- [x] 2.4 Test: 45h en la semana con tope 42h → `overtime_day_hours = 3.0` (modo `weekly`)
- [x] 2.5 Test: 10h en un día con tope diario 8h en modo `weekly` → `overtime_day_hours = 0`, `regular_hours = 10.0`
- [x] 2.6 Test de regresión: modo `daily` conserva el doble trigger actual (sin cambios)

## 3. Ventana de liquidación "dueño del domingo"

- [x] 3.1 Action `ResolveOvertimeSettlementWindow` en `TimeTracking/Actions`: dado `[inicio, fin]` y el modo, devuelve la ventana de extra (lunes del primer domingo dueño → último domingo dueño); en `daily` devuelve `[inicio, fin]`
- [x] 3.2 Test unitario de la action: cierre a mitad de semana, periodo siguiente con extra diferido, periodo sin domingo, modo `daily`
- [x] 3.3 `GenerateEmployeeReport`: sumar columnas de overtime con la ventana de extra y las columnas base con `[inicio, fin]`; exponer en el payload `overtime_accrual_mode`, fechas de la ventana de extra y flag de extra diferido
- [x] 3.4 `GenerateCompanyReport`: aplicar la misma ventana de extra al agregado de empresa
- [x] 3.5 Test: reporte en modo `weekly` con cierre miércoles paga solo semanas cuyo domingo cae en el periodo
- [x] 3.6 Test: el periodo siguiente incluye el extra diferido de la semana partida
- [x] 3.7 Test: periodo sin domingo → overtime del periodo = 0
- [x] 3.8 Test: las horas base de la semana de cierre se incluyen por fecha (no se difieren)
- [x] 3.9 Test de regresión: modo `daily` produce los mismos totales que hoy

## 4. Form Request y configuración (admin)

- [x] 4.1 `UpdateSurchargeRuleRequest`: regla `overtime_accrual_mode` requerida `in:daily,weekly` + mensaje de error
- [x] 4.2 `SurchargeRuleController`: persistir el campo (sin lógica adicional en el controller)
- [x] 4.3 `php artisan wayfinder:generate`
- [x] 4.4 Test: admin cambia a `weekly`; valor inválido rechazado (422); `employee` 403; cross-company `assertSessionHasErrors`; super-admin actualiza empresa ajena

## 5. Frontend — formulario de Reglas de recargo

- [x] 5.1 Revisar `components/ui/` para el control de selección a reutilizar
- [x] 5.2 Página de Reglas de recargo: selector de modo de acumulación (`daily`/`weekly`) precargado desde el valor actual + i18n
- [x] 5.3 `npm run build`

## 6. Frontend — reporte (banner + desglose diario)

- [x] 6.1 Banner en la página de reporte (empleado y empresa) con el rango de semanas liquidadas y aviso de extra diferido; solo visible en modo `weekly` + i18n
- [x] 6.2 Desglose diario: marcar las filas de la semana de cierre como "extra diferido"
- [x] 6.3 `npm run build`

## 7. Exports

- [x] 7.1 `EmployeeReportExport` y `CompanyReportExport` (PDF/Excel): incluir el banner del rango de extra en modo `weekly`
- [x] 7.2 Test: el export refleja el rango de extra liquidado en modo `weekly`

## 8. Cierre

- [x] 8.1 `vendor/bin/pint --dirty --format agent`
- [x] 8.2 `php artisan test --compact` de los archivos afectados (motor, reporte, settings, exports)
- [x] 8.3 Actualizar `ai-specs/specs/domain-model.md` con la nueva Action `ResolveOvertimeSettlementWindow` y el campo `overtime_accrual_mode`
