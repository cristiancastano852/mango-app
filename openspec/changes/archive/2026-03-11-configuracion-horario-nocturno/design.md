## Context

`CalculateWorkHours` clasifica minutos como nocturnos usando las constantes hardcodeadas `21` (inicio) y `6` (fin). `SurchargeRule` ya existe como registro único por empresa con configuración de porcentajes de recargo. Es el lugar natural para almacenar `night_start_time` y `night_end_time`.

El controller `Settings/SurchargeRuleController` y su `UpdateSurchargeRuleRequest` ya existen y manejan la actualización de porcentajes. La ruta ya está definida en `settings.php`.

## Goals / Non-Goals

**Goals:**
- Almacenar `night_start_time` y `night_end_time` en `surcharge_rules` (migración)
- Exponer ambos campos en el formulario Vue de `Configuración → Reglas de recargo`
- Que `CalculateWorkHours` use los valores de la empresa en lugar de constantes
- Validar formato `HH:MM` y autorización cross-company en el Form Request

**Non-Goals:**
- Recalcular entradas de tiempo ya guardadas (solo afecta cálculos futuros)
- Soporte para rangos nocturnos discontinuos (e.g. dos bloques separados)
- Cambiar la lógica de detección de cruce de medianoche (ya funciona correctamente)

## Decisions

### 1. Almacenar en `surcharge_rules`, no en `companies`
`surcharge_rules` ya centraliza todas las configuraciones de recargo por empresa (porcentajes, max_weekly_hours). Agregar aquí evita dispersar la configuración en otra tabla.

### 2. Tipo de columna: `time` (string `H:i` en PHP)
Columna SQL `TIME` con default `'21:00'`/`'06:00'`. En PHP se accede como string; `CalculateWorkHours` parsea con `Carbon::createFromFormat('H:i', ...)`. No se necesita cast especial.

### 3. Modificar `CalculateWorkHours` — leer desde `SurchargeRule`
`CalculateWorkHours` ya recibe la `SurchargeRule` de la empresa. Solo hay que reemplazar `$nightStart = 21` / `$nightEnd = 6` por valores parseados desde el modelo. Sin consulta adicional.

### 4. Reutilizar controller/action/form request existentes
La ruta `PUT /settings/surcharge-rule` ya existe. `UpdateSurchargeRuleRequest` y la Action de actualización solo necesitan los dos campos nuevos. No se crea nueva ruta ni controller.

### 5. Fallback si no existe `SurchargeRule`
`CompanyObserver` ya crea `SurchargeRule` al crear empresa. Si por alguna razón no existe, `CalculateWorkHours` debe usar `21:00`/`06:00` como default (guard con `?? '21:00'`).

## Risks / Trade-offs

- **[Risk] Cruce de medianoche con rangos distintos** → Mitigación: la lógica de cruce de medianoche en `CalculateWorkHours` ya es genérica (compara minutos del día); solo hay que pasar los valores dinámicos.
- **[Risk] Migración en producción con datos existentes** → Los defaults `21:00`/`06:00` preservan comportamiento actual; no hay acción requerida sobre datos existentes.
- **[Trade-off] Input `type="time"` en browser** → Algunos navegadores muestran formato 12h. El valor enviado al backend siempre es `HH:MM` (24h), validado con `date_format:H:i`.
