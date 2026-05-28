### Requirement: Empresa nueva recibe tipos de pausa por defecto
Al crear una empresa, el sistema SHALL sembrar automáticamente 5 tipos de pausa estándar via `CompanyObserver`. Estos tipos SHALL crearse con `company_id` de la empresa recién creada y `is_active: true`.

#### Scenario: Se crean los 5 tipos de pausa al crear empresa
- **WHEN** se crea una `Company` nueva (por cualquier vía: registro, super-admin, factory)
- **THEN** existen exactamente 5 `BreakType` con `company_id` de esa empresa
- **THEN** los nombres son: Almuerzo, Descanso, Baño, Personal, Médica

#### Scenario: Almuerzo es el único tipo default
- **WHEN** se crea una empresa nueva
- **THEN** exactamente un `BreakType` tiene `is_default: true` y su nombre es "Almuerzo"
- **THEN** los restantes 4 tipos tienen `is_default: false`

#### Scenario: Todos los tipos quedan activos por defecto
- **WHEN** se crea una empresa nueva
- **THEN** todos los `BreakType` sembrados tienen `is_active: true`

#### Scenario: Los tipos sembrados respetan el aislamiento multi-tenant
- **WHEN** se crean dos empresas distintas
- **THEN** cada empresa tiene sus propios 5 `BreakType` con su `company_id`
- **THEN** los break types de una empresa no son visibles para la otra

#### Scenario: El onboarding de break types muestra las pausas sembradas
- **WHEN** admin de una empresa nueva accede a `GET /onboarding/break-types`
- **THEN** la respuesta incluye los 5 tipos de pausa de su empresa
