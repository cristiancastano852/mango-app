# Fase 11 — Landing Page y Onboarding

## Contexto

Las fases anteriores construyeron el core de MangoApp: control de asistencia, cálculo de horas, panel administrativo y reportes. La Fase 11 cierra el ciclo de adquisición de clientes: cualquier visitante puede descubrir el producto, registrar su empresa de forma autónoma y configurarla en minutos sin intervención manual.

---

## Funcionalidades implementadas

### 1. Landing Page pública

**Controlador:** `app/Http/Controllers/LandingController.php`
**Vista:** `resources/js/pages/Landing/Index.vue`
**Ruta:** `GET /`

Página pública (sin auth ni tenant middleware) con:

- **Hero section** — tagline + CTA directo a `/register/company`
- **Features section** — 4 cards con las funcionalidades clave (fichaje, legislación colombiana, reportes, multi-sede)
- **Pricing section** — 4 planes con precios (Free, Básico $15, Pro $35, Enterprise $75). Plan Pro destacado con borde naranja. Los CTAs enlazan al registro, no a Stripe (informativo en esta fase).
- **Footer** básico

También existe `GET /pricing` que redirige a `/#pricing`.

---

### 2. Registro de empresa

**Action:** `app/Domain/Company/Actions/RegisterCompany.php`
**Controlador:** `app/Http/Controllers/CompanyRegistrationController.php`
**Form Request:** `app/Http/Requests/RegisterCompanyRequest.php`
**Vista:** `resources/js/pages/auth/RegisterCompany.vue`
**Rutas:** `GET /register/company`, `POST /register/company` (throttle: 60/min)

El formulario crea empresa + usuario admin en una **transacción atómica**:

1. Crea `Company` con `timezone = America/Bogota`, `country = CO`, `onboarding_completed = false`
2. Crea `User` con `company_id` y rol `admin`
3. El `CompanyObserver` siembra automáticamente `SurchargeRule` y festivos colombianos
4. Autentica al usuario
5. Redirige a `/onboarding/company`

**Anti-spam:** campo honeypot `website` oculto. Si viene con valor, simula éxito sin crear registros.

**Validaciones:** `company_name` (required, max 255), `name` (required, max 255), `email` (required, email, unique), `password` (required, min 8, confirmed). Mensajes de error en español.

**Guard:** usuarios ya autenticados que acceden a `GET /register/company` son redirigidos a `/dashboard`.

---

### 3. Campo `onboarding_completed` en Company

**Migración:** `2026_03_20_050702_add_onboarding_completed_to_companies.php`

Columna `boolean default false` en la tabla `companies`. La migración actualiza automáticamente las empresas pre-existentes a `true` para no interrumpir a usuarios actuales.

```php
// Model cast
'onboarding_completed' => 'boolean',
```

---

### 4. Wizard de onboarding (3 pasos)

**Middleware:** `app/Http/Middleware/EnsureOnboardingNotCompleted.php` (alias: `onboarding`)

Si `company.onboarding_completed = true`, redirige a `/dashboard`. Registrado en `bootstrap/app.php`.

Todas las rutas del wizard requieren `auth` + middleware `onboarding` + `role:admin`.

#### Paso 1 — Perfil de empresa
**Controlador:** `app/Http/Controllers/Onboarding/OnboardingCompanyController.php`
**Vista:** `resources/js/pages/Onboarding/Company.vue`
**Rutas:** `GET/POST /onboarding/company`

Muestra formulario con nombre, timezone (selector) y país (selector), pre-rellenado con los datos actuales. Al guardar, actualiza la empresa y redirige al paso 2.

#### Paso 2 — Horario de trabajo
**Controlador:** `app/Http/Controllers/Onboarding/OnboardingScheduleController.php`
**Vista:** `resources/js/pages/Onboarding/Schedule.vue`
**Rutas:** `GET/POST /onboarding/schedule`

Formulario con nombre, hora inicio, hora fin y días de la semana (checkboxes). Incluye botón **"Omitir este paso"** — si se omite, no crea ningún schedule y avanza al paso 3.

#### Paso 3 — Tipos de pausa
**Controlador:** `app/Http/Controllers/Onboarding/OnboardingBreakTypesController.php`
**Vista:** `resources/js/pages/Onboarding/BreakTypes.vue`
**Rutas:** `GET/POST /onboarding/break-types`

Muestra los tipos de pausa de la empresa (almuerzo, descanso, baño, personal, médica) con toggles visuales. Al guardar:
- Actualiza `is_active` de cada tipo
- Setea `company.onboarding_completed = true`
- Redirige a `/dashboard` con flash de éxito

#### Componente de progreso
**Componente:** `resources/js/components/OnboardingProgress.vue`

Indicador de 3 pasos con estado visual: completado (verde ✓), activo (naranja), pendiente (gris). Incluido en los 3 pasos del wizard.

---

### 5. Tour guiado

**Controlador:** `app/Http/Controllers/TourController.php`
**Componente:** `resources/js/components/GuidedTour.vue`
**Ruta:** `POST /tour/dismiss`

El tour se muestra al admin cuando:
- `company.onboarding_completed = true` AND
- `session('tour_dismissed')` no está seteado

El `DashboardController` pasa la prop `showTour: bool` a la vista. `Dashboard.vue` renderiza `<GuidedTour>` condicionalmente.

El tour es un modal overlay con 5 pasos secuenciales:
1. Bienvenida
2. KPIs en tiempo real
3. Estado de empleados
4. Check-in manual (FAB)
5. Listo para empezar

Al hacer clic en "Saltar tour" o completar el último paso, se llama `POST /tour/dismiss` que guarda `tour_dismissed = true` en sesión. El tour no vuelve a aparecer en esa sesión.

---

## Estructura de archivos nuevos

```
app/
├── Domain/Company/Actions/
│   └── RegisterCompany.php
├── Http/
│   ├── Controllers/
│   │   ├── LandingController.php
│   │   ├── CompanyRegistrationController.php
│   │   ├── TourController.php
│   │   └── Onboarding/
│   │       ├── OnboardingCompanyController.php
│   │       ├── OnboardingScheduleController.php
│   │       └── OnboardingBreakTypesController.php
│   ├── Middleware/
│   │   └── EnsureOnboardingNotCompleted.php
│   └── Requests/
│       └── RegisterCompanyRequest.php

resources/js/
├── components/
│   ├── OnboardingProgress.vue
│   └── GuidedTour.vue
└── pages/
    ├── Landing/
    │   └── Index.vue
    ├── auth/
    │   └── RegisterCompany.vue
    └── Onboarding/
        ├── Company.vue
        ├── Schedule.vue
        └── BreakTypes.vue

database/migrations/
└── 2026_03_20_050702_add_onboarding_completed_to_companies.php

tests/Feature/
├── CompanyRegistrationTest.php   (7 tests)
├── OnboardingWizardTest.php      (9 tests)
└── GuidedTourTest.php            (5 tests)
```

---

## Tests

| Suite | Tests | Assertions |
|-------|-------|-----------|
| `CompanyRegistrationTest` | 7 | 21 |
| `OnboardingWizardTest` | 9 | 32 |
| `GuidedTourTest` | 5 | 41 |
| **Total fase 11** | **21** | **94** |

Suite completa al finalizar la fase: **255 tests, 958 assertions, todos pasando**.

---

## Decisiones técnicas clave

| Decisión | Alternativa considerada | Por qué se eligió |
|----------|------------------------|-------------------|
| Rutas públicas fuera del grupo `tenant` | Subdomain `www.` separado | Sin complejidad adicional en Bref/Lambda |
| Wizard con rutas Inertia separadas | Stepper client-side en Vue | El estado persiste en BD; se puede retomar si el usuario recarga |
| Tour en sesión PHP (`session`) | Columna en BD | Es preferencia de UI, no dato de negocio |
| `onboarding_completed` en `companies` | En `users` | El onboarding es de la empresa, no del usuario individual |
| Honeypot en registro | CAPTCHA | Sin fricción para el usuario real |

---

## Notas para futuras fases

- **Fase 9 (Stripe):** La pricing page ya está lista como landing informativa. Conectar los CTAs a Stripe Checkout cuando se implemente.
- **Verificación de email:** Omitida en MVP. Laravel Fortify ya tiene la infraestructura lista (`email_verified_at`).
- **Onboarding para empleados:** Fuera de scope. El wizard actual es exclusivo para el admin que registra la empresa.
