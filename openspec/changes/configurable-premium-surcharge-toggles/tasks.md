## 1. Migración y schema

- [x] 1.1 Migración: agregar `pay_night_dominical`, `pay_night_holiday`, `pay_overtime_dominical`, `pay_overtime_holiday` (boolean, default `true`) a `surcharge_rules`, con `down()`
- [x] 1.2 En la misma migración (paso de datos): `UPDATE surcharge_rules SET pay_night_dominical = pay_dominical_by_default, pay_overtime_dominical = pay_dominical_by_default` para preservar comportamiento (festivos quedan en `true` por default)
- [x] 1.3 Ejecutar migración y actualizar `ai-specs/specs/data-model.md` con los 4 campos

## 2. Modelo y seed

- [x] 2.1 `SurchargeRule`: agregar los 4 campos a `$fillable` y `casts()` (boolean); actualizar `SurchargeRuleFactory` (default `true`)
- [x] 2.2 `CompanyObserver`: los nuevos campos toman su default `true` al crear empresa (verificar; no requiere cambio si se apoya en el default de BD)

## 3. Cálculo de costos (CalculateReportCosts)

- [x] 3.1 Leer los 4 flags de `$rules`. Calcular horas efectivas de `night`, `overtime_day`, `overtime_night` sumando las premium colapsadas (cuando su flag es `false`)
- [x] 3.2 `night`: costo = `(night + ¬pay_night_dominical·night_dominical + ¬pay_night_holiday·night_holiday) × tarifa × (1+nocturno%)`. Los renglones `night_dominical`/`night_holiday` colapsados → `0`
- [x] 3.3 Overtime: `overtime_day`/`overtime_night` absorben las extras dominicales/festivas colapsadas (cuando se paga overtime); renglones premium colapsados → `0`
- [x] 3.4 Partir el branch dominical: `pay_dominical_by_default` solo decide el diurno (`dominical`); `night_dominical` y `ot_*_dominical` ya no dependen de él, sino de sus flags
- [x] 3.5 Verificar interacción con modo por-día (la noche dominical en por-día ya es nocturno normal; el flag de overtime aplica en ambos modos) y con `pay_overtime` (si OFF, overtime $0 y los flags no aplican)
- [x] 3.6 `details[]`: las horas/costo de los premium colapsados se reflejan en el renglón base y el premium queda en `0h/$0`
- [x] 3.7 Tests unitarios (ver §8.1)

## 4. Form Request y controller

- [x] 4.1 `UpdateSurchargeRuleRequest`: agregar los 4 flags (`required`, `boolean`) con mensajes; `Settings/SurchargeRuleController@update` ya pasa `validated()` (verificar)
- [x] 4.2 Tests feature de ajustes (ver §8.2)

## 5. Frontend — ajustes

- [x] 5.1 `SurchargeRules.vue`: 4 checkboxes en la sección de recargos (nocturno dominical, nocturno festivo, extra dominical, extra festivo), con el patrón hidden `value=0` + `Checkbox value=1`; tipo en la interfaz `SurchargeRule`
- [x] 5.2 Textos/ayudas que aclaren "si lo desactivas, se paga como nocturno/extra normal" y la diferencia con "Pagar horas extra" (compensación)
- [x] 5.3 i18n `es.json`/`en.json` si aplica (el archivo de ajustes usa español hardcoded por convención)

## 6. Frontend — reportes y exports

- [x] 6.1 `Reports/Employee.vue` y `Reports/Company.vue`: el desglose ya itera `details[]`; verificar que los renglones premium colapsados (0h/$0) se muestren consistentes o se oculten, y que la base muestre las horas fundidas
- [x] 6.2 `EmployeeReportExport` / `CompanyReportExport` (Excel) y vistas Blade PDF: mismos montos que el reporte (premium fundido en base)
- [x] 6.3 `php artisan wayfinder:generate` (si cambia algo de rutas; probablemente no) y `npm run build`

## 7. Cierre

- [x] 7.1 `vendor/bin/pint --dirty --format agent`
- [x] 7.2 `php artisan test --compact` de los afectados + suite completa

## 8. Plan de tests (asserts fuertes)

### 8.1 `CalculateReportCostsTest` (unit)
- [x] `pay_night_dominical=false`: `(night + night_dominical) × tarifa × (1+noc%)`; `night_dominical` subtotal = 0; assert exacto
- [x] `pay_night_holiday=false`: análogo con `night_holiday`
- [x] Ambos noche en false: `night` absorbe dominical+festivo; assert total
- [x] `pay_overtime_dominical=false`: `overtime_day_dominical`→`overtime_day`, `overtime_night_dominical`→`overtime_night`; assert tarifas base
- [x] `pay_overtime_holiday=false`: análogo con festivo
- [x] Default (todos true): comportamiento idéntico al actual (regression — montos premium intactos)
- [x] `pay_overtime=false` + `pay_overtime_dominical=false`: overtime sigue en $0 (el flag no resucita pago)
- [x] Independencia: `pay_dominical_by_default=false` + `pay_night_dominical=true` → diurno dominical a `regular` pero noche con `night_sunday`
- [x] Independencia inversa: `pay_dominical_by_default=true` + `pay_night_dominical=false` → diurno con recargo, noche como `night`
- [x] Modo por-día: el flag de overtime dominical/festivo aplica; la noche por-día se mantiene como nocturno normal

### 8.2 Feature
- [x] `SurchargeRuleControllerTest`: admin y super-admin actualizan los 4 flags (`assertDatabaseHas`); `employee`→403; cross-company falla; validación boolean → 422
- [x] Migración: test que crea compañías con `pay_dominical_by_default` true/false y verifica el seed (`pay_night_dominical`/`pay_overtime_dominical` = valor previo) — preserva comportamiento
- [x] Reporte end-to-end (`GenerateEmployeeReportTest`): con `pay_night_dominical=false`, el `cost_summary` funde la noche dominical en el nocturno y deja el premium en 0; el total coincide

### 8.3 Regresión
- [x] `ReportExportTest`: Excel y PDF reflejan los montos colapsados; sin renglones premium con costo cuando están colapsados
- [x] Suite existente de dominical/festivo sigue verde (default true = comportamiento actual)
