## 1. Backend — Action y Form Request

- [x] 1.1 Modificar `CreateEmployee::execute()` para aceptar `$data['password']` opcional: si presente usar ese valor, si no `Str::random(16)`. Cambiar retorno a `array{employee: Employee, plain_password: string}`.
- [x] 1.2 Actualizar `StoreEmployeeRequest` añadiendo regla `'password' => ['nullable', 'string', 'min:8', 'max:128']`.

## 2. Backend — Controller

- [x] 2.1 Actualizar `EmployeeController::store()`: desestructurar `['employee', 'plain_password']` del resultado de la action y redirigir a `employees.show` con `->with('created_password', $plainPassword)` en lugar de `employees.index`.

## 3. Backend — Tests

- [x] 3.1 Agregar test `test_admin_can_create_employee_with_custom_password`: verifica que el flash `created_password` coincide con la contraseña enviada y que el empleado se crea correctamente.
- [x] 3.2 Agregar test `test_admin_can_create_employee_without_password_and_random_is_generated`: verifica que el flash `created_password` existe y tiene al menos 16 caracteres.
- [x] 3.3 Agregar test `test_super_admin_can_create_employee_with_password`: happy path para super-admin con contraseña custom.
- [x] 3.4 Agregar test `test_password_validation_min_8_chars`: verifica que contraseña de menos de 8 chars devuelve error de validación en campo `password`.
- [x] 3.5 Ejecutar `php artisan test --compact tests/Feature/EmployeeControllerTest.php` y confirmar que todos los tests pasan.
- [x] 3.6 Ejecutar `vendor/bin/pint --dirty --format agent` y corregir cualquier issue de formato.

## 4. Frontend — Formulario de creación

- [x] 4.1 Agregar prop `showPassword` (default `true`) a `EmployeeForm.vue`. Añadir campo `password` con `type="password"` y botón toggle mostrar/ocultar (iconos `Eye`/`EyeOff` de `lucide-vue-next`). Mostrar el campo solo si `showPassword` es `true`.
- [x] 4.2 En `Create.vue`, agregar `password: ''` al objeto `useForm` y pasar `showPassword: true` al componente `EmployeeForm`.
- [x] 4.3 En `Edit.vue`, pasar `showPassword: false` a `EmployeeForm` para que el campo no aparezca en la edición.

## 5. Frontend — Banner post-creación en Show.vue

- [x] 5.1 En `Show.vue`, leer `usePage().props.flash` y detectar `created_password`. Renderizar un banner/card de confirmación cuando el valor exista.
- [x] 5.2 El banner MUST mostrar la contraseña enmascarada (`••••••••••••`) por defecto con un botón toggle de visibilidad (iconos `Eye`/`EyeOff`) y un botón de copiar (icono `Copy` → `Check` por 2 segundos tras copiar con `navigator.clipboard.writeText()`).
- [x] 5.3 El banner MUST incluir el aviso "Guarda esta contraseña, no volverá a mostrarse." usando el componente `Alert` existente.
- [x] 5.4 Extender el tipo global `PageProps` en `resources/js/types/global.d.ts` añadiendo `flash: { created_password?: string | null }`.

## 6. Frontend — i18n y build

- [x] 6.1 Añadir claves en `resources/js/locales/en.json` bajo `employees`: `form.password`, `form.password_placeholder`, `form.show_password`, `form.hide_password`, `show.created_password_title`, `show.created_password_warning`, `show.copy_password`, `show.password_copied`.
- [x] 6.2 Añadir las mismas claves en `resources/js/locales/es.json` con las traducciones al español.
- [x] 6.3 Ejecutar `npm run build` y verificar que compila sin errores.
