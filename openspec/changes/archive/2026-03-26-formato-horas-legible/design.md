## Context

Todos los valores de horas en el frontend provienen del backend como decimales (`decimal 5,2`), por ejemplo `7.99`, `0.5`, `8.0`. Actualmente se presentan con `.toFixed(1)` o sin formato, resultando en `7.9h` o `7.99h`, que son poco legibles para usuarios finales.

La función `formatHours()` que ya existe en `TimeClock/Index.vue` convierte a `HH:MM:SS` para el cronómetro en tiempo real — ese uso es correcto y no se toca.

El cambio afecta 6 archivos Vue y necesita una única fuente de verdad para la lógica de formato.

## Goals / Non-Goals

**Goals:**
- Crear `formatDecimalHours()` como función utilitaria en `resources/js/lib/utils.ts` (donde ya vive `cn()` y `toUrl()`)
- Aplicar la función en todos los lugares donde se muestran horas estáticas al usuario
- Manejar edge cases: `null`, `undefined`, `0`, strings numéricos

**Non-Goals:**
- Cambiar el cronómetro en tiempo real (`HH:MM:SS`) de TimeClock
- Cambiar el backend o la BD
- Internacionalizar el formato (el formato `Xh Ym` es suficientemente universal)
- Cambiar cómo se almacenan o calculan las horas

## Decisions

**1. Dónde ubicar la función: `lib/utils.ts` vs nuevo archivo `utils/hours.ts`**

→ **`lib/utils.ts`** — ya es el archivo de utilidades del proyecto (tiene `cn()` y `toUrl()`). Crear un nuevo archivo solo para una función es sobredimensionado para este cambio.

_Alternativa descartada:_ composable `useFormatHours` — innecesario para una función pura sin estado reactivo.

**2. Algoritmo de conversión**

```ts
export function formatDecimalHours(hours: number | string | null | undefined): string {
    const h = Number(hours ?? 0);
    if (isNaN(h)) return '0h 0m';
    const totalMinutes = Math.round(h * 60);
    const hrs = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;
    return `${hrs}h ${mins}m`;
}
```

`Math.round` sobre minutos totales evita errores de punto flotante (`7.99 * 60 = 479.4` → `480 min = 8h 0m` sería incorrecto; `Math.round(479.4) = 479 = 7h 59m` ✓).

_Alternativa descartada:_ `toFixed(1)` — no resuelve el problema de legibilidad.

**3. Migración gradual vs. cambio en un solo PR**

→ **Un solo PR** — el cambio es mecánico (buscar y reemplazar patrones de display), bajo riesgo, y mantenerlo fragmentado crea inconsistencia visual temporal.

## Risks / Trade-offs

- **[Riesgo] `7.99 * 60 = 479.40000...` (punto flotante)** → Mitigación: usar `Math.round` sobre minutos totales antes de dividir, como muestra el algoritmo arriba.
- **[Riesgo] Valor `null` llega del backend cuando la jornada no ha cerrado** → Mitigación: la función maneja `null` con `?? 0`, retornando `0h 0m`.
- **[Trade-off] `8h 0m` en lugar de `8h`** — se mantienen los minutos siempre para consistencia visual en listas/tablas. Un usuario que ve `8h` y otro `8h 0m` en la misma tabla sería confuso.

## Migration Plan

1. Agregar `formatDecimalHours` a `resources/js/lib/utils.ts`
2. Actualizar los 6 archivos Vue en orden: Dashboard → TimeClock → Reports → Admin → Calendar
3. `npm run build` para verificar TypeScript sin errores
4. Verificación manual en el browser

Rollback: revertir el commit — no hay cambios de BD ni de API.

## Open Questions

_(ninguna — el cambio es claro y acotado)_
