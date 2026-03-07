# Phase 4: Motor de Cálculo de Horas - Legislación Colombiana

## Resumen

Implementación del motor de cálculo automático de horas laborales según la legislación colombiana. Al hacer clock-out, el sistema clasifica las horas trabajadas en: regulares diurnas, nocturnas, dominicales/festivas y horas extra. Se agregan interfaces de admin para gestionar las reglas de recargo y el calendario de festivos.

---

## Archivos Creados

### Backend

| Archivo | Descripción |
|---|---|
| `app/Domain/TimeTracking/Actions/CalculateWorkHours.php` | Motor principal de cálculo de horas |
| `app/Domain/Company/Observers/CompanyObserver.php` | Seed automático al crear empresa |
| `database/seeders/ColombianHolidaysSeeder.php` | Festivos colombianos 2026, método `seedForCompany(int $id)` |
| `app/Http/Controllers/Settings/SurchargeRuleController.php` | Controlador edit + update para recargos |
| `app/Http/Controllers/Settings/HolidayController.php` | Controlador CRUD para festivos |
| `app/Http/Requests/Settings/UpdateSurchargeRuleRequest.php` | Validación de recargos |
| `app/Http/Requests/Settings/StoreHolidayRequest.php` | Validación al crear festivo |
| `app/Http/Requests/Settings/UpdateHolidayRequest.php` | Validación al actualizar festivo |
| `tests/Feature/WorkHourCalculationTest.php` | 11 tests del motor de cálculo |
| `tests/Feature/Settings/SurchargeRuleControllerTest.php` | 4 tests del controlador de recargos |
| `tests/Feature/Settings/HolidayControllerTest.php` | 9 tests del controlador de festivos |

### Frontend

| Archivo | Descripción |
|---|---|
| `resources/js/pages/settings/SurchargeRules.vue` | Página de edición de recargos salariales |
| `resources/js/pages/settings/Holidays.vue` | Página CRUD de festivos con edición inline |

---

## Archivos Modificados

| Archivo | Cambio |
|---|---|
| `app/Domain/TimeTracking/Actions/ClockOut.php` | Inyecta `CalculateWorkHours` y lo invoca tras el update |
| `app/Providers/AppServiceProvider.php` | Registra `CompanyObserver` |
| `database/seeders/DemoSeeder.php` | Elimina `SurchargeRule::create` manual (lo maneja el observer) |
| `routes/settings.php` | Agrega rutas admin para surcharge-rules y holidays |
| `resources/js/layouts/settings/Layout.vue` | Agrega nav items de admin (Recargos, Festivos) |

---

## Algoritmo: CalculateWorkHours

**Input:** `TimeEntry` con `clock_in`, `clock_out`, `gross_hours` y `net_hours` ya calculados.

### Lógica

1. Carga `SurchargeRule` de la empresa y el timezone (default `America/Bogota`)
2. Calcula horas previas en la semana (lunes–domingo) para determinar overtime acumulado
3. Calcula `netRatio = net_hours / gross_hours` para distribuir breaks proporcionalmente
4. Itera minuto a minuto desde `clock_in` hasta `clock_out`, clasificando cada minuto:
   - **Overtime** si `accumulatedNetMinutes >= weeklyLimitMinutes`
   - **Dominical/Festivo** si es domingo o la fecha está en el listado de festivos
   - **Nocturno** si la hora es `>= 21:00` o `< 06:00`
   - **Regular** en cualquier otro caso
5. Actualiza `regular_hours`, `night_hours`, `sunday_holiday_hours`, `overtime_hours` y `status = 'calculated'`

### Casos borde manejados
- Turnos que cruzan medianoche (el loop minuto a minuto lo maneja naturalmente)
- Festivos recurrentes: se comparan por `mm-dd` ajustado al año del clock_in
- Sin clock_out o gross_hours == 0: retorna sin cambios

---

## CompanyObserver

Al crear una `Company`, automáticamente:
1. Crea `SurchargeRule` con los defaults colombianos (definidos en la migración)
2. Siembra los festivos colombianos del año actual vía `ColombianHolidaysSeeder::seedForCompany()`

---

## Festivos Colombianos 2026

**Fijos (recurrentes año a año):** Año Nuevo (01-01), Día del Trabajo (05-01), Día de la Independencia (07-20), Batalla de Boyacá (08-07), Inmaculada Concepción (12-08), Navidad (12-25).

**Móviles 2026 (no recurrentes):** Reyes Magos (01-12), San José (03-23), Jueves Santo (04-02), Viernes Santo (04-03), Ascensión (05-25), Corpus Christi (06-15), Sagrado Corazón (06-22), San Pedro y San Pablo (07-06), Asunción (08-17), Día de la Raza (10-12), Todos los Santos (11-02), Independencia de Cartagena (11-16).

---

## Rutas Agregadas

```
GET  /settings/surcharge-rules    surcharge-rules.edit
PUT  /settings/surcharge-rules    surcharge-rules.update
GET  /settings/holidays           holidays.index
POST /settings/holidays           holidays.store
PUT  /settings/holidays/{holiday} holidays.update
DEL  /settings/holidays/{holiday} holidays.destroy
```

Todas requieren middleware `auth`, `verified` y `role:admin|super-admin`.

---

## Tests

```bash
# Motor de cálculo
php artisan test --compact tests/Feature/WorkHourCalculationTest.php

# Controladores
php artisan test --compact tests/Feature/Settings/SurchargeRuleControllerTest.php
php artisan test --compact tests/Feature/Settings/HolidayControllerTest.php

# Suite completa
php artisan test --compact
```

**Resultado:** 90 tests, 251 assertions — todos pasan.

### Casos cubiertos en WorkHourCalculationTest

| Test | Escenario |
|---|---|
| `test_regular_daytime_shift` | Lun 08:00–16:00 → `regular = 8.00` |
| `test_night_hours_crossing_threshold` | Lun 20:00–22:00 → `regular = 1.00`, `night = 1.00` |
| `test_shift_crossing_midnight` | Lun 22:00–Mar 02:00 → `night = 4.00` |
| `test_sunday_daytime_shift` | Dom 08:00–16:00 → `sunday_holiday = 8.00` |
| `test_holiday_daytime_shift` | Festivo diurno → `sunday_holiday = 8.00` |
| `test_overtime_partial` | 40h previas + 4h turno → `regular = 2.00`, `overtime = 2.00` |
| `test_all_overtime_when_exceeds_weekly_limit` | 44h+ previas → `overtime = 8.00` |
| `test_breaks_applied_proportionally` | 9h brutas, 1h break, diurno → `regular = 8.00` |
| `test_no_calculation_without_clock_out` | Sin clock_out → sin cambios |
| `test_recurring_holiday_matched_by_month_day` | Festivo recurrente (año distinto) reconocido |
| `test_clock_out_integration_stores_calculated_hours` | ClockOut llama al motor y persiste `status = calculated` |
