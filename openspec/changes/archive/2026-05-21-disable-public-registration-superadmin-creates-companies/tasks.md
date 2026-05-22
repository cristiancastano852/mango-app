## 1. Deshabilitar registro público

- [x] 1.1 Eliminar las dos rutas `/register/company` (GET y POST) de `routes/web.php`
- [x] 1.2 Actualizar `tests/Feature/CompanyRegistrationTest.php` para verificar que las rutas devuelven 404

## 2. Backend — Creación de empresa desde super-admin

- [x] 2.1 Crear `app/Domain/Company/Actions/CreateCompanyWithAdmin.php` — crea Company + User admin en `DB::transaction` sin `Auth::login()`; retorna `[$company, $user, $plainPassword]`
- [x] 2.2 Crear `app/Http/Requests/SuperAdmin/StoreCompanyRequest.php` — validar `company_name` (required, string, max 255), `admin_name` (required, string, max 255), `admin_email` (required, email, unique:users)
- [x] 2.3 Agregar `create()` y `store()` a `app/Http/Controllers/SuperAdmin/CompanyController.php` — `create()` renderiza `SuperAdmin/Companies/Create`, `store()` llama la action y redirige a edit con flash `created_password`
- [x] 2.4 Agregar rutas en `routes/web.php` dentro del grupo `super-admin`: `GET /super-admin/companies/create` y `POST /super-admin/companies`
- [x] 2.5 Ejecutar `php artisan wayfinder:generate` y `npm run build`
- [x] 2.6 Ejecutar `vendor/bin/pint --dirty --format agent`

## 3. Tests backend

- [x] 3.1 Crear `tests/Feature/SuperAdminCompanyCreationTest.php` — cubrir: creación exitosa (empresa + admin creados, observer disparado, flash `created_password`, redirect a edit), email duplicado (errores de sesión, sin empresa creada), campos vacíos (errores de sesión), acceso 403 para rol `admin`, acceso 403 para rol `employee`, redirect a login para no autenticado
- [x] 3.2 Ejecutar `php artisan test --compact --filter=SuperAdminCompanyCreationTest`
- [x] 3.3 Ejecutar `php artisan test --compact --filter=CompanyRegistrationTest`

## 4. Frontend — Página de creación

- [x] 4.1 Crear `resources/js/pages/SuperAdmin/Companies/Create.vue` — formulario con `company_name`, `admin_name`, `admin_email`; usar `Form` de Inertia con Wayfinder; mostrar `InputError` por campo; botón submit con `Spinner`
- [x] 4.2 Agregar botón "Nueva empresa" en `resources/js/pages/SuperAdmin/Companies/Index.vue` junto al header (alineado a la derecha del título, usando `<a>` con Wayfinder `create()`)
- [x] 4.3 Agregar claves i18n en `resources/js/locales/es.json` y `resources/js/locales/en.json` para los textos nuevos del formulario de creación
- [x] 4.4 Ejecutar `npm run build` y verificar que la página de creación funciona correctamente

## 5. Frontend — Landing page

- [x] 5.1 En `resources/js/pages/Landing/Index.vue`, reemplazar los 4 enlaces `registerCompany()` por `href="https://wa.me/573158978036"` con texto "Contáctame"
- [x] 5.2 Ejecutar `npm run build` y verificar visualmente que los botones de la landing apuntan a WhatsApp
