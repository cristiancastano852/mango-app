## 1. Base de datos

- [ ] 1.1 Crear migración para agregar `night_start_time` (time, default `21:00`) y `night_end_time` (time, default `06:00`) a la tabla `surcharge_rules`
- [ ] 1.2 Ejecutar la migración y actualizar `ai-specs/specs/data-model.md` con las nuevas columnas

## 2. Modelo y Factory

- [ ] 2.1 Agregar `night_start_time` y `night_end_time` al modelo `SurchargeRule`: incluir en `$fillable` y agregar cast como `string` si corresponde
- [ ] 2.2 Actualizar `SurchargeRuleFactory` con valores por defecto `'21:00'` / `'06:00'`

## 3. Lógica de negocio

- [ ] 3.1 Modificar `CalculateWorkHours`: reemplazar constantes hardcodeadas `21`/`6` por valores parseados desde `$surchargeRule->night_start_time` y `$surchargeRule->night_end_time` usando `Carbon::createFromFormat('H:i', ...)`; agregar fallback `'21:00'`/`'06:00'` si `SurchargeRule` es null

## 4. Validación y Controller

- [ ] 4.1 Actualizar `UpdateSurchargeRuleRequest`: agregar `night_start_time` y `night_end_time` con regla `required|date_format:H:i`; agregar mensajes de error en español
- [ ] 4.2 Actualizar la Action (o controller) de `UpdateSurchargeRule` para persistir `night_start_time` y `night_end_time`
- [ ] 4.3 Verificar que `Settings/SurchargeRuleController` pasa los nuevos valores de la `SurchargeRule` a la vista Inertia

## 5. Frontend

- [ ] 5.1 Agregar `night_start_time` y `night_end_time` al shape de props Inertia en `Settings/SurchargeRule.vue`
- [ ] 5.2 Agregar dos campos `<Input type="time">` en el formulario Vue para inicio y fin del horario nocturno, usando componente `resources/js/components/ui/input/`
- [ ] 5.3 Agregar claves i18n en `lang/en/messages.php` (`night_start_time`, `night_end_time`) y en `lang/es/messages.php` (`Inicio horario nocturno`, `Fin horario nocturno`)
- [ ] 5.4 Ejecutar `npm run build` y verificar que el formulario muestra y envía los valores correctamente

## 6. Tests

- [ ] 6.1 Agregar test happy path (admin): actualiza `night_start_time`/`night_end_time` de su empresa → `assertDatabaseHas` con todos los campos del registro
- [ ] 6.2 Agregar test happy path (super-admin): actualiza horario nocturno de cualquier empresa
- [ ] 6.3 Agregar test cross-company: admin intenta modificar `SurchargeRule` de otra empresa → `assertSessionHasErrors`
- [ ] 6.4 Agregar test de validación: `night_start_time = '25:00'` y `night_start_time = 'abc'` → `assertSessionHasErrors('night_start_time')`
- [ ] 6.5 Agregar test `CalculateWorkHours` con `night_start_time = '22:00'` y `night_end_time = '05:00'`: minutos entre 22:00–05:00 son nocturnos; minutos entre 21:00–22:00 no lo son
- [ ] 6.6 Ejecutar `php artisan test --compact --filter=SurchargeRuleTest` y `--filter=CalculateWorkHoursTest` y confirmar que todos pasan

## 7. Calidad

- [ ] 7.1 Ejecutar `vendor/bin/pint --dirty --format agent` y corregir errores de estilo
- [ ] 7.2 Actualizar `ai-specs/specs/base-standards.mdc`: cambiar "Recargo nocturno: 21:00–06:00" por "Recargo nocturno: configurable por empresa (default 21:00–06:00)"
