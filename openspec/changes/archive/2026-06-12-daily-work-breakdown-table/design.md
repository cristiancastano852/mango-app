# Design: daily-work-breakdown-table

## Context

Las dos vistas afectadas ya tienen su backend casi completo:

- `GenerateEmployeeReport::getDailyBreakdown()` produce un desglose por día (agregado SQL con `GROUP BY date`) que hoy solo consumen los exports; la vista web lo pide con `includeDailyBreakdown: false` (`ReportController::employee`).
- `time_entries` tiene constraint `unique(employee_id, date)` (con marcador de activo para soft-deletes): **un entry activo por empleado por día**. El desglose diario es isomorfo a los entries mismos.
- Todas las horas (gross/break/net + 8 tipos) están **precalculadas y persistidas** por `RecalculateTimeEntry`/`CalculateWorkHours`. Este cambio solo lee.
- `break_types` ya tiene `icon` y `color`. `formatDecimalHours()` ya da "Xh Ym". App timezone: `America/Bogota`.
- Índices existentes: `unique(employee_id, date)` cubre el rango del reporte; FK de `breaks.time_entry_id` cubre el eager load.

## Goals / Non-Goals

**Goals:**

- Desglose diario visual en `/reports/employee` (debajo del resumen de costos) y filas enriquecidas en `/admin/time-entries`: horario AM/PM, trabajado y descansos en "Xh Ym", detalle expandible de pausas y tipos de hora.
- Reutilizar el backend entre ambas vistas y los exports (un solo dataset enriquecido).
- Días "No laborado" y turnos "En curso" representados honestamente.
- Cero impacto de performance: solo queries indexadas ya existentes + 1 eager load.

**Non-Goals:**

- Cambiar cálculo/clasificación de horas, reporte de empresa, filtros, paginación, vistas de empleado.
- Migraciones de BD.

## Decisions

### D1 — Enriquecer `getDailyBreakdown()` en lugar de crear una estructura paralela

Cambiar la implementación de `GROUP BY date` a lectura de entries con `with(['breaks' => ... ->with('breakType')])`, manteniendo el shape actual (los exports siguen funcionando) y agregando campos nuevos por día:

```php
[
    'date' => '2026-06-10',
    'clock_in' => '2026-06-10T07:00:00-05:00',   // ISO 8601, nuevo
    'clock_out' => '2026-06-10T16:11:00-05:00',  // ISO 8601, nuevo
    'status' => 'calculated',                     // nuevo
    'gross_hours' => 9.5, 'break_hours' => 1.0, 'net_hours' => 8.5,
    // ...los 8 tipos de hora, igual que hoy
    'breaks' => [                                 // nuevo
        ['name' => 'Almuerzo', 'icon' => '🍽️', 'color' => '#FF9800', 'is_paid' => false,
         'started_at' => '...', 'ended_at' => '...', 'duration_minutes' => 60],
    ],
]
```

- **Por qué no mantener el GROUP BY + subqueries**: el unique constraint hace que el GROUP BY devuelva exactamente una fila por entry; leer entries directamente da lo mismo con menos SQL y permite anidar pausas. Alternativa descartada: nueva Action `GetDailyWorkDetail` — duplicaría el rango/filtros/exclusiones ya resueltos en `GenerateEmployeeReport` y obligaría a los exports a migrar o divergir.
- Las horas de reloj viajan **en ISO 8601 con offset** y el frontend las formatea (AM/PM, locale-aware). El blade del PDF formatea en PHP (`->format('g:i A')`).
- El método deja de excluir `whereNotNull('clock_out')` **solo para el breakdown** (los turnos en curso se necesitan como filas "En curso"); `aggregateTotals()` no cambia — los totales siguen excluyendo turnos abiertos. Un entry sin `clock_out` sale con `status` derivado `in_progress` y horas en null para que el front no las muestre.
- Exports: deben seguir mostrando solo días finalizados → el export filtra (o el blade ignora) las filas `in_progress`. Decisión: filtrar en `EmployeeReportExport`/blade, no en la Action, para que la vista web sí las reciba.

### D2 — Días "No laborado": se generan en el frontend, no en el backend

El backend devuelve solo días con entry. `DailyWorkTable.vue` recibe `period.start`/`period.end` (ya existen en el report) y rellena los huecos hasta `min(period.end, hoy)` como filas atenuadas "No laborado".

- **Por qué**: evita inflar el payload de Inertia con filas vacías, no contamina los exports (que hoy no muestran días vacíos), y la lógica es presentacional pura. Alternativa descartada: rellenar en PHP — obligaría a todos los consumidores del breakdown a filtrar filas sintéticas.
- "Hoy" se calcula en el cliente; el desfase de timezone es aceptable porque la fila "No laborado" de hoy solo aparecería si el empleado aún no marca entrada (y en cuanto marque, llega el entry).

### D3 — `/admin/time-entries`: enriquecer el `through()` existente, sin Action nueva

El index ya pagina 20 entries. Cambios:

- Agregar al mapeo: `gross_hours`, `break_hours` (columnas del mismo row — cero queries extra), `clock_in`/`clock_out` en ISO (hoy van en `H:i`), y `breaks` mapeadas desde eager load `breaks.breakType` (+1 query por página, acotada por `whereIn` de 20 ids).
- No se introduce Action: es proyección de datos para la vista, el controller sigue delgado (sin lógica de negocio nueva). No hay Form Request nuevo: no se agregan inputs (los filtros existentes ya están validados implícitamente por su uso con `when()`; sin cambios).
- El reuso backend real entre vistas es el dato persistido + el shape de `breaks[]`: se extrae un mapeo compartido de pausas (método privado o helper en el dominio TimeTracking, p.ej. `BreakEntry` presenter/closure compartido) para que ambas vistas serialicen las pausas idéntico.

### D4 — Frontend: componente compartido `DailyWorkTable.vue` + `formatTime12h()`

- `resources/js/components/DailyWorkTable.vue`: tabla con columnas Día / Horario / Trabajado / Descansos, fila expandible (componente `collapsible` de shadcn-vue ya disponible) con detalle de pausas (icono + color del BreakType) y chips de tipos de hora (>0 solamente). Props: `days[]`, flags (`showEmployee`, `fillMissingDays`, `period`), slots para columna de acciones/estado en time-entries.
- Reuso en time-entries: misma estructura visual con columna empleado, badge de estado y acciones editar/borrar vía slot. Si el encaje del slot complica el componente, se permite divergir en dos componentes que compartan los subcomponentes de celda (HorarioCell, DuraciónCell, BreaksDetail) — el reuso obligatorio es backend, el visual es deseable.
- `formatTime12h(iso: string)` en `resources/js/lib/utils.ts`: única fuente para formato `7:00 AM` (usa `Intl.DateTimeFormat` con `hour12: true`). Se aplica también donde el index muestra horas hoy.
- Indicadores con color: verde (trabajado), ámbar (descansos), badge rojo domingo/festivo (derivable: `sunday_holiday_hours + night_sunday_hours + overtime_*_sunday > 0`), rayo ámbar si hubo extras, gris para "No laborado", azul "En curso".
- i18n es/en para todas las etiquetas nuevas.
- Implementar la UI con el skill `frontend-design` activo.

### D5 — Tests: reforzar clases existentes, sin archivos paralelos

- `GenerateEmployeeReportTest`: nuevos asserts campo a campo del breakdown enriquecido (clock_in/out ISO, breaks anidadas con icon/color, pausas múltiples, pausa sin `ended_at` excluida del detalle o marcada, orden cronológico, entry en curso presente con horas null y excluido de totales, soft-deleted excluido — ya cubierto, extender al shape nuevo).
- `ReportControllerTest::test_employee_report_returns_correct_data`: assert del prop `report.daily_breakdown` completo vía `AssertableInertia` (campo a campo, no solo `has`).
- `Admin/TimeEntryControllerTest`: asserts de los campos nuevos del index (`gross_hours`, `break_hours`, `clock_in` ISO, `breaks[]`), y test de conteo de queries (sin N+1 con 20 entries × pausas).
- PHPUnit (no Pest), `Role::create(['name' => 'employee'])` en setUp donde aplique, fechas SQLite como datetime completo.

## Risks / Trade-offs

- [El breakdown ahora incluye entries en curso] → `aggregateTotals` no cambia; exports filtran `in_progress`; tests explícitos de que totales ≠ suma de filas cuando hay turno abierto.
- [Cambio de shape de `clock_in` en el index de time-entries (`H:i` → ISO)] → el front es el único consumidor (Inertia); se actualiza en el mismo PR y el test de controller fija el formato nuevo.
- [Pausas con `duration_minutes` negativos en prod (data repair pendiente)] → la UI debe tolerar valores anómalos sin romper (clamp visual a 0 o mostrar el dato crudo); no se corrige el dato aquí.
- [Componente compartido con slots puede sobre-abstraerse] → permitido divergir en dos tablas que compartan subcomponentes de celda; criterio: legibilidad sobre DRY visual.
- [Relleno de días en cliente con timezone del navegador] → riesgo bajo (admins en Colombia); el peor caso es una fila "No laborado" de hoy que desaparece al refrescar.

## Migration Plan

Sin migración de BD. Deploy normal (`npm run build` incluido). Rollback: revertir el commit — no hay cambios de datos ni de schema.

## Open Questions

- Ninguna bloqueante. (Menor, decidible en implementación: si la pausa en curso —sin `ended_at`— se muestra en el expandible como "pausa en curso" o se omite; inclinación: mostrarla con badge, sin duración.)
