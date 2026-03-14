## Context

Mango App tiene configuraciones de tenant parcialmente implementadas: reglas de recargo (`SurchargeRuleController`) y festivos (`HolidayController`) ya existen en `routes/settings.php` con middleware `role:admin|super-admin`. El Settings Layout (`layouts/settings/Layout.vue`) tiene nav items de usuario (profile, password, 2FA, appearance) y admin (Recargos, Festivos).

Se necesitan 3 nuevas capabilities: gestión de tipos de pausa, perfil de empresa (nombre/logo/timezone), y días laborales + horario default. Los modelos `BreakType` y `Company` ya existen con todos los campos necesarios — no se requieren migraciones.

**Convenciones existentes a seguir:**
- Controllers en `Settings/` usan `withoutGlobalScopes()->where('company_id', ...)` para queries
- Los controllers son delgados: Form Request + lógica directa o delegada a Actions
- Rutas admin en `settings.php` bajo middleware `role:admin|super-admin`
- Frontend: páginas en `resources/js/pages/settings/`, layout settings, Wayfinder para rutas

## Goals / Non-Goals

**Goals:**
- Admin puede gestionar tipos de pausa de su empresa (CRUD + activar/desactivar + marcar default almuerzo)
- Admin puede editar datos básicos de empresa: nombre, logo, país, timezone
- Admin puede configurar días laborales y horario por defecto
- Nuevos empleados heredan el schedule default de la empresa si no se asigna uno explícito
- Todo aislado por tenant (company_id)

**Non-Goals:**
- No se modifica la lógica de `StartBreak` ni `EndBreak` (ya validan break_type_id correctamente)
- No se recalculan time entries históricas al cambiar timezone (solo afecta cálculos futuros)
- No se implementa eliminación hard de break types (solo soft-disable via is_active)
- No se implementa gestión de múltiples horarios por día o por empleado (eso ya existe en SchedulesController)
- No se implementa upload de logo avanzado (crop, resize) — solo upload básico

## Decisions

### D1: Agrupar perfil de empresa y timezone en un solo controller

**Decisión:** Un solo `CompanyProfileController` con edit/update que maneja nombre, logo, país y timezone.

**Alternativa considerada:** Controllers separados para logo (upload) y datos básicos. Descartado porque son campos del mismo modelo `companies` y el formulario Vue los agrupa naturalmente.

**Rationale:** Menos endpoints, un solo Form Request con reglas para todos los campos. El upload de logo se maneja con `$request->file('logo')` en el mismo request multipart.

### D2: Días laborales y horario default en companies.settings (jsonb)

**Decisión:** Usar `companies.settings` jsonb existente con keys `working_days` (array) y `default_schedule_id` (int|null). Un `CompanySettingsController` separado del perfil.

**Alternativa considerada:** Agregar columnas dedicadas a `companies`. Descartado porque `settings` jsonb ya existe para este propósito y evita migraciones.

**Rationale:** Separar de perfil porque la UI es distinta (checkboxes de días + selector de schedule vs formulario de texto + file upload). El jsonb es flexible y ya está casteado como array en el modelo Company.

### D3: BreakType CRUD sin Actions — lógica directa en controller

**Decisión:** Para `BreakTypeController`, la lógica de store/update/toggle es suficientemente simple para vivir en el controller, siguiendo el patrón de `HolidayController`.

**Alternativa considerada:** Crear `CreateBreakType`, `UpdateBreakType`, `ToggleBreakTypeActive` como Actions separadas. Esto sería over-engineering dado que: (1) no hay lógica de negocio compleja, (2) no se reutilizan en otros contextos, (3) el patrón establecido en el proyecto para CRUD simple es controller directo (ver `HolidayController`).

**Excepción:** La lógica de `is_default` (desmarcar el anterior al marcar uno nuevo) se implementa inline con `BreakType::where('company_id', ...)->where('is_default', true)->update(['is_default' => false])` antes del create/update.

**Rationale:** Consistencia con `HolidayController` que ya hace create/update/delete directamente.

### D4: Upload de logo con disco public de Laravel

**Decisión:** Usar `Storage::disk('public')->putFile('logos', $file)` para almacenar logos. El path se guarda en `companies.logo`. Para mostrar, se usa `Storage::disk('public')->url($logo)`.

**Alternativa considerada:** Disco S3 o servicio externo. Descartado porque el proyecto no usa S3 actualmente y agrega complejidad innecesaria para logos.

**Requisito previo:** `php artisan storage:link` debe estar ejecutado (crear symlink `public/storage`).

### D5: Modificar CreateEmployee para schedule default — fallback silencioso

**Decisión:** En `CreateEmployee::execute()`, si `$data['schedule_id']` es null, buscar `Company::find($companyId)->settings['default_schedule_id']` y usarlo como fallback. Si tampoco existe, queda null (comportamiento actual).

**Alternativa considerada:** Listener/Observer en Employee creating. Descartado por ser menos explícito y harder to test que la lógica inline en la Action.

### D6: Estructura de nav en Settings Layout

**Decisión:** Agregar 3 items al array `adminNavItems` en el Settings Layout:
1. "Empresa" → `settings/company-profile`
2. "Tipos de pausa" → `settings/break-types`
3. "Días laborales" → `settings/company-settings`

Mantener "Recargos" y "Festivos" como ya están.

**Orden visual:** Empresa, Días laborales, Tipos de pausa, Recargos, Festivos (de más general a más específico).

### D7: CompanyProfile — super-admin accede a su propia vista sin company_id

**Decisión:** Para `CompanyProfileController` y `CompanySettingsController`, el super-admin (company_id = null) ve un estado vacío o un mensaje "No tienes empresa asignada". No se implementa selector de empresa para super-admin en esta fase.

**Alternativa considerada:** Permitir que super-admin seleccione una empresa para editar. Descartado porque agrega complejidad de UI y no es el caso de uso principal. El super-admin puede gestionar empresas desde un futuro panel de admin.

**Rationale:** Mantiene la consistencia con `SurchargeRuleController` que ya usa `$request->user()->company_id` directamente.

## Risks / Trade-offs

**[Risk] Timezone change afecta CalculateWorkHours retroactivamente**
→ Mitigación: documentar que el cambio solo afecta entradas futuras. No se recalculan entries existentes. Considerar un warning en el UI.

**[Risk] Logo upload sin validación de contenido real**
→ Mitigación: Laravel's `image` validation rule verifica que el archivo sea una imagen real (no solo por extensión). Limitar a 2MB.

**[Risk] is_default de break type — race condition**
→ Mitigación: wrap en transacción DB al desmarcar anterior + marcar nuevo. En la práctica, un solo admin edita esto raramente.

**[Risk] Eliminar schedule que es default de empresa**
→ Mitigación: al eliminar un schedule en `SchedulesController`, limpiar `settings.default_schedule_id` si coincide. Agregar test para este caso.

**[Trade-off] No hay migración — todo usa columnas/jsonb existentes**
→ Beneficio: despliegue sin riesgo de BD. Limitación: `companies.settings` no tiene schema enforcement a nivel DB; la validación vive en Form Requests.
