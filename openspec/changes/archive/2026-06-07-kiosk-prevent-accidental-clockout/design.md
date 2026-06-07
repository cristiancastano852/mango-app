## Context

El kiosco (`resources/js/pages/Kiosk/Index.vue`) muestra acciones según el estado del día del empleado. En el estado `clocked_in` (trabajando) se renderizan dos botones apilados: "Iniciar pausa" (ámbar, `kiosk-btn--lg`) y "Finalizar jornada" (ghost, `kiosk-btn--lg`), sin separación ni confirmación. `doClockOut()` hace `router.post` directo.

"Finalizar jornada" es irreversible para la empleada: una vez con `clock_out`, el estado pasa a `clocked_out` (solo mensaje informativo) y `ClockIn` rechaza un nuevo fichaje del día ("Ya completaste tu jornada de hoy"). Solo un admin puede corregirlo.

Por contraste, el estado `on_break` ya solo muestra "Finalizar pausa" —no expone finalizar jornada— y la vista personal usa `confirm()`. El kiosco quedó sin ninguna red de seguridad para la acción más destructiva.

## Goals / Non-Goals

**Goals:**
- Que sea muy difícil confundir "Iniciar pausa" con "Finalizar jornada" en el kiosco.
- Añadir un paso de confirmación intencional antes de finalizar jornada, con contexto útil (hora de entrada, tiempo trabajado).
- Blindar el invariante de que `on_break` nunca exponga finalizar jornada.

**Non-Goals:**
- Recuperación/undo de jornada (no se puede retomar).
- Cambios de backend.
- Cambios en la vista personal `TimeClock/Index.vue`.

## Decisions

### 1. Prevención por distinción visual, no solo por jerarquía

El error fue confundir dos botones pegados de tamaño similar. La solución usa múltiples señales simultáneas: color (ámbar cálido para pausa vs terracota para finalizar), tamaño (finalizar más pequeño), icono diferenciado, y separación física con un divisor + microcopy ("¿Terminaste?").

**Alternativa descartada:** pintar "Finalizar jornada" de rojo de alarma. Es una acción diaria y legítima; un rojo fuerte genera fatiga de alarma. El terracota/outline lee como "fin" sin asustar. La seguridad la carga el modal de confirmación, no el color del botón.

### 2. Confirmación con modal propio, no `confirm()` nativo

El kiosco es una pantalla táctil con estética propia (fondo verde, Fraunces serif, acento ámbar). Un `confirm()` nativo rompería la experiencia y es poco táctil. El modal usa el mismo lenguaje visual del kiosco.

En el modal, el botón seguro ("No, volver") es el primario y fácil; el destructivo ("Sí, finalizar") es deliberado. Quien llegó aquí por error sale con el toque más natural.

El modal muestra **hora de entrada** y **tiempo trabajado** (calculado en cliente desde `clock_in`, sin llamadas extra al backend). El dato ayuda a cazar el error ("solo llevo 6h, no debería estar saliendo").

### 3. Sin backend, manteniendo `ClockOut` como red de seguridad

No se toca `ClockOut`: sigue cerrando la pausa activa si existiera. Como la UI ya no permite finalizar jornada durante una pausa (estado `on_break` no expone el botón), esa rama queda como salvaguarda para llamadas directas, sin ser el camino normal.

### 4. Invariante `on_break` blindado con test

Hoy `on_break` ya solo expone "Finalizar pausa". Se añade un test que verifica que el estado/payload del kiosco en `on_break` no ofrece la acción de finalizar jornada, para que ningún refactor futuro reintroduzca el problema.

## Risks / Trade-offs

- **Un toque extra siempre** para finalizar jornada (el modal). Aceptable: es una acción una vez al día y el costo del error es alto (corrección manual de admin).
- **Cálculo de tiempo trabajado en cliente** no descuenta pausas (es bruto desde `clock_in`). Es suficiente como señal de contexto; no pretende ser el cálculo oficial de horas netas.
