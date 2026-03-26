## Context

Al crear un empleado, `CreateEmployee` genera `Str::random(16)` y lo pasa a `Hash::make()` sin retornarlo. El controller redirige a `employees.index` con un flash genérico de éxito. El admin no puede conocer la contraseña del empleado creado y el empleado no recibe notificación alguna, creando fricción en el onboarding.

El cambio es localizado: afecta únicamente al dominio `Employee`, sin cambios de schema de BD.

## Goals / Non-Goals

**Goals:**
- Permitir al admin ingresar una contraseña custom al crear un empleado (opcional).
- Si no se ingresa, generar una aleatoria como hasta ahora.
- Mostrar la contraseña **una única vez** en la pantalla `Show` tras la creación, con toggle de visibilidad y botón de copiar.
- No almacenar la contraseña en texto plano ni cifrada en BD.

**Non-Goals:**
- Flujo de "resetear contraseña" desde el panel admin.
- Mostrar o recuperar la contraseña después de haber abandonado la pantalla post-creación.
- Notificación al empleado por email o SMS.
- Modificar la contraseña desde la vista `Edit` de empleado.

## Decisions

### 1. `CreateEmployee` retorna la contraseña en texto plano

**Decisión:** Cambiar la firma de `execute()` para retornar un array `['employee' => $employee, 'plain_password' => $plainPassword]`.

**Alternativa considerada:** Pasar un callback o un objeto DTO. Más complejo sin beneficio real para este caso.

**Rationale:** Es el patrón más simple y consistente con actions existentes. El controller es quien necesita el valor para el flash; la action no debe saber nada del request/response cycle.

---

### 2. Contraseña entregada al frontend vía Inertia flash

**Decisión:** `EmployeeController::store()` redirige a `employees.show` con `->with('created_password', $plainPassword)` en lugar de `employees.index`.

**Alternativa considerada:** Pasar `created_password` como prop directa de Inertia en el `show()`. Esto implicaría almacenarla temporalmente (en sesión o BD) para poder leerla en la siguiente request — innecesario.

**Rationale:** El flash de Laravel/Inertia ya maneja el ciclo de vida de un único request siguiente. No requiere almacenamiento adicional. La contraseña se pierde sola al navegar fuera de la página.

---

### 3. Banner en `Show.vue` — no en `Create.vue`

**Decisión:** El banner de contraseña generada se renderiza en `Show.vue` leyendo `$page.props.flash.created_password`, no en `Create.vue`.

**Alternativa considerada:** Mostrar un modal en `Create.vue` antes de redirigir. Requeriría manejar estado de espera en el frontend y complica el flujo de Inertia.

**Rationale:** Redirigir a `Show` es el patrón natural post-creación (es lo que harían todos los recursos RESTful bien implementados). El flash llega junto con la página de destino.

---

### 4. Campo `password` en `EmployeeForm.vue`

**Decisión:** Agregar el campo directamente al partial `EmployeeForm.vue` con un prop `showPassword` (default `true` en Create, `false` en Edit).

**Alternativa considerada:** Crear un partial separado solo para creación. Innecesaria complejidad para un solo campo.

**Rationale:** `EmployeeForm` ya tiene precedente de `showStatus` prop para controlar visibilidad de campos según contexto (Create vs Edit). Mismo patrón.

---

### 5. `StoreEmployeeRequest` — validación del campo password

**Decisión:** `'password' => ['nullable', 'string', 'min:8', 'max:128']`.

**Rationale:** Nullable porque el campo es opcional. Mínimo 8 caracteres como piso de seguridad razonable. Máximo 128 para evitar payloads abusivos.

## Risks / Trade-offs

- **[Risk] La contraseña se pierde si el admin cierra el tab accidentalmente** → El banner incluye un aviso explícito "Guarda esta contraseña, no volverá a mostrarse." No hay mitigación técnica intencional; es el comportamiento deseado por seguridad.
- **[Risk] Flash de Inertia puede no estar tipado en el frontend** → Extender el tipo global de `PageProps` en `resources/js/types/global.d.ts` para incluir `flash: { created_password?: string }`.
- **[Trade-off] Redirigir a `show` en lugar de `index`** → El admin llega al detalle del empleado recién creado en vez de al listado. Es un cambio de UX menor pero positivo (ve el perfil completo).

## Migration Plan

No hay migración de BD. El despliegue es sin riesgo: los empleados existentes no se ven afectados. No hay rollback necesario más allá de revertir el código.

## Open Questions

_(ninguna — el alcance está completamente definido)_
