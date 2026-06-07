## 1. Rediseño visual del estado `clocked_in`

- [x] 1.1 En `resources/js/pages/Kiosk/Index.vue`, reestructurar el bloque `entryStatus === 'clocked_in'`: "Iniciar pausa" como primaria ámbar y "Finalizar jornada" como secundaria, separadas por un divisor con microcopy ("¿Terminaste?")
- [x] 1.2 Añadir estilos terracota/outline para "Finalizar jornada" (`kiosk-btn--danger` o similar) y el divisor con label, siguiendo las convenciones de estilos del componente
- [x] 1.3 Verificar visualmente en los tres tamaños relevantes que los botones se leen como acciones distintas

## 2. Modal de confirmación de finalizar jornada

- [x] 2.1 Añadir estado reactivo `showClockOutConfirm` y computeds para hora de entrada y tiempo trabajado (calculado en cliente desde `clock_in`)
- [x] 2.2 "Finalizar jornada" ya no llama `doClockOut()` directo: abre el modal. `doClockOut()` se invoca solo al confirmar
- [x] 2.3 Implementar el modal con el lenguaje visual del kiosco: título, hora de entrada, tiempo trabajado, botón primario "No, volver" y botón deliberado "Sí, finalizar"
- [x] 2.4 Cerrar el modal al cancelar sin ejecutar acción; asegurar que se cierra también al cambiar de pantalla (watch sobre `screen`/`entryStatus`)

## 3. i18n

- [x] 3.1 Añadir claves de traducción del modal y microcopy en `resources/js/locales/es.json` y `en.json` (si el kiosco usa i18n; si usa texto en español hardcodeado, seguir esa convención existente del componente)

## 4. Tests

- [x] 4.1 Test que garantiza que el payload/estado del kiosco en `on_break` NO expone la acción de finalizar jornada (invariante del punto 1)
- [x] 4.2 Correr los tests del kiosco con filtro y dejarlos en verde

## 5. Build y verificación

- [x] 5.1 Correr `npm run build` y verificar que no hay errores
- [x] 5.2 Verificación manual del flujo: clocked_in → tap finalizar → modal → cancelar (no pasa nada) y confirmar (finaliza)
