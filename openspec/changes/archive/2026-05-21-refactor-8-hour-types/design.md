## Context

`CalculateWorkHours` itera minuto a minuto sobre un turno y clasifica cada segmento en un bucket usando un `match` de 4 ramas con prioridad estricta: `$isOvertime > $isSundayOrHoliday > $isNight > default`. Esto produce 4 columnas en `time_entries`. `CalculateReportCosts` toma esas 4 columnas y aplica los recargos de `surcharge_rules`.

El problema es que la prioridad estricta de la primera rama aplana todos los sub-tipos de overtime en uno solo. Un minuto que sea simultáneamente extra + nocturno + dominical queda clasificado como "overtime" y se cobra con `overtime_day` (25%) en vez de `overtime_night_sunday` (150%).

`surcharge_rules` ya tiene los 7 campos de recargo correctos desde el origen. Solo falta que el clasificador los use.

## Goals / Non-Goals

**Goals:**
- Clasificar cada minuto trabajado en uno de 8 tipos mutuamente excluyentes
- Calcular el costo aplicando el recargo específico de cada tipo
- Mostrar el desglose de 8 tipos en reportes y exports
- Mantener la arquitectura existente (Action → DB → Report)

**Non-Goals:**
- Recalcular entradas históricas con `status='calculated'`
- Cambiar la UI de configuración de `surcharge_rules`
- Modificar la lógica de clock-in/clock-out o breaks
- Cambiar el algoritmo de breakpoints (sigue siendo el mismo)

## Decisions

### D1: 8 buckets en lugar de refactor del match

**Decisión:** Expandir el `match` a 8 ramas combinando los tres flags (`$isOvertime`, `$isSundayOrHoliday`, `$isNight`) en orden de mayor a menor especificidad.

```php
match(true) {
    $isOvertime && $isSundayOrHoliday && $isNight => 'overtime_night_sunday',
    $isOvertime && $isSundayOrHoliday             => 'overtime_day_sunday',
    $isOvertime && $isNight                        => 'overtime_night',
    $isOvertime                                    => 'overtime_day',
    $isSundayOrHoliday && $isNight                 => 'night_sunday',
    $isSundayOrHoliday                             => 'sunday_holiday',
    $isNight                                       => 'night',
    default                                        => 'regular',
}
```

**Alternativa descartada:** Mantener el match de 4 ramas y derivar sub-tipos post-hoc al calcular costos. Descartada porque requeriría guardar meta-información adicional por segmento (era domingo? era nocturno?) que no está disponible al momento del cálculo de costos.

**Rationale:** El cambio es quirúrgico (~15 líneas), el algoritmo de breakpoints no cambia, y el resultado queda almacenado en columnas independientes para consultas/agregados SQL eficientes.

---

### D2: Mantener el nombre `overtime_hours` como `overtime_day_hours` (rename explícito)

**Decisión:** Renombrar `overtime_hours` → `overtime_day_hours` en la migración. Nombre descriptivo consistente con los nuevos campos.

**Alternativa descartada:** Mantener `overtime_hours` como alias del sub-tipo diurno de semana. Descartada porque genera confusión: el nombre sugiere "todo el overtime" cuando en realidad solo contiene una fracción.

**Rationale:** El sistema está en desarrollo, no hay datos de producción. El costo del rename (buscar/reemplazar en el codebase) es bajo comparado con el beneficio de claridad a largo plazo.

---

### D3: 4 columnas nuevas en `time_entries` (no tabla separada)

**Decisión:** Agregar `overtime_night_hours`, `night_sunday_hours`, `overtime_day_sunday_hours`, `overtime_night_sunday_hours` como columnas `decimal(5,2)` en `time_entries`.

**Alternativa descartada:** Tabla `time_entry_hour_types(entry_id, type, minutes)`. Descartada porque rompe todos los queries existentes, introduce N+1 en reportes, y la cantidad de tipos (8) es fija por legislación.

**Rationale:** Consistente con el esquema actual. SQL `SUM(overtime_night_hours)` es trivial. Los índices existentes siguen siendo efectivos.

---

### D4: `CalculateReportCosts` acepta y retorna 8 tipos

**Decisión:** Actualizar la firma de `execute()` para leer 8 keys del array `$hourTotals` y devolver 8 items en `details`. El array `details` ya se itera dinámicamente en `Employee.vue` (`v-for`), por lo que la UI escala sin cambios estructurales.

**Rationale:** El contrato de la función crece pero no cambia de naturaleza. Los tipos faltantes defaultan a `0` para backward-compatibility durante desarrollo.

---

### D5: PHPDoc con comentarios en el modelo TimeEntry

**Decisión:** Agregar comentario de una línea a cada propiedad de horas en `TimeEntry` explicando la condición exacta: día semana/dom-fest + diurno/nocturno + dentro-límite/extra.

**Rationale:** El modelo es el contrato de datos del dominio. Un lector debe entender qué significa cada campo sin consultar `CalculateWorkHours`.

## Risks / Trade-offs

- **[Rename masivo de `overtime_hours`]** → Todos los archivos que referencian el campo deben actualizarse. Mitigación: buscar/reemplazar en todo el proyecto antes de la migración; correr el test suite completo al final.

- **[Tests existentes asumen 4 tipos]** → `test_details_array_contains_correct_surcharge_percentages` aserta `assertCount(4, $result['details'])`. Mitigación: actualizar al principio a `assertCount(8, ...)`.

- **[Datos existentes en `overtime_hours` no se reclasifican]** → Las entradas calculadas antes del deploy tendrán `overtime_day_hours` con una mezcla de sub-tipos. Mitigación: dado que el sistema está en desarrollo, resetear `status='pending'` en todas las entradas o truncar `time_entries` antes del deploy.

- **[Vue i18n]** → Las labels de los 4 nuevos tipos deben agregarse a los archivos de traducción. Si falta una key el componente mostrará la key cruda. Mitigación: agregar todas las keys antes de actualizar la vista.

## Migration Plan

1. Crear migración: `rename_and_expand_time_entries_hour_columns`
   - `$table->renameColumn('overtime_hours', 'overtime_day_hours')`
   - ADD COLUMN × 4 con `default(0.00)`
2. Buscar/reemplazar `overtime_hours` en todo el codebase (PHP + TS + Vue)
3. Actualizar `CalculateWorkHours`, `CalculateReportCosts`, `GenerateEmployeeReport`, `GenerateCompanyReport`, exports, modelos, types
4. Actualizar vistas Vue + i18n
5. Correr `php artisan test --compact` — todos los tests deben pasar
6. En dev: `UPDATE time_entries SET status='pending'` para forzar recalculación

**Rollback:** La migración puede revertirse (`renameColumn` inverso + `dropColumn`). No hay cambios de datos irreversibles.
