## 1. Break Type Management — Backend

- [x] 1.1 Crear `BreakTypeFactory` con estados `paid()`, `unpaid()`, `lunchDefault()` usando `php artisan make:factory BreakTypeFactory --model=BreakType`
- [x] 1.2 Crear `StoreBreakTypeRequest` con reglas: name (required|string|max:255|unique por company), is_paid (required|boolean), max_duration_minutes (nullable|integer|min:1), max_per_day (nullable|integer|min:1), is_default (boolean), icon (nullable|string|max:50), color (nullable|string|max:7)
- [x] 1.3 Crear `UpdateBreakTypeRequest` con mismas reglas que store, validación unique excluyendo el registro actual
- [x] 1.4 Crear `Settings/BreakTypeController` — index (listar break types de la empresa), store (crear + auto-slug + gestión is_default con transacción), update (editar + gestión is_default), toggleActive (PATCH, valida que no sea default si desactiva)
- [x] 1.5 Agregar rutas en `settings.php`: resource `settings/break-types` (index, store, update) + PATCH toggle, middleware `role:admin|super-admin`
- [x] 1.6 Ejecutar `php artisan wayfinder:generate` y `npm run build`
- [x] 1.7 Crear tests PHPUnit para BreakTypeController: happy path admin (index, store, update, toggle), happy path super-admin, cross-company (store/update/toggle de otra empresa), is_default desmarca anterior, desactivar tipo default → error, validaciones de campos
- [x] 1.8 Ejecutar `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter=BreakType`

## 2. Break Type Management — Frontend

- [x] 2.1 Crear página `resources/js/pages/settings/BreakTypes.vue` con layout settings: tabla de tipos (nombre, badge pagada/no pagada, duración max, max/día, badge default, toggle activo/inactivo), dialog para crear/editar
- [x] 2.2 Agregar claves i18n en `resources/js/locales/en.json` y `es.json`: settings.break_types, break_type.name, break_type.is_paid, break_type.paid, break_type.unpaid, break_type.max_duration, break_type.max_per_day, break_type.is_default, break_type.active, break_type.inactive, break_type.create, break_type.edit
- [x] 2.3 Agregar nav item "Tipos de pausa" en `adminNavItems` del Settings Layout con ruta Wayfinder
- [x] 2.4 Ejecutar `npm run build` y verificar que compila sin errores

## 3. Company Profile — Backend

- [x] 3.1 Crear `UpdateCompanyProfileRequest` con reglas: name (required|string|max:255), logo (nullable|image|mimes:jpg,jpeg,png,svg|max:2048), remove_logo (nullable|boolean), country (required|string|size:2), timezone (required|timezone:all)
- [x] 3.2 Crear `Settings/CompanyProfileController` — edit (GET, retorna datos de company del user + logo URL), update (PUT, actualiza name/country/timezone, gestiona upload/delete de logo con Storage::disk('public'))
- [x] 3.3 Agregar rutas en `settings.php`: GET/PUT `settings/company-profile`, middleware `role:admin|super-admin`
- [x] 3.4 Ejecutar `php artisan wayfinder:generate` y `npm run build`
- [x] 3.5 Crear tests PHPUnit para CompanyProfileController: happy path admin (edit, update name/country/timezone), happy path super-admin sin empresa (ve estado vacío), upload logo válido, upload archivo no-imagen → error, logo > 2MB → error, remove_logo elimina archivo y pone null, validación name vacío, timezone inválido, country formato inválido
- [x] 3.6 Ejecutar `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter=CompanyProfile`

## 4. Company Profile — Frontend

- [x] 4.1 Crear página `resources/js/pages/settings/CompanyProfile.vue` con layout settings: Input nombre, file input con preview de logo actual, botón eliminar logo, Select país (mínimo CO), Select timezone (zonas horarias Latam relevantes)
- [x] 4.2 Agregar claves i18n: settings.company_profile, company.name, company.logo, company.upload_logo, company.remove_logo, company.country, company.timezone
- [x] 4.3 Agregar nav item "Empresa" como primer item de `adminNavItems` en Settings Layout
- [x] 4.4 Ejecutar `npm run build` y verificar

## 5. Company Working Days & Default Schedule — Backend

- [x] 5.1 Crear `UpdateCompanySettingsRequest` con reglas: working_days (required|array|min:1), working_days.* (integer|between:0,6), default_schedule_id (nullable|exists:schedules,id con validación condicional de company_id)
- [x] 5.2 Crear `Settings/CompanySettingsController` — edit (GET, retorna settings de company + lista de schedules de la empresa), update (PUT, deduplicar working_days, persistir en companies.settings jsonb)
- [x] 5.3 Agregar rutas en `settings.php`: GET/PUT `settings/company-settings`, middleware `role:admin|super-admin`
- [x] 5.4 Modificar `CreateEmployee::execute()`: si `schedule_id` es null, buscar `Company::find($companyId)->settings['default_schedule_id']` como fallback
- [x] 5.5 Modificar `SchedulesController::destroy()`: al eliminar schedule, limpiar `settings.default_schedule_id` de la empresa si coincide
- [x] 5.6 Ejecutar `php artisan wayfinder:generate` y `npm run build`
- [x] 5.7 Crear tests PHPUnit para CompanySettingsController: happy path admin (edit, update working_days + default_schedule_id), happy path super-admin sin empresa, cross-company (schedule_id de otra empresa → error), working_days vacío → error, valor fuera de rango → error, deduplicación de working_days, limpiar default_schedule_id → null
- [x] 5.8 Crear tests para CreateEmployee con default schedule: hereda default cuando schedule_id null, ignora default cuando schedule_id explícito, sin default configurado queda null
- [x] 5.9 Crear test para SchedulesController destroy: eliminar schedule default limpia el setting
- [x] 5.10 Ejecutar `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter=CompanySettings` y `--filter=CreateEmployee` y `--filter=SchedulesController`

## 6. Company Working Days — Frontend

- [x] 6.1 Crear página `resources/js/pages/settings/CompanySettings.vue` con layout settings: 7 checkboxes para días de la semana (Lun-Dom), Select de schedules de la empresa para horario por defecto
- [x] 6.2 Agregar claves i18n: settings.company_settings, settings.working_days, settings.default_schedule, days.monday..sunday
- [x] 6.3 Agregar nav item "Días laborales" en `adminNavItems` del Settings Layout (después de "Empresa")
- [x] 6.4 Ejecutar `npm run build` y verificar

## 7. Finalización

- [x] 7.1 Reordenar `adminNavItems` en Settings Layout: Empresa, Días laborales, Tipos de pausa, Recargos, Festivos
- [x] 7.2 Actualizar `ai-specs/specs/domain-model.md` con nuevos controllers (CompanyProfileController, CompanySettingsController, BreakTypeController)
- [x] 7.3 Ejecutar suite completa: `php artisan test --compact`
- [x] 7.4 Verificar `npm run build` exitoso final
