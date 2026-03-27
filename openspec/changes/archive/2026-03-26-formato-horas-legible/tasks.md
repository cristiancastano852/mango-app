## 1. Utility Function

- [x] 1.1 Agregar `formatDecimalHours(hours: number | string | null | undefined): string` a `resources/js/lib/utils.ts` usando `Math.round` sobre minutos totales

## 2. Dashboard

- [x] 2.1 Importar `formatDecimalHours` en `Dashboard.vue` y aplicar en KPI `net_hours_today`, `avg_net_hours`, y columna `net_hours_today` de la lista de empleados

## 3. TimeClock

- [x] 3.1 Importar `formatDecimalHours` en `TimeClock/Index.vue` y reemplazar `.toFixed(1)h` en el resumen del día (`gross_hours`, `break_hours`, `net_hours`) y en el historial de entradas — sin tocar el cronómetro `HH:MM:SS`

## 4. Reports

- [x] 4.1 Importar `formatDecimalHours` en `Reports/Employee.vue` y aplicar en totales (`gross_hours`, `break_hours`, `net_hours`) y en el promedio calculado
- [x] 4.2 Importar `formatDecimalHours` en `Reports/Company.vue` y aplicar en todos los valores de horas mostrados

## 5. Admin TimeEntries

- [x] 5.1 Importar `formatDecimalHours` en `Admin/TimeEntries/Index.vue` y aplicar en la columna `net_hours` de la tabla

## 6. Calendar

- [x] 6.1 Importar `formatDecimalHours` en `Calendar/Index.vue` y aplicar en el badge de horas por día

## 7. Build & Verification

- [x] 7.1 Ejecutar `npm run build` y verificar que no hay errores de TypeScript
