## Context

Actualmente los empleados deben autenticarse con email/contraseña para registrar tiempo. En ambientes donde varios trabajadores comparten un mismo computador físico, esto obliga a cerrar y abrir sesión repetidamente durante el día, lo cual es lento y genera fricción innecesaria.

El sistema ya tiene todas las actions de dominio necesarias (`ClockIn`, `ClockOut`, `StartBreak`, `EndBreak`) y la empresa ya tiene un `slug` único. Lo que falta es una capa pública que opere sobre esas actions usando un identificador de empleado (número de documento).

## Goals / Non-Goals

**Goals:**
- Ruta pública `/kiosk/{company:slug}` funcional sin autenticación
- Campo `document_number` en `employees`, único por empresa
- Flujo completo: lookup → acción → confirmación → reset
- Mostrar solo el estado del día actual (sin histórico)
- Campo disponible en formularios admin de crear/editar empleado

**Non-Goals:**
- Soporte de subdominios por empresa
- PIN o doble factor en el kiosco
- Histórico de días anteriores en la vista kiosco
- Cronómetro en tiempo real en el kiosco
- Modificar el flujo de autenticación existente

## Decisions

### 1. Campo `document_number` en `employees`, no en `users`

`users` es una entidad de autenticación. El número de documento es un dato laboral/RR.HH. que pertenece al empleado dentro de una empresa. Además, la unicidad es por empresa (dos empresas distintas podrían tener empleados con la misma cédula).

**Alternativa descartada:** campo en `users` — rompería la semántica del modelo y complicaría la unicidad multi-tenant.

### 2. Rutas del kiosco fuera del grupo `auth`, bajo `/kiosk/{company:slug}`

El kiosco debe ser accesible sin sesión. Se crea un grupo de rutas con middleware mínimo (throttle, verifycsrftoken excluido opcionalmente para las actions POST del kiosco o usando un token de sesión guest).

**CSRF en rutas POST del kiosco:** Se incluye CSRF normalmente (Inertia lo maneja con el header `X-XSRF-TOKEN`). No es necesario excluir del CSRF porque Inertia envía el token automáticamente desde el frontend.

**Alternativa descartada:** ruta bajo `/api/kiosk` — las rutas API no tienen sesión/cookies, lo cual complica el CSRF y el estado de Inertia.

### 3. Nuevo `KioskController` en `app/Http/Controllers/`

El kiosco no pertenece a un dominio específico — orquesta `TimeTracking` actions usando un employee encontrado por `document_number`. Se ubica en el namespace raíz de Controllers, igual que `TimeClockController`.

```
GET  /kiosk/{company:slug}                    → KioskController@index
POST /kiosk/{company:slug}/lookup             → KioskController@lookup
POST /kiosk/{company:slug}/clock-in           → KioskController@clockIn
POST /kiosk/{company:slug}/clock-out          → KioskController@clockOut
POST /kiosk/{company:slug}/break/start        → KioskController@startBreak
POST /kiosk/{company:slug}/break/end          → KioskController@endBreak
```

### 4. Lookup sin sesión persistente — employee_id en sesión de corta duración

Tras el lookup, el `employee_id` se guarda en sesión (`kiosk_employee_id`) para las actions POST subsiguientes. Se limpia después de cada acción. Esto evita pasar el `document_number` en cada POST y es seguro porque la sesión es del browser (no del empleado).

**Alternativa descartada:** pasar `document_number` en cada POST — más requests, más exposición del dato.

### 5. `KioskLayout.vue` — layout público sin AppLayout

La página del kiosco no usa `AppLayout` (que requiere usuario autenticado con sidebar/nav). Se crea un layout mínimo con logo de empresa y fondo neutro.

### 6. Reutilizar actions de dominio existentes

`ClockIn`, `ClockOut`, `StartBreak`, `EndBreak` ya funcionan correctamente. El `KioskController` los invoca con el employee recuperado de sesión, igual que `TimeClockController`.

## Risks / Trade-offs

**[Seguridad: cualquiera con la cédula puede fichar por otro]**
→ Aceptado por diseño. El kiosco es un terminal físico en la empresa. La mitigación es operacional (el trabajador está presente). No se añade PIN por ahora para no aumentar la fricción.

**[Sesión compartida en mismo browser]**
→ Cada acción limpia `kiosk_employee_id` de sesión. El auto-reset de 5s en frontend da tiempo a que el siguiente empleado ingrese su documento limpio.

**[`document_number` nullable — empleados sin documento no pueden usar el kiosco]**
→ El campo es nullable para no romper empleados existentes. El admin debe completarlo para habilitar el kiosco por empleado.

**[Rate limiting]**
→ Aplicar `throttle:10,1` en las rutas POST del kiosco para evitar fuerza bruta sobre document_numbers.

## Migration Plan

1. Migración: añadir `document_number` (string, nullable) a `employees`. Unique index scoped: `unique(['document_number', 'company_id'])`.
2. Actualizar `ai-specs/specs/data-model.md` con el nuevo campo.
3. Implementar backend (Controller, Form Requests, rutas).
4. Implementar frontend (KioskLayout, Kiosk/Index.vue, campo en formularios de empleado).
5. Tests PHPUnit para todos los casos: lookup exitoso, lookup fallido, cada acción, throttle.
6. Rollback: la migración es aditiva (nullable); revertir solo requiere drop de la columna.

## Open Questions

- ¿El admin quiere ver en el panel si un empleado tiene o no `document_number` asignado? (Podría ser útil un badge o indicador en la lista de empleados.) → Decisión UX para el administrador, no bloqueante.
