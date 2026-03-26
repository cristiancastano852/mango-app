# Contraseña de empleado gestionada por el administrador

## [Original]

> "cuando un administrador crea un nuevo employee, la contrasena se genera con un teto random, quiero agregar que cuando se llena el formulario el usuario le pueda agregar una contrasena y que le muestre al final la contrasena, adicional que cuando vea el detalle del employee, el usuario admin pueda copiar la contrasena pero que la contrasena visualmente se vea con puntos .... o asteriscos para que no sea un texto plano, pero que tenga un icono de copiar para que se la pueda dar el empleado"

---

## [Enhanced]

### User Story
Como **administrador de empresa**, quiero poder establecer u obtener la contraseña de acceso de un empleado al momento de crearlo, para poder compartírsela directamente sin exponer datos sensibles ni requerir flujos de recuperación.

### Descripción
Actualmente, `CreateEmployee` genera una contraseña aleatoria de 16 caracteres con `Str::random(16)` y la hashea sin exponerla. El administrador no tiene manera de conocer esa contraseña y el empleado no recibe ninguna notificación, lo que crea fricción en el onboarding.

El cambio consiste en agregar un campo opcional `password` al formulario de creación de empleados. Si el administrador lo completa, esa contraseña se usa; si lo deja vacío, se genera una aleatoria. En ambos casos, la contraseña en texto plano se muestra **una única vez** tras la creación exitosa en una pantalla de confirmación antes de redirigir al listado.

No se almacena la contraseña en texto plano ni cifrada en la base de datos. Una vez que el admin abandona la pantalla de confirmación, la contraseña no es recuperable desde la aplicación. Esto evita exponer datos sensibles y elimina la necesidad de gestionar columnas de contraseña recuperable.

### Contexto técnico
- **Dominio:** `app/Domain/Employee/`
- **Tablas involucradas:** `users` — sin cambios de schema
- **Roles con acceso:** `admin`, `super-admin` (middleware `role:admin|super-admin` ya aplicado al resource `employees`)
- **Multi-tenant:** Sí. El `company_id` en `users` garantiza que un admin solo accede a empleados de su propia empresa. La validación cross-company ya está cubierta por el global scope `BelongsToCompany`.

### Criterios de aceptación
- [ ] El formulario de creación incluye un campo `password` opcional con toggle mostrar/ocultar.
- [ ] Si `password` se deja vacío, se genera automáticamente con `Str::random(16)`.
- [ ] Tras la creación exitosa, se redirige al admin a `employees.show` con la contraseña en texto plano via Inertia flash (`created_password`).
- [ ] `Employees/Show.vue` detecta el flash `created_password` y muestra un banner/card de confirmación con la contraseña.
- [ ] En el banner, la contraseña se muestra enmascarada (`••••••••••••`) con un botón de toggle para revelarla y un botón de copiar (icono `Copy`) que copia al portapapeles.
- [ ] El icono de copiar cambia a `Check` por ~2 segundos como feedback visual.
- [ ] El banner incluye un aviso claro: "Guarda esta contraseña, no volverá a mostrarse."
- [ ] El banner desaparece al navegar fuera de la página (comportamiento natural del flash de Inertia).
- [ ] La contraseña no se almacena en ninguna columna adicional de la base de datos.

### Desglose técnico — Backend

- **Migración:** ninguna — no hay cambios de schema.

- **Action `CreateEmployee`** (`app/Domain/Employee/Actions/CreateEmployee.php`):
  - Recibe `$data['password']` (opcional); si está presente usa ese valor, si no genera `Str::random(16)`.
  - Retorna el `Employee` creado junto con la contraseña en texto plano para que el controller la incluya en el flash.
  - Firma sugerida: `public function execute(array $data): array` → `['employee' => $employee, 'plain_password' => $plainPassword]`.

- **Form Request `StoreEmployeeRequest`** (`app/Http/Requests/Employee/StoreEmployeeRequest.php`):
  - Agregar regla: `'password' => ['nullable', 'string', 'min:8', 'max:128']`

- **Controller `EmployeeController::store()`** (`app/Http/Controllers/EmployeeController.php`):
  - Desestructurar el resultado de la action: `['employee' => $employee, 'plain_password' => $plainPassword]`.
  - Redirigir a `employees.show` con `->with('created_password', $plainPassword)`.

- **Ruta:** sin cambios — `Route::resource('employees', EmployeeController::class)` ya existe en `routes/web.php`.

- **Tests requeridos** (en `tests/Feature/EmployeeControllerTest.php`):
  - `test_admin_can_create_employee_with_custom_password`: verifica que el flash `created_password` coincide con la contraseña enviada.
  - `test_admin_can_create_employee_without_password_and_random_is_generated`: verifica que el flash `created_password` existe y tiene al menos 8 caracteres.
  - `test_super_admin_can_create_employee_with_password`: happy path para super-admin.
  - `test_password_validation_min_8_chars`: verifica que una contraseña de menos de 8 chars devuelve error de validación.
  - `test_created_password_flash_is_not_persisted_in_database`: verifica que la tabla `users` no tiene columna `plain_password` (o que no se almacena en texto plano).

### Desglose técnico — Frontend

- **Páginas Vue a modificar:**
  - `resources/js/pages/Employees/Show.vue` — detectar `$page.props.flash.created_password` y renderizar banner de confirmación.
  - `resources/js/pages/Employees/partials/EmployeeForm.vue` — agregar campo `password` con toggle show/hide.

- **Componentes UI a reutilizar** (de `resources/js/components/ui/`):
  - `Input` — campo de contraseña con `type="password"` / `type="text"` según toggle.
  - `Button` — botón icon-only para toggle de visibilidad y para copiar.
  - `Alert` / `Card` — banner de contraseña generada en `Show.vue`.

- **Props de Inertia:**
  - `Show.vue`: leer `usePage().props.flash.created_password` (string | undefined | null) — disponible solo en la carga inmediatamente posterior a la creación.
  - No se agrega ninguna prop persistente al controller `show()` para la contraseña.

- **Wayfinder imports:** sin cambios de rutas.

- **i18n — claves nuevas** en `lang/en/` y `lang/es/` (o `resources/js/locales/` según convención del proyecto):

  Bajo `employees.form`:
  ```json
  "password": "Password (optional)",
  "password_placeholder": "Leave empty to auto-generate",
  "show_password": "Show password",
  "hide_password": "Hide password"
  ```

  Bajo `employees.show` (banner post-creación):
  ```json
  "created_password_title": "Employee created successfully",
  "created_password_warning": "Save this password — it will not be shown again.",
  "created_password_label": "Access password",
  "copy_password": "Copy password",
  "password_copied": "Copied!"
  ```

  Equivalentes en español bajo las mismas claves en `es.json`.

### Requisitos no funcionales
- **Seguridad:**
  - La contraseña en texto plano solo viaja en el flash de Inertia (memoria del servidor, no almacenada en BD).
  - No se serializa la contraseña en JSON ni en ninguna respuesta de API.
  - Validar mínimo 8 caracteres y máximo 128 en `StoreEmployeeRequest`.
- **Performance:** sin impacto — no hay queries adicionales ni cambios de schema.

### Definición de Done
- [ ] `CreateEmployee` retorna `['employee', 'plain_password']`; usa contraseña del form o genera aleatoria.
- [ ] `EmployeeController::store()` redirige a `employees.show` con flash `created_password`.
- [ ] `StoreEmployeeRequest` valida campo `password` opcional (nullable, min:8, max:128).
- [ ] `EmployeeForm.vue` tiene campo password con toggle mostrar/ocultar.
- [ ] `Show.vue` muestra banner con contraseña enmascarada, toggle y botón copiar cuando el flash existe.
- [ ] Banner incluye aviso "no volverá a mostrarse".
- [ ] Claves i18n agregadas en `en.json` y `es.json`.
- [ ] Tests nuevos pasando: `php artisan test --compact tests/Feature/EmployeeControllerTest.php`.
- [ ] `vendor/bin/pint --dirty` sin errores.
- [ ] `npm run build` exitoso.
