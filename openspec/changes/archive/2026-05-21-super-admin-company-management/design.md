## Context

La plataforma es multi-tenant: cada empresa es un tenant y el `BelongsToCompany` trait aplica un global scope que filtra por `company_id` del usuario autenticado. El super-admin tiene `company_id = null`, lo que hace que el scope no aplique filtros — lo que significa que las queries estándar de modelos con ese trait devuelven datos de **todas** las empresas sin discriminar. El super-admin no tiene un panel propio; actualmente cae en el Dashboard de métricas, que asume que el usuario tiene empresa activa y mostraría datos mezclados.

El modelo `Company` no usa `BelongsToCompany` (es el root tenant), por lo que `Company::query()` ya devuelve todas las empresas correctamente para el super-admin.

## Goals / Non-Goals

**Goals:**
- Panel dedicado `/super-admin/companies` solo accesible por `role:super-admin`
- Listar todas las empresas con datos clave (nombre, slug, plan, fecha de creación)
- Editar cualquier campo del modelo Company (nombre, slug, logo, timezone, país, plan, trial)
- Ver admins existentes de cada empresa y crear nuevos con contraseña visible (patrón `created_password`)
- Redirigir al super-admin fuera del Dashboard de métricas (que no aplica a su rol)
- Navegación lateral diferenciada para super-admin

**Non-Goals:**
- Impersonación / operar como admin de una empresa
- Eliminar empresas
- Gestión de empleados desde este panel
- Envío de emails de bienvenida al admin creado

## Decisions

### 1. Namespace y ubicación del controlador: `SuperAdmin/` en Http/Controllers

**Decisión**: `App\Http\Controllers\SuperAdmin\CompanyController`

**Rationale**: Separa claramente la responsabilidad de gestión de plataforma de la gestión de empresa. Sigue el patrón ya establecido en `Admin/TimeEntryController` y `Settings/CompanyProfileController`.

**Alternativa descartada**: Usar el `Settings/CompanyProfileController` existente y agregar lógica condicional para super-admin. Descartado porque mezclaría dos contextos muy diferentes (admin editando su empresa vs. super-admin editando cualquier empresa) y generaría condicionales `isSuperAdmin()` en código de presentación.

---

### 2. Gestión de password para nuevo admin: contraseña generada visible

**Decisión**: Generar contraseña aleatoria con `Str::password(12)`, crear el usuario, hacer flash de `created_password` a la sesión, redirigir a la misma página de edición.

**Rationale**: El patrón `created_password` ya existe en `EmployeeController` y está implementado en frontend (`Employees/Show.vue`) y en los shared flash data de `HandleInertiaRequests`. Reutilizar el patrón evita inventar uno nuevo.

**Alternativa descartada**: Envío de email de reset de contraseña (Fortify `PasswordResetLinkController`). Descartado porque requiere que el servidor de email esté configurado y el usuario tenga email válido desde el inicio. Se puede agregar en una iteración futura.

---

### 3. Action para creación de admin: nueva `CreateCompanyAdminUser`

**Decisión**: Crear `App\Domain\Company\Actions\CreateCompanyAdminUser` que encapsula: crear `User` con `company_id`, asignar rol `admin`, retornar `[$user, $plainPassword]`.

**Rationale**: Sigue el patrón de Actions del dominio (`RegisterCompany`, `AdminClockIn`, etc.). El controlador queda delgado.

---

### 4. Query de usuarios admin de una empresa: `withoutGlobalScopes()`

**Decisión**: Para listar los admins existentes de una empresa usar `User::withoutGlobalScopes()->where('company_id', $company->id)->role('admin')->get()`.

**Rationale**: El super-admin tiene `company_id = null`. Sin `withoutGlobalScopes()`, el `CompanyScope` no agrega filtro (porque `auth()->user()->company_id` es null), por lo que técnicamente devolvería todos los usuarios. Sin embargo, ser explícito con `withoutGlobalScopes()` hace la intención clara y previene bugs si el scope cambia en el futuro.

---

### 5. Redirect del Dashboard para super-admin

**Decisión**: En `DashboardController::__invoke()`, agregar al inicio: `if ($user->isSuperAdmin()) { return redirect()->route('super-admin.companies.index'); }`

**Rationale**: Es el punto de entrada más simple. El Dashboard de métricas presupone una empresa activa; intentar adaptarlo para super-admin sería complejo sin beneficio real.

---

### 6. Navegación lateral: sección "Plataforma" condicionada a `isSuperAdmin`

**Decisión**: En `AppSidebar.vue`, agregar computed separado `superAdminNavItems` con link a `/super-admin/companies`. Ocultar los items de `isAdmin` (Employees, Schedules, etc.) cuando el usuario es super-admin (no aplican a su contexto).

**Rationale**: El super-admin no opera empresas propias; mostrarle menús de admin de empresa crea confusión. La sección "Plataforma" comunica claramente el contexto de gestión global.

---

### 7. Validación del slug: único globalmente

**Decisión**: En `UpdateSuperAdminCompanyRequest`, la regla para `slug` usa `Rule::unique('companies', 'slug')->ignore($company->id)`.

**Rationale**: El slug es la clave pública de cada empresa (usada en rutas de kiosk como `/kiosk/{company:slug}`). Debe ser único a nivel de plataforma.

## Risks / Trade-offs

**[Riesgo] Super-admin puede cambiar el slug de una empresa activa** → Rutas de kiosk existentes dejan de funcionar.
Mitigación: Documentar en el formulario que cambiar el slug rompe URLs existentes. En el futuro, agregar redirección automática de slugs antiguos.

**[Riesgo] Contraseña del admin creada en texto plano, solo visible una vez** → Si se cierra la pestaña antes de copiarla, se pierde.
Mitigación: El patrón ya existe en el sistema para empleados. Agregar instrucción clara en la UI: "Guarda esta contraseña — no volverá a mostrarse."

**[Trade-off] No se verifica si la empresa ya completó onboarding antes de crear un admin** → Podría crearse un segundo admin antes de que el primero complete el onboarding.
Aceptado: El super-admin es un actor privilegiado con conocimiento del estado del sistema.

## Migration Plan

No requiere migración de base de datos.

Despliegue: cambio de código puro. No hay estado previo que migrar.
