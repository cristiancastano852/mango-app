## Why

El registro público de empresas en `/register/company` permite que cualquier persona cree una empresa sin supervisión, lo cual ya no es el modelo de negocio deseado. El producto pasa a un modelo donde el super-admin gestiona manualmente el alta de nuevas empresas, con control total sobre quién accede a la plataforma.

## What Changes

- **BREAKING** La ruta pública `GET /POST /register/company` se deshabilita (devuelve 404 o redirige a home).
- El super-admin puede crear una nueva empresa desde `/super-admin/companies/create` en un único formulario que incluye el primer usuario administrador.
- Los 4 botones CTA de la landing page (`/`) cambian de enlazar a `/register/company` a enlazar a WhatsApp (`https://wa.me/573158978036`) con texto "Contáctame".
- El `CompanyObserver` (que siembra `SurchargeRule` y festivos) continúa disparándose automáticamente al crear la empresa desde el panel super-admin.

## Capabilities

### New Capabilities
- `super-admin-company-creation`: Super-admin crea una empresa nueva junto con su primer usuario administrador en un único formulario atómico desde el panel de gestión.

### Modified Capabilities
- `company-registration`: **BREAKING** — el requisito cambia de "visitante puede registrar empresa públicamente" a "la ruta pública está deshabilitada; solo el super-admin puede crear empresas".
- `super-admin-companies`: Se añade el flujo de creación de empresa (antes solo existía edición, actualización y creación de admin-users).
- `public-landing`: Los CTAs de pricing y hero ya no enlazan a `/register/company` sino a WhatsApp para contacto.

## Non-goals

- No se cambia el flujo de onboarding (sigue existiendo para empresas recién creadas).
- No se cambia la gestión de usuarios admin existentes en el panel super-admin.
- No se modifica el modelo de datos ni se agrega ninguna migración de BD.

## Impact

**Backend:**
- `app/Http/Controllers/CompanyRegistrationController.php` — deshabilitar rutas
- `routes/web.php` — eliminar/comentar rutas de `/register/company`
- `app/Http/Controllers/SuperAdmin/CompanyController.php` — agregar `create()` + `store()`
- Nueva `app/Http/Requests/SuperAdmin/StoreCompanyRequest.php`
- `app/Domain/Company/Actions/RegisterCompany.php` — extraer lógica reutilizable sin `Auth::login()`

**Frontend:**
- `resources/js/pages/SuperAdmin/Companies/Index.vue` — botón "Nueva empresa"
- Nueva `resources/js/pages/SuperAdmin/Companies/Create.vue`
- `resources/js/pages/Landing/Index.vue` — cambiar 4 CTAs a WhatsApp

**Tests:**
- `tests/Feature/CompanyRegistrationTest.php` — actualizar para reflejar que la ruta está deshabilitada
- Nueva suite de tests para `super-admin-company-creation`

**Roles afectados:**
- `super-admin`: gana capacidad de crear empresas
- Visitantes anónimos: pierden acceso a `/register/company`
