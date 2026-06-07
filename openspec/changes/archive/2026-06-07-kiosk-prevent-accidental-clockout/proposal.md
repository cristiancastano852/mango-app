## Why

En el kiosco, una empleada que estaba en su almuerzo pulsó "Finalizar jornada" en lugar de la acción que esperaba, y dejó su registro del día cerrado de forma incorrecta. No hay forma de retomar la jornada, así que el registro quedó dañado y requirió corrección manual de un admin.

La causa raíz es de diseño: en el estado `clocked_in`, los botones "Iniciar pausa" y "Finalizar jornada" conviven pegados, con tamaño similar y sin ninguna fricción. "Finalizar jornada" es una acción irreversible (la empleada no puede retomar) y está a un solo toque, sin confirmación. La vista personal sí pide confirmación con `confirm()`; el kiosco —donde más prisa hay— no.

## What Changes

- **Rediseño visual del estado `clocked_in` en el kiosco**: "Iniciar pausa" se mantiene como acción primaria (ámbar, grande); "Finalizar jornada" se separa con un divisor + microcopy ("¿Terminaste?"), se muestra con estilo terracota/outline y tamaño menor. La distinción se da por color, tamaño, icono y separación física —no solo jerarquía— para que sea muy difícil confundirlas.
- **Modal de confirmación al finalizar jornada** en el kiosco: muestra hora de entrada y tiempo trabajado (calculado en cliente desde `clock_in`), con un botón seguro "No, volver" como primario y un botón deliberado "Sí, finalizar".
- **Garantía del invariante**: el estado `on_break` nunca debe exponer "Finalizar jornada" (hoy ya se cumple); se blinda con un test para que ningún cambio futuro lo rompa.

## Capabilities

### Modified Capabilities

- `employee-kiosk`: Se refuerza la prevención de finalización accidental de jornada en el estado `clocked_in` mediante rediseño visual y confirmación.

## Impact

- **Frontend**: Cambios en `resources/js/pages/Kiosk/Index.vue` (estado `clocked_in`, nuevo modal de confirmación, cálculo de tiempo trabajado en cliente, estilos terracota). Posibles claves de traducción nuevas en `resources/js/locales/es.json` y `en.json` si se añaden textos del modal.
- **Backend**: Sin cambios. `ClockOut` sigue cerrando la pausa activa como red de seguridad.
- **Tests**: Test que garantiza que `on_break` no expone la acción de finalizar jornada en el payload/estado del kiosco.

## Non-goals

- No se modifica la vista personal `TimeClock/Index.vue` (mantiene su `confirm()` nativo).
- No se añade opción de retomar/deshacer jornada (recuperación queda fuera de alcance).
- No se modifica el comportamiento de backend de `ClockOut`, `ClockIn`, `StartBreak` ni `EndBreak`.
- No se cambia el flujo de cierre automático de pausa activa al finalizar jornada.
