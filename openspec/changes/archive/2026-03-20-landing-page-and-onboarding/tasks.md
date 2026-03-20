## 1. Migración y Modelo — onboarding_completed

- [x] 1.1 Crear migración `add_onboarding_completed_to_companies` con columna `onboarding_completed boolean default false`; en el `up()` actualizar empresas pre-existentes a `true` con `DB::statement('UPDATE companies SET onboarding_completed = true')`
- [x] 1.2 Añadir `onboarding_completed` al modelo `Company`: cast `bool`, fillable, actualizar `ai-specs/specs/data-model.md`
- [x] 1.3 Correr `php artisan migrate` y verificar columna en BD

## 2. Backend — Registro de Empresa

- [x] 2.1 Crear `RegisterCompanyRequest` en `app/Http/Requests/` con reglas: `company_name` (required, max:255), `name` (required, max:255), `email` (required, email, unique:users), `password` (required, min:8, confirmed); incluir mensajes de error en español
- [x] 2.2 Crear `app/Domain/Company/Actions/RegisterCompany.php`: recibe datos validados, ejecuta `DB::transaction` → crea `Company` → crea `User` con `company_id` y rol `admin` → autentica al usuario → retorna el usuario; el `CompanyObserver` correrá automáticamente
- [x] 2.3 Crear `CompanyRegistrationController` (GET retorna Inertia page, POST delega a `RegisterCompany` y redirige a `/onboarding/company`); incluir guard `guest` para redirigir usuarios autenticados a `/dashboard`
- [x] 2.4 Añadir rutas públicas en `routes/web.php`: `GET /` → `LandingController@index`, `GET /pricing` → redirect a `/#pricing`, `GET /register/company` y `POST /register/company` → `CompanyRegistrationController`; aplicar rate limiting 60/min a las rutas de registro
- [x] 2.5 Crear `LandingController` con método `index` que retorna Inertia page `Landing/Index`
- [x] 2.6 Correr `php artisan wayfinder:generate && npm run build`
- [x] 2.7 Escribir tests `CompanyRegistrationTest`: happy path (empresa creada, usuario admin autenticado, redirige a onboarding), email duplicado, contraseñas no coinciden, campos requeridos vacíos, honeypot lleno (no crea registros)
- [x] 2.8 Correr `php artisan test --compact --filter=CompanyRegistrationTest` y verificar que pasan; correr `vendor/bin/pint --dirty --format agent`

## 3. Backend — Wizard de Onboarding

- [x] 3.1 Crear middleware `EnsureOnboardingNotCompleted`: si `onboarding_completed = true`, redirige a `/dashboard`; registrar en `bootstrap/app.php` como alias `onboarding`
- [x] 3.2 Crear `OnboardingCompanyController` (GET retorna Inertia `Onboarding/Company` con datos de la empresa, POST → valida y llama `UpdateCompanyProfile` existente → redirige a `/onboarding/schedule`)
- [x] 3.3 Crear `OnboardingScheduleController` (GET retorna Inertia `Onboarding/Schedule`, POST con `skip` o datos de schedule → crea Schedule si datos presentes, no si skip → redirige a `/onboarding/break-types`)
- [x] 3.4 Crear `OnboardingBreakTypesController` (GET retorna Inertia `Onboarding/BreakTypes` con break types de la empresa, POST → actualiza `is_active` de cada tipo → setea `onboarding_completed = true` → redirige a `/dashboard` con flash de éxito)
- [x] 3.5 Añadir rutas wizard en `routes/web.php` dentro de grupo `auth` + middleware `onboarding`: `GET/POST /onboarding/company`, `GET/POST /onboarding/schedule`, `GET/POST /onboarding/break-types`; middleware `role:admin` para los 3
- [x] 3.6 Correr `php artisan wayfinder:generate && npm run build`
- [x] 3.7 Escribir tests `OnboardingWizardTest`: paso 1 (actualiza empresa, redirige), paso 2 happy path y skip, paso 3 (actualiza break types, `onboarding_completed = true`, redirige), employee recibe 403, admin con `onboarding_completed = true` es redirigido al dashboard
- [x] 3.8 Correr `php artisan test --compact --filter=OnboardingWizardTest`; correr `vendor/bin/pint --dirty --format agent`

## 4. Backend — Tour Guiado

- [x] 4.1 Modificar `DashboardController`: añadir prop `showTour` = (`onboarding_completed && !session('tour_dismissed')`)
- [x] 4.2 Crear `TourController` con método `dismiss`: setea `session(['tour_dismissed' => true])`, retorna redirect back; proteger con `auth`
- [x] 4.3 Añadir ruta `POST /tour/dismiss` → `TourController@dismiss` en grupo `auth`
- [x] 4.4 Correr `php artisan wayfinder:generate && npm run build`
- [x] 4.5 Escribir tests `GuidedTourTest`: dashboard incluye `showTour = true` para admin post-onboarding, `showTour = false` después de dismiss, `showTour = false` para admin existente con `onboarding_completed = false`
- [x] 4.6 Correr `php artisan test --compact --filter=GuidedTourTest`; correr `vendor/bin/pint --dirty --format agent`

## 5. Frontend — Landing Page

- [x] 5.1 Crear `resources/js/pages/Landing/Index.vue` con layout sin sidebar (layout propio o sin layout): sección hero (tagline + CTA button → `/register/company`), sección features (3-4 features de MangoApp), sección pricing (4 cards), footer básico
- [x] 5.2 Styling con Tailwind v4: diseño mobile-first, hero con gradient de fondo, pricing cards con highlight en plan Pro, botones usando componentes `ui/button`
- [x] 5.3 Añadir claves i18n en `en.json` y `es.json` para textos de landing: `landing.hero.title`, `landing.hero.subtitle`, `landing.cta`, `landing.pricing.*`
- [x] 5.4 Correr `npm run build` y verificar en browser que la landing carga en `/`

## 6. Frontend — Registro de Empresa

- [x] 6.1 Crear `resources/js/pages/Auth/RegisterCompany.vue`: formulario con campos `company_name`, `name`, `email`, `password`, `password_confirmation`, campo honeypot oculto; usar `useForm` de Inertia con Wayfinder para el POST
- [x] 6.2 Añadir claves i18n en `en.json` y `es.json`: `auth.register_company.*`
- [x] 6.3 Correr `npm run build` y verificar flujo completo en browser

## 7. Frontend — Wizard de Onboarding

- [x] 7.1 Crear componente `resources/js/components/OnboardingProgress.vue`: recibe prop `currentStep` (1|2|3), muestra 3 pasos con estado (completado/activo/pendiente)
- [x] 7.2 Crear `resources/js/pages/Onboarding/Company.vue`: formulario nombre + timezone (selector) + país, incluye `<OnboardingProgress :currentStep="1" />`
- [x] 7.3 Crear `resources/js/pages/Onboarding/Schedule.vue`: formulario nombre + start_time + end_time + days_of_week (checkboxes) + botón skip, incluye `<OnboardingProgress :currentStep="2" />`
- [x] 7.4 Crear `resources/js/pages/Onboarding/BreakTypes.vue`: lista de break types con toggles `is_active`, botón finalizar, incluye `<OnboardingProgress :currentStep="3" />`
- [x] 7.5 Añadir claves i18n en `en.json` y `es.json`: `onboarding.*`
- [x] 7.6 Correr `npm run build` y verificar flujo completo paso a paso en browser

## 8. Frontend — Tour Guiado

- [x] 8.1 Crear componente `resources/js/components/GuidedTour.vue`: recibe prop `show` (boolean), muestra tooltips secuenciales sobre 4 elementos del dashboard (KPI cards, FAB, sidebar nav, time entries); botones "Siguiente" y "Saltar tour"
- [x] 8.2 Modificar `Dashboard.vue`: recibir prop `showTour`, renderizar `<GuidedTour :show="showTour" />`, al descartar llamar `POST /tour/dismiss` via Wayfinder
- [x] 8.3 Correr `npm run build` y verificar que el tour aparece tras completar el wizard y no vuelve a aparecer tras descartarlo

## 9. Verificación Final

- [x] 9.1 Correr `php artisan test --compact` (suite completa) y verificar que todos pasan
- [x] 9.2 Actualizar `ai-specs/specs/domain-model.md` con `RegisterCompany` action, `OnboardingCompanyController`, `OnboardingScheduleController`, `OnboardingBreakTypesController`, `TourController`, `LandingController`, `EnsureOnboardingNotCompleted` middleware
- [x] 9.3 Correr `vendor/bin/pint --dirty --format agent` para formateo final
