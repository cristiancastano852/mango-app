## Why

MangoApp no tiene punto de entrada público — un visitante no puede descubrir el producto, comparar planes ni registrar su empresa sin intervención manual. Esta fase cierra esa brecha para habilitar la adquisición de clientes de forma autónoma y completar el flujo SaaS end-to-end.

## What Changes

- Nueva landing page pública (sin auth) con hero, features, pricing y CTA
- Flujo de registro de empresa: crea `Company` (tenant) + `User` admin en una sola transacción
- `CompanyObserver` ya siembra `SurchargeRule` y holidays — el registro se beneficia automáticamente
- Wizard de configuración inicial post-registro (3 pasos: perfil empresa → horario por defecto → tipos de pausa activos)
- Tour guiado tipo tooltip para nuevos admins en el dashboard (primera sesión)
- Rutas públicas sin middleware `tenant` ni `auth`
- Rutas del wizard protegidas por `auth` + flag `onboarding_completed` en `companies`

## Capabilities

### New Capabilities

- `public-landing`: Landing page pública con hero, features, pricing (4 planes) y formulario de contacto/CTA
- `company-registration`: Formulario de registro de empresa — crea Company + User admin, inicia sesión y redirige al wizard
- `onboarding-wizard`: Wizard de 3 pasos post-registro (perfil empresa, horario por defecto, tipos de pausa); guarda `onboarding_completed = true` al finalizar
- `guided-tour`: Tour de tooltips secuenciales para nuevos admins en primera visita al dashboard

### Modified Capabilities

- `company-profile`: Añadir columna `onboarding_completed` (boolean, default false) al modelo Company y migración

## Impact

- **Nuevas rutas públicas**: `/`, `/pricing`, `/register/company` (sin middleware tenant/auth)
- **Nuevas rutas protegidas**: `/onboarding/*` (auth + `!onboarding_completed`)
- **Migración**: `companies` tabla → columna `onboarding_completed boolean default false`
- **Dominio afectado**: principalmente `Company`; `Organization` para schedule del wizard; `TimeTracking` para break types del wizard
- **Roles**: registro crea usuario con rol `admin`; wizard solo accesible para `admin` de la empresa recién creada
- **Multi-tenant**: el registro crea el tenant, por lo que corre FUERA del middleware `NeedsTenant` — la acción debe setear `company_id` explícitamente
- **No requiere Stripe** en esta fase — pricing page es informativa (CTAs vinculan a registro, no a checkout)

## Non-goals

- Integración real con Stripe Checkout (Fase 9)
- Tour interactivo avanzado con highlights de elementos DOM complejos
- Onboarding para rol `employee` (solo admin por ahora)
- Multi-idioma de la landing (solo español en MVP)
