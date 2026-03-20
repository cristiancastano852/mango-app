## Context

MangoApp actualmente solo tiene rutas protegidas por autenticación y tenant middleware. No existe ningún punto de entrada público. El flujo completo de adquisición (descubrir → comparar precios → registrarse → configurar) debe implementarse sin romper las rutas existentes del tenant.

El `CompanyObserver` ya siembra `SurchargeRule` y festivos colombianos al crear una empresa, lo que simplifica el wizard post-registro.

## Goals / Non-Goals

**Goals:**
- Landing page pública estática (no requiere auth ni tenant)
- Registro de empresa crea Company + User admin en una transacción atómica
- Wizard de 3 pasos que configura lo mínimo para empezar a usar la app
- Tour de tooltips que se muestra una sola vez al admin en el dashboard
- Migración no destructiva: columna `onboarding_completed` con default false

**Non-Goals:**
- Integración con Stripe (la pricing page es solo informativa en esta fase)
- Onboarding para empleados
- Multi-idioma en la landing
- Tour interactivo con highlights DOM (solo tooltips secuenciales simples)
- Verificación de email post-registro (fuera de scope MVP)

## Decisions

### 1. Rutas públicas fuera del grupo `tenant`

**Decisión**: Las rutas `/`, `/pricing` y `/register/company` van en `routes/web.php` sin middleware `tenant` ni `auth`. Solo el wizard (`/onboarding/*`) requiere `auth`.

**Alternativa considerada**: Crear un subdomain `www.` separado. Rechazada — complejidad innecesaria en Bref/Lambda; el TenantFinder de Spatie ya distingue por subdomain, las rutas sin tenant simplemente no pasan por el finder.

**Rationale**: Patrón estándar SaaS — landing en dominio raíz, app en subdomain del tenant. En desarrollo local y staging, la landing es accesible en el mismo dominio.

### 2. Registro crea Company + User en una Action atómica

**Decisión**: `RegisterCompany` action en `app/Domain/Company/Actions/` ejecuta en `DB::transaction`: crea Company → asigna rol `admin` al User → llama a `CompanyObserver` implícitamente (el observer ya corre en `Company::created`).

**Alternativa considerada**: Reusar `CreateCompany` existente. No existe aún como action formal; el observer sí existe. La nueva action envuelve todo el flujo de registro.

**Rationale**: Transacción única previene estados inconsistentes (empresa sin admin o admin sin empresa).

### 3. Wizard como páginas Inertia separadas (no stepper client-side)

**Decisión**: Cada paso del wizard es una ruta/página Inertia independiente: `/onboarding/company`, `/onboarding/schedule`, `/onboarding/break-types`. El progreso se persiste en BD al completar cada paso.

**Alternativa considerada**: Componente wizard client-side con estado en Vue. Rechazada — si el usuario recarga o cierra el browser, pierde el progreso. Con rutas separadas, se puede retomar desde donde se quedó.

**Rationale**: Laravel maneja el estado del wizard; el frontend solo renderiza el paso actual.

### 4. Tour guiado con localStorage + prop Inertia

**Decisión**: El backend pasa `showTour: true` como prop Inertia cuando `onboarding_completed = true` Y la sesión no tiene `tour_dismissed`. El frontend (Dashboard.vue) muestra el tour y al cerrar llama a `POST /tour/dismiss` que setea la sesión.

**Alternativa considerada**: Guardar `tour_dismissed` en BD. Rechazada — es preferencia de UI, no datos de negocio. La sesión es suficiente.

**Rationale**: Simple, sin migración adicional, desacoplado del wizard.

### 5. `onboarding_completed` en `companies`, no en `users`

**Decisión**: Columna booleana en `companies` tabla. Cuando el admin completa el wizard, todos los admins de esa empresa ven el tour (nueva feature: si hay múltiples admins en el futuro).

**Rationale**: El onboarding es de la empresa, no del usuario. Consistente con el modelo multi-tenant.

## Risks / Trade-offs

- **[Risk] Spam de registros falsos** → Mitigation: Honeypot field en el form + rate limiting en la ruta de registro (60 req/min por IP).
- **[Risk] Admin abandona el wizard a mitad** → Mitigation: La app funciona sin completar el wizard; el flag `onboarding_completed` permanece `false` y el admin puede volver a `/onboarding/company` manualmente. Un middleware redirige si `!onboarding_completed` al entrar al dashboard (solo una vez).
- **[Risk] Observer dispara en tests de registro** → Mitigation: Usar `Company::withoutEvents()` en tests que no necesiten el observer, o aceptar que el seeder corre (ya está probado).
- **[Trade-off] Tour en sesión vs BD** → La sesión se pierde si el admin usa otro browser/dispositivo. Aceptable para MVP.

## Migration Plan

1. Crear migración: `ALTER TABLE companies ADD COLUMN onboarding_completed BOOLEAN DEFAULT FALSE`
2. Empresas existentes quedan con `onboarding_completed = false` — no se les muestra el wizard (el middleware solo actúa si vienen de un registro nuevo, no para admins existentes)
3. Deploy en Bref: migración corre via `php artisan migrate` en el pipeline CI/CD existente
4. Rollback: `ALTER TABLE companies DROP COLUMN onboarding_completed`

## Open Questions

- ¿Las empresas existentes (antes de esta feature) deben tener `onboarding_completed = true` automáticamente para no mostrarles el wizard? → Recomendado: sí, hacer la migración con `UPDATE companies SET onboarding_completed = true WHERE created_at < NOW()` para no interrumpir a usuarios existentes.
