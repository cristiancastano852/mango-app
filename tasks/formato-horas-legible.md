## [Original]

> Al finalizar una jornada diaria de una empleada quedó así: el dato en BD sí queda guardado 7.99 pero necesito que al usuario no se le muestre de esa manera sino algo más fácil como 7h y 55m — para un usuario no es tan fácil entender cuánto es 7.99h. Cuadrar otros lugares donde pase lo mismo.
>
> Contexto visual:
> - Horas Netas Hoy: `7.99h`
> - prom por empleado: `7.99h`
> - Estado de Empleados → `Finalizado`

---

## [Enhanced]

### User Story
Como **admin** o **empleado**, quiero que las horas se muestren en formato `7h 55m` en lugar de `7.99h` para **entender de un vistazo cuánto tiempo trabajé o trabajaron mis empleados**.

### Descripción
Actualmente todos los valores de horas decimales en el frontend se muestran con sufijo `h` (ej. `7.99h`) o con `.toFixed(1)` (ej. `7.9h`). Este formato es técnico y confuso para usuarios no técnicos: `7.99h` no comunica intuitivamente que son 7 horas y 59 minutos.

La solución es crear un **composable reutilizable `useFormatHours`** (o función utilitaria `formatDecimalHours`) que convierta horas decimales al formato `Xh Ym`, y aplicarlo en **todos los lugares del frontend** donde se muestran horas al usuario final. El dato en base de datos se mantiene como `decimal(5,2)` — el cambio es únicamente de presentación.

Ya existe una función `formatHours()` en `TimeClock/Index.vue` que convierte a `HH:MM:SS` para el cronómetro en tiempo real. Esa función es para contadores activos y no debe modificarse; el nuevo formato `Xh Ym` es para valores estáticos de resumen.

### Contexto técnico
- **Dominio:** `app/Domain/TimeTracking/` (datos), presentación en `resources/js/`
- **Tablas involucradas:** `time_entries` — columnas `net_hours`, `gross_hours`, `break_hours`, `regular_hours`, `overtime_hours`, `night_hours`, `sunday_holiday_hours` (todas `decimal 5,2`)
- **Roles con acceso:** `admin` (dashboard, time entries, reports, calendar), `employee` (time clock, historial propio)
- **Multi-tenant:** No aplica directamente — es cambio de presentación puro en frontend

### Criterios de aceptación
- [ ] `7.99` se muestra como `7h 59m` (no `7h 60m` — redondeo correcto de minutos)
- [ ] `0.5` se muestra como `0h 30m`
- [ ] `8.0` se muestra como `8h 0m`
- [ ] `0.0` o `null` se muestra como `0h 0m` sin errores
- [ ] El formato aplica en Dashboard (KPIs + lista de empleados)
- [ ] El formato aplica en TimeClock/Index (historial de entradas)
- [ ] El formato aplica en Reports/Employee y Reports/Company
- [ ] El formato aplica en Admin/TimeEntries/Index
- [ ] El formato aplica en Calendar/Index
- [ ] El cronómetro en tiempo real (`HH:MM:SS`) en TimeClock **no** se modifica

### Desglose técnico — Frontend

**Utility a crear:** `resources/js/utils/formatHours.ts`

```typescript
// Convierte horas decimales a "Xh Ym"
// Ej: 7.99 → "7h 59m" | 0.5 → "0h 30m"
export function formatDecimalHours(hours: number | string | null | undefined): string {
    const h = Number(hours ?? 0);
    if (isNaN(h)) return '0h 0m';
    const totalMinutes = Math.round(h * 60);
    const hrs = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;
    return `${hrs}h ${mins}m`;
}
```

**Páginas Vue a actualizar:**

| Archivo | Líneas afectadas | Cambio |
|---|---|---|
| `resources/js/pages/Dashboard.vue` | ~192, 194, 261 | `kpis.net_hours_today` → `formatDecimalHours(kpis.net_hours_today)` |
| `resources/js/pages/TimeClock/Index.vue` | ~297, 302, 307, 331 | Reemplazar `.toFixed(1)h` por `formatDecimalHours(...)` |
| `resources/js/pages/Reports/Employee.vue` | Totales y promedios | Reemplazar `.toFixed(1)h` |
| `resources/js/pages/Reports/Company.vue` | Donde muestra horas | Reemplazar `.toFixed(1)h` |
| `resources/js/pages/Admin/TimeEntries/Index.vue` | Columna net_hours | Reemplazar display actual |
| `resources/js/pages/Calendar/Index.vue` | Donde muestra net_hours | Reemplazar display actual |

**Componentes UI a reutilizar:** No requiere componentes nuevos — solo importar la función utilitaria.

**Wayfinder imports:** No aplica (cambio de presentación, sin nuevas rutas).

**i18n:** No requiere nuevas claves — el formato `Xh Ym` es universal.

### Requisitos no funcionales
- **Seguridad:** N/A — es cambio de presentación puro.
- **Performance:** La función es O(1), sin impacto.
- **Consistencia:** Usar la misma función en todos los lugares — no duplicar lógica inline.

### Definición de Done
- [ ] `resources/js/utils/formatHours.ts` creado con `formatDecimalHours()`
- [ ] Todos los archivos Vue listados actualizados para usar la función
- [ ] `npm run build` exitoso sin errores TypeScript
- [ ] Verificación manual: abrir Dashboard y ver `7h 59m` en lugar de `7.99h`
- [ ] El cronómetro `HH:MM:SS` en TimeClock sigue funcionando sin cambios
