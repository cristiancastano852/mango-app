## Context

Hoy cualquier visitante puede crear una empresa en `/register/company`. El producto cambia a un modelo donde solo el super-admin da de alta empresas manualmente. Además, la landing page tiene 4 CTAs que apuntan a ese formulario y deben reconvertirse a contacto por WhatsApp.

Estado actual:
- `RegisterCompany` action crea empresa + usuario admin + hace `Auth::login()` en una transacción.
- `CompanyRegistrationController` maneja la ruta pública (sin auth).
- `SuperAdmin\CompanyController` tiene index, edit, update y storeAdminUser — **no tiene create ni store**.
- Landing page apunta 4 CTAs a `registerCompany()`.

## Goals / Non-Goals

**Goals:**
- Deshabilitar la ruta pública `/register/company` (404).
- Agregar `create` + `store` al panel super-admin para crear empresa + primer admin en un solo paso.
- Cambiar los CTAs de la landing a un link de WhatsApp.

**Non-Goals:**
- No se modifica el wizard de onboarding (sigue existiendo).
- No se agrega migración de BD.
- No se cambia la gestión de admins en la pantalla de edición existente.

## Decisions

### 1. Reutilizar `RegisterCompany` action vs. nueva action

**Decisión**: Crear una nueva action `CreateCompanyWithAdmin` en `app/Domain/Company/Actions/`.

**Rationale**: `RegisterCompany` llama `Auth::login()` — comportamiento incorrecto para un super-admin que no debe cambiar de sesión. En lugar de añadir un flag condicional a `RegisterCompany` (que la complica), se extrae la lógica compartida en una nueva action sin side-effects de sesión. `RegisterCompany` queda sin uso activo pero no se elimina por ser parte del spec de `company-registration` (se podría borrar en un cambio posterior).

### 2. Deshabilitar la ruta: 404 vs. redirect

**Decisión**: Las rutas de `CompanyRegistrationController` se eliminan de `web.php`. Cualquier acceso devuelve 404 por defecto de Laravel.

**Rationale**: Un redirect a home podría confundir a bots que cachean la URL. Un 404 limpio es el comportamiento correcto para una ruta que deja de existir.

### 3. Formulario super-admin: empresa + admin en un paso vs. dos pasos

**Decisión**: Un único formulario (`Create.vue`) con campos de empresa y del primer admin, enviado en una transacción atómica.

**Rationale**: La empresa nunca queda "huérfana" sin acceso. Reduce fricción operativa para el super-admin. Consistente con el flujo anterior del registro público.

### 4. Contraseña del admin creado

**Decisión**: Seguir el patrón existente de `CreateCompanyAdminUser` — generar contraseña aleatoria segura y mostrarla una sola vez via flash `created_password`.

**Rationale**: Consistencia con el comportamiento actual del panel super-admin. El super-admin copia la contraseña y se la comunica al cliente.

## Risks / Trade-offs

- **`RegisterCompany` queda como código muerto** → Mitigación: Se desregistran sus rutas; un futuro refactor puede eliminarlo. No hay riesgo funcional.
- **Super-admin puede crear empresas sin límite** → Es intencional; no hay regla de negocio que lo restrinja.
- **Los tests de `CompanyRegistrationTest` fallarán** → Mitigación: Actualizar los tests para reflejar que la ruta devuelve 404.

## Migration Plan

1. Eliminar rutas de `/register/company` en `web.php`.
2. Crear `CreateCompanyWithAdmin` action.
3. Agregar `create()` + `store()` a `SuperAdmin\CompanyController`.
4. Crear `StoreCompanyRequest`.
5. Generar Wayfinder + build frontend.
6. Crear `SuperAdmin/Companies/Create.vue`.
7. Agregar botón "Nueva empresa" en `Index.vue`.
8. Actualizar CTAs en `Landing/Index.vue`.
9. Actualizar/reescribir `CompanyRegistrationTest` + nueva suite para creación super-admin.

**Rollback**: Revertir eliminación de rutas en `web.php`. Sin cambios de BD, el rollback es trivial.

## Open Questions

- ¿La contraseña del admin debe ser generada aleatoriamente (segura) o continuar con el hardcoded `'password'` actual? — Asumimos que se mantiene el comportamiento actual (`'password'`) para consistencia con `CreateCompanyAdminUser`.
