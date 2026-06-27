## 1. Migración y modelo

- [x] 1.1 Crear migración que agrega `night_settlement_mode` (string, default `immediate`) a `surcharge_rules`
- [x] 1.2 Agregar `night_settlement_mode` al `$fillable` del modelo `SurchargeRule` (y cast si aplica)
- [x] 1.3 Actualizar `SurchargeRuleFactory` con el nuevo campo (default `immediate`)

## 2. Resolver de ventana

- [x] 2.1 Crear action `ResolveNightSettlementWindow` que retorna `{start, end, deferred}`: en `deferred` la ventana es `[inicio−1, fin−1]` con `deferred = true`; en `immediate` es `[inicio, fin]` con `deferred = false`
- [x] 2.2 Test unitario de la ventana: modo diferido corre un día, captura el corte previo, modo inmediato no corre (espejo de `ResolveOvertimeSettlementWindowTest`)

## 3. Cálculo de costos

- [x] 3.1 En `CalculateReportCosts`, separar el costo nocturno de los 3 buckets (`night`, `night_dominical`, `night_holiday`) en: base + remanente premium (`max(0, bucketPct − night_surcharge)`%) por fecha, y componente `night_surcharge`% diferible
- [x] 3.2 Aceptar las horas nocturnas del componente diferible sobre la ventana corrida (parámetro separado de las horas del rango del periodo)
- [x] 3.3 Asegurar que el diferimiento se aplica DESPUÉS del colapso `pay_night_dominical`/`pay_night_holiday`
- [x] 3.4 En modo `immediate` el resultado nocturno es idéntico al actual
- [x] 3.5 Test unitario: día de corte difiere solo el `night_surcharge`%, base y dominical/festivo se quedan, dominical por día completo no se difiere, modo inmediato sin cambio, interacción con flags de colapso

## 4. Generadores de reporte

- [x] 4.1 En `GenerateEmployeeReport`, resolver la ventana nocturna y sumar las horas nocturnas del componente diferible sobre ella (espejo de `overrideOvertimeTotals`); exponer bloque `night_settlement` `{mode, start, end, deferred}`
- [x] 4.2 Marcar `night_deferred` en la fila del día de corte del desglose diario (espejo de `overtime_deferred`)
- [x] 4.3 Misma lógica en `GenerateCompanyReport`
- [x] 4.4 Test feature: el recargo nocturno del día de corte no se paga en el periodo y sí en el siguiente; la base se paga por fecha

## 5. Configuración (request, controlador, settings)

- [x] 5.1 Validar `night_settlement_mode` (`in:immediate,deferred`) en `UpdateSurchargeRuleRequest`
- [x] 5.2 Aceptar y guardar el campo en `Settings/SurchargeRuleController`
- [x] 5.3 Test feature: admin guarda el modo; valor inválido rechazado; `employee` recibe 403; aislamiento multi-tenant

## 6. Frontend

- [x] 6.1 Agregar selector de modo de liquidación nocturna en `settings/SurchargeRules.vue`
- [x] 6.2 Banner del rango nocturno liquidado + aviso de recargo diferido en `Reports/Employee.vue` y `Reports/Company.vue` (reusar el patrón del banner de overtime semanal)
- [x] 6.3 Marca de "recargo nocturno diferido" en la fila del día de corte del desglose diario (`DailyWorkTable.vue`)
- [x] 6.4 Reflejar el banner/aviso en exports Excel y Blade PDF
- [x] 6.5 Agregar claves i18n (es/en)

## 7. Cierre

- [x] 7.1 Correr `vendor/bin/pint --dirty --format agent`
- [x] 7.2 Correr los tests afectados con `php artisan test --compact` y dejar la suite relacionada en verde
