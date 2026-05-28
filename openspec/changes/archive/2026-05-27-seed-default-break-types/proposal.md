## Why

Al crear una empresa nueva, el paso de onboarding de tipos de pausa (`/onboarding/break-types`) aparece vacío porque no se siembran pausas por defecto. El admin tiene que crearlas manualmente desde cero, lo que genera fricción innecesaria y deja el kiosko sin opciones de pausa hasta que lo haga.

## What Changes

- Nueva acción `SeedDefaultBreakTypes` en el dominio `Company` que crea 5 tipos de pausa estándar para una empresa.
- `CompanyObserver::created()` invoca la nueva acción junto a las semillas ya existentes (`SurchargeRule`, `ColombianHolidays`).
- La lógica de pausas por defecto se extrae del `DemoSeeder` a la acción reutilizable.

## Capabilities

### New Capabilities

- `default-break-types-seeding`: Al crear una empresa, se siembran automáticamente 5 tipos de pausa por defecto (Almuerzo, Descanso, Baño, Personal, Médica) mediante `CompanyObserver`.

### Modified Capabilities

- `break-type-management`: Se agrega el requisito de que toda empresa nueva nace con tipos de pausa pre-configurados. El comportamiento existente de gestión (CRUD, toggle, onboarding) no cambia.

## Impact

- **Archivos nuevos**: `app/Domain/Company/Actions/SeedDefaultBreakTypes.php`
- **Archivos modificados**: `app/Domain/Company/Observers/CompanyObserver.php`
- **Tests**: nuevo test en `tests/Feature/` que verifica que al crear una empresa se generan los 5 break types esperados
- **Multi-tenancy**: cada `BreakType` se crea con el `company_id` correcto; el `BelongsToCompany` trait los aísla automáticamente
- **Roles afectados**: ningún rol nuevo; el efecto es transparente (ocurre en el observer)
- **Migración de BD**: no requerida
- **Non-goals**: no modifica empresas ya existentes; no añade UI nueva; no cambia el CRUD de break types
