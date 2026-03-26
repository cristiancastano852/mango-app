## ADDED Requirements

### Requirement: Tour guiado se muestra al admin en su primera visita al dashboard tras el onboarding
El sistema SHALL mostrar un tour de tooltips secuenciales al admin cuando: (1) `onboarding_completed = true` Y (2) la sesión no tiene `tour_dismissed = true`. El tour SHALL mostrar 4-5 tooltips apuntando a elementos clave del dashboard.

#### Scenario: Admin ve el tour en primera visita post-onboarding
- **WHEN** admin completa el wizard y llega al dashboard por primera vez
- **THEN** el dashboard recibe prop `showTour: true` desde el backend
- **THEN** se muestra el primer tooltip del tour sobre el elemento correspondiente

#### Scenario: Admin que ya descartó el tour no lo vuelve a ver
- **WHEN** admin con `tour_dismissed` en sesión accede al dashboard
- **THEN** el dashboard recibe prop `showTour: false`
- **THEN** no se muestra ningún tooltip del tour

#### Scenario: Admin existente (antes de esta feature) no ve el tour
- **WHEN** admin con `onboarding_completed = false` (empresa pre-existente) accede al dashboard
- **THEN** el dashboard recibe prop `showTour: false`

---

### Requirement: Admin puede descartar el tour
El sistema SHALL proveer un botón "Saltar tour" o "Entendido" al final del tour. Al hacer clic, SHALL llamar a `POST /tour/dismiss` que guarda `tour_dismissed = true` en la sesión.

#### Scenario: Admin descarta el tour
- **WHEN** admin hace clic en "Saltar tour" o completa el último paso
- **THEN** se llama a `POST /tour/dismiss`
- **THEN** la sesión guarda `tour_dismissed = true`
- **THEN** al recargar el dashboard, `showTour` es `false`

#### Scenario: Ruta dismiss es idempotente
- **WHEN** se llama `POST /tour/dismiss` múltiples veces
- **THEN** siempre responde 200/redirect sin error
