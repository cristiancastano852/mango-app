## 1. Backend — Regla de descuento de exceso

- [x] 1.1 En `RecalculateTimeEntry`, reemplazar el `sum()` agregado de `break_hours` por una iteración sobre las pausas finalizadas (eager load de `breakType`), encapsulando en un método privado el cálculo por pausa: no pagada → `duration_minutes`; pagada con tope → `max(0, duration_minutes − max_duration_minutes)`; pagada sin tope → `0`. Mantener `net_hours = max(0, gross_hours − break_hours)` y el resto del flujo (clasificación, `status`) intacto.
- [x] 1.2 Ejecutar `vendor/bin/pint --dirty --format agent`.
- [x] 1.3 Escribir un test de feature completo para el cálculo (un archivo, asserts fuertes sobre `break_hours`/`net_hours`) cubriendo: pausa pagada con tope excedida (15/25 → descuenta 10), pausa pagada dentro del tope (no descuenta), pausa pagada sin tope (no descuenta), pausa no pagada (descuenta completa), y un turno con combinación de pausas (30 no pagada + 25/15 pagada + 20 pagada sin tope → `break_hours = 40`). Verificar que el flujo de edición/creación admin produce los mismos `break_hours`.
- [x] 1.4 Ejecutar `php artisan test --compact --filter=` del test nuevo y dejar en verde.

## 2. Frontend — Visibilidad del exceso descontado

- [x] 2.1 Revisar `components/ui/` reutilizables para indicadores; en `resources/js/pages/admin/TimeEntries/Edit.vue` mostrar, por pausa pagada con tope cuya `duration_minutes` lo supera, los minutos descontados por exceso (`duration_minutes − max_duration_minutes`), derivado en la vista sin datos nuevos del backend. No mostrar nada cuando no hay exceso o no hay tope.
- [x] 2.2 Agregar las claves i18n necesarias para la etiqueta del exceso descontado.
- [x] 2.3 Ejecutar `npm run build` y verificar build exitoso.
