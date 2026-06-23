## 1. Migraciones y schema

- [x] 1.1 Migración: agregar `dominical_weekday` (tinyint, default 0), `pay_dominical_by_default` (boolean, default true), `default_dominical_payment_mode` (string, default `hour`), `default_dominical_day_value` (decimal 12,2, default 0) a `surcharge_rules`, con `down()`
- [x] 1.2 Migración: agregar `dominical_payment_mode` (string, default `hour`) y `dominical_day_value` (decimal 12,2, default 0) a `employees`, con `down()`
- [x] 1.3 Migración: renombrar en `time_entries` `sunday_holiday_hours`→`dominical_hours`, `night_sunday_hours`→`night_dominical_hours`, `overtime_day_sunday_hours`→`overtime_day_dominical_hours`, `overtime_night_sunday_hours`→`overtime_night_dominical_hours`; agregar `holiday_hours`, `night_holiday_hours`, `overtime_day_holiday_hours`, `overtime_night_holiday_hours` (decimal 5,2 default 0). El `down()` SHALL re-sumar `holiday→dominical` (y nocturnas/overtime equivalentes) antes de dropear, para restaurar el estado fusionado
- [x] 1.4 Migración: crear tabla `dominical_payment_decisions` (`company_id` FK, `employee_id` FK **NOT NULL**, `start_date`, `end_date`, `payable_count` unsignedInteger nullable, `exported_by` FK users nullable, `exported_at` timestamp, timestamps) con índice único `(company_id, employee_id, start_date, end_date)`
- [x] 1.5 Ejecutar migraciones y actualizar `ai-specs/specs/data-model.md`. NOTA: sin recálculo histórico — los turnos viejos conservan el valor fusionado en `*_dominical` y las `*_holiday` quedan en 0

## 2. Clasificación (CalculateWorkHours)

- [x] 2.1 Leer `dominical_weekday` de `SurchargeRule` (default 0); separar `$isDominical` (día == dominical_weekday) de `$isHoliday` (fecha en festivos)
- [x] 2.2 Implementar precedencia festivo > dominical y clasificar en los 12 buckets; persistir las 12 columnas
- [x] 2.3 Tests unitarios `CalculateWorkHours` (ver §11.1)

## 3. Modelos, factories y seeders

- [x] 3.1 `TimeEntry`: actualizar `$fillable` (4→12 columnas premium) y `casts()` (4→12, con comentarios por tipo)
- [x] 3.2 `SurchargeRule`: agregar los 4 campos nuevos a `$fillable` y `casts()`; actualizar `SurchargeRuleFactory`, seeder y `CompanyObserver` para sembrar defaults
- [x] 3.3 `Employee`: agregar `dominical_payment_mode`/`dominical_day_value` a `$fillable` y `casts()`; actualizar `EmployeeFactory`
- [x] 3.4 `CreateEmployee`: sembrar `dominical_payment_mode`/`dominical_day_value` desde los defaults (molde `hourly_rate ?? default_hourly_rate`)
- [x] 3.5 Crear modelo `DominicalPaymentDecision` (`BelongsToCompany`, fillable, casts `start_date`/`end_date` date + `exported_at` datetime, relaciones `employee()`/`exportedBy()` con return types) + factory
- [x] 3.6 `TimeEntryFactory` y `ReportDemoSeeder`: actualizar a las 12 columnas
- [x] 3.7 Actualizar `ai-specs/specs/domain-model.md`

## 4. Cálculo de costos (CalculateReportCosts)

- [x] 4.1 Aceptar los 12 tipos de hora; familias `*_dominical` y `*_holiday` con el mismo % de recargo dominical; `details[]` con 12 items
- [x] 4.2 Modo `day`: base de `dominical_hours` como `regular` y de `night_dominical_hours` como `night`, MÁS `min(K,N) × dominical_day_value`; `overtime_*_dominical` sin afectar. Modo `hour`: comportamiento actual
- [x] 4.3 Festivos siempre pagan (no afectados por switch/conteo); overtime conserva `payOvertime` sobre las 6 categorías
- [x] 4.4 Dominical no pagado (hour mode o switch off) → diurno a `regular`, nocturno a `night` (conserva nocturno, pierde dominical)
- [x] 4.5 Tests unitarios `CalculateReportCosts` (ver §11.2)

## 5. Resolución y agregación de reportes

- [x] 5.1 Crear `ResolveDominicalPaymentDecision` (molde `ResolveOvertimePaymentDecision`): precedencia request `dominical_payable_count` → decisión guardada → todos los N. Solo por empleado
- [x] 5.2 `GenerateEmployeeReport`: sumar las 12 columnas (`aggregateTotals` selectRaw, array `totals`, `mapDay()` `$hours[]`, 3 PHPDoc shapes); contar N = `entry.date` distintos con horas dominicales > 0; pasar modo/valor/K a `CalculateReportCosts`
- [x] 5.3 `GenerateCompanyReport`: sumar las 12 columnas (selectRaw, `includeMonthlyEmployeesWithoutEntries`, `sumEmployeeTotals`); resolver por cada empleado su decisión dominical guardada (o default); sin control global
- [x] 5.4 Tests de agregación (ver §11.3)

## 6. Form Requests y controllers

- [x] 6.1 `UpdateSurchargeRuleRequest` + `Settings/SurchargeRuleController@update`: aceptar `dominical_weekday` (0–6), `pay_dominical_by_default` (boolean), `default_dominical_payment_mode` (`in:hour,day`), `default_dominical_day_value` (numeric ≥0) con mensajes
- [x] 6.2 Request de empleado (store/update): aceptar `dominical_payment_mode` (`in:hour,day`) / `dominical_day_value` (numeric ≥0)
- [x] 6.3 `ReportFilterRequest`: aceptar `dominical_payable_count` (nullable integer ≥0)
- [x] 6.4 `ReportController`: en `employee()` resolver K (sin persistir) y pasarlo como prop; en `exportEmployee Excel/Pdf` hacer `updateOrCreate` con `employee_id`; en `company()`/exports de empresa NO persistir K y resolver por empleado; pasar modo/valor/K a los generadores
- [x] 6.5 `Admin/TimeEntryController`: agregar las 4 columnas `*_holiday` al payload del modal de edición
- [x] 6.6 `php artisan wayfinder:generate`
- [x] 6.7 Tests feature controllers (ver §11.4)

## 7. Frontend — ajustes y empleado

- [x] 7.1 `SurchargeRules.vue`: selector de día dominical, switch "pagar dominicales por defecto", modo de pago default y valor por día default
- [x] 7.2 Formulario de empleado: modo de pago dominical y valor por día (default sembrado visible)

## 8. Frontend — reportes y desglose diario

- [x] 8.1 `Reports/Employee.vue`: filas separadas de recargo dominical y festivo; control "pagar K de N" (solo modo `day`, deshabilitado en `hour`); incluir `dominical_payable_count` en requests de export
- [x] 8.2 `Reports/Company.vue`: filas dominical/festivo separadas; SIN control de conteo (solo totales)
- [x] 8.3 `DailyWorkTable.vue` / `DailyWorkDayDetail.vue`: sumar las 8 columnas premium para el tipo de día y **separar el badge festivo del badge dominical** (hoy mezclados)
- [x] 8.4 Claves i18n nuevas en `es.json`/`en.json` (dominical, festivo, modo, valor por día, conteo)

## 9. Exports (Excel + PDF)

- [x] 9.1 `EmployeeReportExport` y `CompanyReportExport`: 12 tipos, festivo y dominical separados, plus por día en dominical
- [x] 9.2 Vistas Blade `exports.employee-report` y `exports.company-report`: mismo tratamiento
- [x] 9.3 `npm run build`

## 10. Cierre y barrido

- [x] 10.1 `vendor/bin/pint --dirty --format agent`
- [x] 10.2 Barrido final `grep -r "sunday"` en `app/ resources/ database/ tests/` — cero referencias residuales a las columnas viejas de `time_entries` (ojo: NO renombrar las columnas de % en `surcharge_rules` `sunday_holiday`/`night_sunday`/`overtime_*_sunday`, que siguen siendo el % de recargo compartido por dominical y festivo)
- [x] 10.3 `php artisan test --compact` de los archivos afectados + suite completa

## 11. Plan de tests (asserts fuertes a BD y datos)

### 11.1 `CalculateWorkHoursTest` (unit/feature) — actualizar `WorkHourCalculationTest`
- [x] Dominical configurable: con `dominical_weekday=2`, turno martes diurno → `assertEquals` `dominical_hours` esperado y `regular_hours=0`; turno domingo → `regular_hours` esperado y `dominical_hours=0` (`assertDatabaseHas` con las 12 columnas)
- [x] Festivo en familia holiday: turno en festivo → `holiday_hours` esperado, `dominical_hours=0`
- [x] Festivo que cae en día dominical → gana festivo: `holiday_hours>0`, `dominical_hours=0`
- [x] Nocturno dominical: `night_dominical_hours` correcto, no en `night_hours` ni `dominical_hours`
- [x] Cruce de medianoche sábado→dominical y dominical→lunes: reparto exacto entre `night_hours`/`night_dominical_hours`
- [x] Invariante: `assertEqualsWithDelta(net_hours, suma de las 12 columnas, 0.01)` en cada caso
- [x] Compañía sin config (default): `dominical_weekday=0` → comportamiento histórico (domingo)

### 11.2 `CalculateReportCostsTest` (unit) — extender el existente (56 refs a migrar)
- [x] `details[]` tiene exactamente 12 items con los nuevos tipos
- [x] Modo `hour`, hourly: `dominical = hours × rate × (1+%)`; assert subtotal exacto
- [x] Modo `day`, hourly: base dominical como regular + nocturna como night + `min(K,N)×day_value`; assert cada componente y el total
- [x] Modo `day`, monthly: solo `min(K,N)×day_value` (base 0); assert
- [x] K<N en modo `day`: assert plus = `K×day_value` y base intacta
- [x] Festivo siempre paga aunque `payDominical=false` y aunque K=0: `holiday>0` en total
- [x] Dominical no pagado (hour mode, switch off): diurno→regular, nocturno→night (conserva nocturno); assert subtotales
- [x] Overtime: `payOvertime=false` pone en 0 las 6 categorías overtime (incl. `*_dominical`/`*_holiday`) con `compensated:true`; horas intactas
- [x] Tipos con 0 horas → subtotal 0 pero presentes en `details`

### 11.3 `GenerateEmployeeReportTest` / `GenerateCompanyReportTest` (feature)
- [x] `totals` expone las 12 columnas con sumas correctas (`assertEquals` por campo)
- [x] Conteo N: empleado con 3 fechas dominicales distintas → N=3; dos turnos mismo día → N=1
- [x] Reporte empleado modo `day` con K=2: `cost_summary` refleja 2 plus; con default refleja N plus
- [x] Reporte empresa: respeta la decisión guardada por empleado (uno con K=1, otro sin decisión → todos); total empresa == suma de desprendibles individuales (`assertEqualsWithDelta`)
- [x] Festivos suman siempre en ambos reportes
- [x] Sin N+1: `DB::listen`/`assertQueryCount` razonable en el breakdown

### 11.4 Feature controllers
- [x] `SurchargeRuleControllerTest`: admin y super-admin guardan los 4 campos (`assertDatabaseHas`); `employee`→403; cross-company→403; validación de `dominical_weekday` fuera de 0–6 y `payment_mode` inválido → 422
- [x] Empleado store/update con modo/valor → `assertDatabaseHas('employees', ...)`; al crear sin valores hereda defaults (assert sembrado)
- [x] `ReportControllerTest`: exportar reporte de empleado → `assertDatabaseHas('dominical_payment_decisions', ['employee_id'=>..., 'payable_count'=>2, ...])`; ver reporte → `assertDatabaseMissing`; reexportar con otro K → fila única actualizada (`assertDatabaseCount`=1); exportar reporte de empresa → `assertDatabaseMissing` (no persiste)
- [x] Aislamiento multi-tenant: admin de B no ve/sobrescribe decisión de A
- [x] Precarga: con decisión guardada, la prop del control llega con el K guardado; sin decisión, con N

### 11.5 Regresión / exports
- [x] `ReportExportTest`: Excel y PDF incluyen filas separadas dominical y festivo; no referencian columnas viejas
- [x] `PaidBreakOverageTest` / `NegativeBreakDurationRegressionTest`: siguen verdes tras el rename (net_hours = suma 12; overage no afecta clasificación)
