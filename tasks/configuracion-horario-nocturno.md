# Configuración de horario nocturno por empresa

## [Original]

> "gestión sobre a qué hora empieza el horario nocturno, que cada administrador lo pueda modificar y que el recargo nocturno para cada empresa se calcule según ese valor"

---

## [Enhanced]

### User Story
Como **administrador de empresa**, quiero configurar el rango horario que define el período nocturno para que el cálculo de recargos nocturnos refleje la configuración específica de mi empresa.

### Descripción
Actualmente el horario nocturno está hardcodeado en `CalculateWorkHours` como `21:00–06:00` para todas las empresas. Esto no permite flexibilidad si una empresa opera bajo un convenio colectivo o regulación que define un rango distinto.

La tarea consiste en agregar dos campos configurables (`night_start_time` y `night_end_time`) a la tabla `surcharge_rules`, exponer un formulario de edición en el panel de configuraciones (junto a los porcentajes de recargo existentes), y modificar `CalculateWorkHours` para leer esos valores en lugar del rango fijo.

El administrador podrá actualizar el inicio y fin del horario nocturno desde `Configuración → Reglas de recargo`. El cambio afecta únicamente los cálculos futuros; las entradas de tiempo ya calculadas no se recalculan.

### Contexto técnico
- **Dominio principal:** `app/Domain/Company/` (SurchargeRule) + `app/Domain/TimeTracking/` (CalculateWorkHours)
- **Tablas involucradas:**
  - `surcharge_rules` — agregar `night_start_time` (time, default `21:00`) y `night_end_time` (time, default `06:00`)
- **Roles con acceso:** `admin` (solo su empresa) y `super-admin` (cualquier empresa)
- **Multi-tenant:** sí — `surcharge_rules` tiene `company_id` único por empresa; el admin solo puede modificar la regla de su propia empresa

### Criterios de aceptación
- [ ] El admin puede ver y editar el inicio y fin del horario nocturno en `Configuración → Reglas de recargo`
- [ ] Los valores se guardan con formato `HH:MM` (24h) y se validan que sean horas válidas
- [ ] `CalculateWorkHours` usa `night_start_time` y `night_end_time` de `SurchargeRule` de la empresa en lugar del valor hardcodeado
- [ ] Un admin no puede modificar la configuración de otra empresa (cross-company → error de validación)
- [ ] Super-admin puede modificar la configuración de cualquier empresa
- [ ] Si la empresa no tiene `SurchargeRule` aún, se usa el default `21:00–06:00`
- [ ] El formulario muestra los valores actuales pre-cargados al abrir la página

### Desglose técnico — Backend

- **Migración:** agregar a `surcharge_rules`:
  ```php
  $table->time('night_start_time')->default('21:00');
  $table->time('night_end_time')->default('06:00');
  ```
  Actualizar `ai-specs/specs/data-model.md` con las nuevas columnas.

- **Modelo `SurchargeRule`:** agregar `night_start_time` y `night_end_time` al cast como `string` (o `datetime:H:i`). Actualizar factory con valores por defecto.

- **Action `UpdateSurchargeRule`** (existente o nueva): recibir y persistir `night_start_time` y `night_end_time` además de los porcentajes ya existentes.

- **Action `CalculateWorkHours`** (modificar): reemplazar las constantes hardcodeadas `21` y `6` por lectura de `$surchargeRule->night_start_time` y `$surchargeRule->night_end_time`. Parsear correctamente el cruce de medianoche (p.ej. `21:00` del día N a `06:00` del día N+1).

- **Form Request `UpdateSurchargeRuleRequest`** (actualizar):
  ```
  night_start_time: required|date_format:H:i
  night_end_time:   required|date_format:H:i
  ```

- **Controller `Settings/SurchargeRuleController`** (existente): sin cambios estructurales; el Form Request y la Action absorben los nuevos campos.

- **Ruta:** ya existe en `settings.php` — no se agrega nueva ruta. Ejecutar `php artisan wayfinder:generate` solo si cambia la firma del método.

- **Tests requeridos:**
  - Happy path admin: actualiza `night_start_time`/`night_end_time` de su empresa → `assertDatabaseHas` con todos los campos
  - Happy path super-admin: actualiza configuración de cualquier empresa
  - Cross-company: admin intenta modificar `SurchargeRule` de otra empresa → `assertSessionHasErrors`
  - Validación: `night_start_time` con formato inválido (e.g. `25:00`, `abc`) → `assertSessionHasErrors('night_start_time')`
  - `CalculateWorkHours` con `night_start_time = '22:00'` y `night_end_time = '05:00'`: verificar que minutos entre 22:00 y 05:00 se clasifican como nocturnos y los de 21:00–22:00 no

### Desglose técnico — Frontend

- **Página Vue a modificar:** `resources/js/pages/Settings/SurchargeRule.vue` (o similar existente)
- **Componentes UI a agregar:** dos campos `<Input type="time">` usando `resources/js/components/ui/input/`
- **Props de Inertia** (añadir al shape existente):
  ```ts
  surchargeRule: {
    // campos existentes...
    night_start_time: string  // "21:00"
    night_end_time: string    // "06:00"
  }
  ```
- **Wayfinder:** no requiere nuevos imports si la ruta ya existe
- **i18n:** agregar en `lang/en/messages.php` y `lang/es/messages.php`:
  ```
  'night_start_time' => 'Night start time' / 'Inicio horario nocturno'
  'night_end_time'   => 'Night end time'   / 'Fin horario nocturno'
  ```

### Requisitos no funcionales
- **Seguridad:** el controller ya usa middleware `role:admin|super-admin`; la validación cross-company aplica condicionalmente según si el usuario es `super-admin` (`company_id = null`)
- **Performance:** `SurchargeRule` ya se carga en `CalculateWorkHours`; no hay consulta adicional

### Definición de Done
- [ ] Tests pasando: `php artisan test --compact --filter=SurchargeRuleTest`
- [ ] Tests pasando: `php artisan test --compact --filter=CalculateWorkHoursTest`
- [ ] `vendor/bin/pint --dirty` sin errores
- [ ] `npm run build` exitoso
- [ ] `ai-specs/specs/data-model.md` actualizado con `night_start_time` y `night_end_time` en `surcharge_rules`
- [ ] `ai-specs/specs/base-standards.mdc` actualizado: "Recargo nocturno: configurable por empresa (default 21:00–06:00)"
