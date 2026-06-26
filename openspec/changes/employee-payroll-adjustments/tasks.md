## 1. Modelo de datos

- [ ] 1.1 Crear migración `employee_adjustments` (`company_id`, `employee_id`, `date`, `type`, `amount` decimal 12,2, `concept`, `note` nullable, `created_by` nullable, timestamps; índice `(company_id, employee_id, date)`; FKs con onDelete apropiado). Ejecutar `php artisan migrate`.
- [ ] 1.2 Actualizar `ai-specs/specs/data-model.md` con la tabla nueva.
- [ ] 1.3 Crear enum `App\Domain\Employee\Enums\AdjustmentType` (`Bonus`, `Deduction`, string-backed).
- [ ] 1.4 Crear modelo `EmployeeAdjustment` (trait `BelongsToCompany`, relaciones `employee()` y `createdBy()` con return types, cast de `type` al enum y `date`/`amount`). Actualizar `ai-specs/specs/domain-model.md`.
- [ ] 1.5 Crear factory `EmployeeAdjustmentFactory` con estados `bonus()` y `deduction()`.

## 2. Gestión de ajustes (CRUD backend)

- [ ] 2.1 Crear Action(s) en `App\Domain\Employee\Actions` para crear/actualizar/eliminar un ajuste (setear `company_id`, `created_by`).
- [ ] 2.2 Crear Form Request con reglas (`amount` numérico > 0, `type` in enum, `date` fecha, `concept` requerido, `note` opcional) y mensajes de error.
- [ ] 2.3 Crear controller delgado (index/store/update/destroy) que delega en las Actions; tenant scope y autorización admin/super-admin; cross-company rechazado.
- [ ] 2.4 Registrar rutas anidadas `employees/{employee}/adjustments` en el archivo de rutas correspondiente y correr `php artisan wayfinder:generate`.
- [ ] 2.5 Feature test del CRUD: happy path admin, validación (monto inválido), cross-company rechazado, employee denegado. `php artisan test --compact --filter=...`.
- [ ] 2.6 `vendor/bin/pint --dirty --format agent`.

## 3. Integración en el reporte (cálculo)

- [ ] 3.1 En `GenerateEmployeeReport` agregar una query agregada de ajustes del periodo (`SUM(CASE WHEN type='Bonus'...)` para `bonus_total`/`deduction_total`) y traer el detalle de ajustes como lista. Pasar los totales al cálculo y el detalle al resultado.
- [ ] 3.2 En `CalculateReportCosts::execute()` recibir `bonus_total`/`deduction_total` y exponer en `cost_summary`: `bonus_total`, `deduction_total`, `final_pay = round(net_pay + bonus_total − deduction_total, 2)`. No alterar `net_pay` ni la base de seguridad social.
- [ ] 3.3 Tests unit de `CalculateReportCosts`: `final_pay` con bono+deducción, sin ajustes (`final_pay == net_pay`), ajustes no afectan `social_security_base`/`net_pay`. Ejecutar el filtro.
- [ ] 3.4 Feature test de `GenerateEmployeeReport`: ajustes del periodo se suman; ajuste fuera del rango no se incluye; detalle de ajustes presente. Ejecutar el filtro.
- [ ] 3.5 `vendor/bin/pint --dirty --format agent`.

## 4. Presentación del reporte (frontend + exports)

- [ ] 4.1 En `Reports/Employee.vue` extender el tipo de `cost_summary` y renderizar, debajo del neto a pagar, las bonificaciones (+), deducciones (−) y la fila "Total a pagar" (`final_pay`).
- [ ] 4.2 En `resources/views/exports/employee-report.blade.php` agregar las mismas filas.
- [ ] 4.3 En `app/Exports/EmployeeReportExport.php` agregar las mismas filas.
- [ ] 4.4 Claves i18n en `es.json` / `en.json` (bonificaciones, deducciones, total a pagar).
- [ ] 4.5 Tests de export (`ReportExportTest`): Excel y PDF incluyen las filas de ajustes y el total final con sus valores.
- [ ] 4.6 `npm run build`.

## 5. UI de gestión de ajustes (frontend)

- [ ] 5.1 Agregar en la ficha/edición del empleado la UI para listar, crear, editar y eliminar ajustes (tabla + formulario), usando Wayfinder para las rutas.
- [ ] 5.2 Claves i18n para la UI de ajustes.
- [ ] 5.3 `npm run build` y verificar que compila.

## 6. Verificación final

- [ ] 6.1 Ejecutar la suite afectada (`CalculateReportCosts`, `GenerateEmployeeReport`, CRUD de ajustes, `ReportExportTest`) en `--compact` y confirmar que pasa.
- [ ] 6.2 Confirmar que el reporte de empresa no se ve afectado y que la base de salud/pensión no cambia con los ajustes.
