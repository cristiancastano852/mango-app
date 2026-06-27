## 1. Migración y modelo

- [x] 1.1 Crear migración que agrega `overtime_payable_hours` (decimal nullable) a `overtime_payment_decisions`
- [x] 1.2 Agregar `overtime_payable_hours` al `$fillable` y `casts()` del modelo `OvertimePaymentDecision`

## 2. Cálculo de costos

- [x] 2.1 Agregar parámetro `overtimePayableHours` (?float) a `CalculateReportCosts::execute()`
- [x] 2.2 Cuando los 3 flags premium (`pay_overtime_dominical`, `pay_overtime_holiday`, `pay_overtime_night`) están en off y `payOvertime` es true, calcular el costo de la bolsa única `effectiveOvertimeDayHours` sobre `overtimePayableHours` (null = todas, 0 = ninguna, puede exceder lo trabajado)
- [x] 2.3 Exponer en el resultado las horas extra pagadas vs. trabajadas para el display (sin alterar `*_hours` de `totals`)
- [x] 2.4 Test unitario de `CalculateReportCosts`: pagar menos, todas (null), 0, sobre-pago, y que con flags premium en on el input se ignora; y que `payOvertime=false` deja $0

## 3. Resolución de la decisión

- [x] 3.1 Crear action `ResolveOvertimePayableHours` (precedencia request → guardado → null; normaliza a `max(0, valor)`), espejo de `ResolveDominicalPaymentDecision`
- [x] 3.2 Test unitario de la precedencia (request manda, guardado, default null, 0 válido)

## 4. Generadores de reporte

- [x] 4.1 Propagar `overtimePayableHours` en `GenerateEmployeeReport::execute()` hacia `CalculateReportCosts`
- [x] 4.2 Propagar el valor por empleado en `GenerateCompanyReport::execute()` (resuelto empleado por empleado)
- [x] 4.3 Test feature: el reporte de empleado y de empresa reflejan el costo recalculado según las horas pagables

## 5. Request, controlador y persistencia

- [x] 5.1 Validar `overtime_payable_hours` en `ReportFilterRequest` (`nullable|numeric|min:0`)
- [x] 5.2 Leer el override en `ReportController` y pasarlo a los generadores
- [x] 5.3 Persistir (upsert) el valor efectivo en `overtime_payment_decisions` al exportar (empleado y empresa), reusando la fila del periodo
- [x] 5.4 Test feature: exportar PDF/Excel persiste el valor; ver en pantalla no persiste; aislamiento multi-tenant; `employee` recibe 403

## 6. Frontend

- [x] 6.1 Agregar input numérico de horas extra pagables en `Reports/Employee.vue`, visible solo cuando los 3 flags premium están en off; inicializar con el valor resuelto y recalcular el total
- [x] 6.2 Misma lógica en `Reports/Company.vue`
- [x] 6.3 Reflejar el número efectivo de horas pagadas y el costo en exports Excel y Blade PDF
- [x] 6.4 Agregar claves i18n (es/en), incluyendo copy que explique la precondición de visibilidad

## 7. Cierre

- [x] 7.1 Correr `vendor/bin/pint --dirty --format agent`
- [x] 7.2 Correr los tests afectados con `php artisan test --compact` y dejar la suite relacionada en verde
