## Context

`CompanyObserver::created()` ya siembra `SurchargeRule` y `ColombianHolidays` al crear una empresa. El patrón está establecido y funciona. El `DemoSeeder` define manualmente las mismas 5 pausas, pero esa lógica no se aplica en producción al crear empresas reales.

## Goals / Non-Goals

**Goals:**
- Que toda empresa nueva nazca con 5 tipos de pausa listos para el onboarding
- Centralizar la definición de pausas por defecto en un lugar reutilizable
- Seguir el patrón establecido de `CompanyObserver`

**Non-Goals:**
- Migrar empresas existentes sin pausas
- Configurar pausas por industria/país
- Cambiar el CRUD de break types

## Decisions

### Acción `SeedDefaultBreakTypes` en dominio Company

**Decisión**: crear `app/Domain/Company/Actions/SeedDefaultBreakTypes.php` con un método `execute(Company $company): void` que inserta los 5 tipos de pausa con `BreakType::insert()`.

**Alternativa descartada**: poner la lógica directamente en `CompanyObserver` — descartada porque el observer ya está creciendo (SurchargeRule + Holidays) y tener la lista de pausas inline lo haría difícil de testear y mantener.

**Alternativa descartada**: usar `BreakTypeFactory` — las factories son para tests, no para lógica de producción.

### Punto de invocación: CompanyObserver

**Decisión**: invocar `SeedDefaultBreakTypes` desde `CompanyObserver::created()`, junto a las semillas ya existentes.

**Alternativa descartada**: `CreateCompanyWithAdmin` — ese action ya hace lo suficiente (empresa + usuario + rol). Además, el observer dispara para cualquier `Company::create()`, no solo para ese action, lo que garantiza consistencia.

### Pausas por defecto

Las 5 pausas extraídas del `DemoSeeder`, que ya probaron ser útiles:

| Nombre   | Pagada | Max min | Max/día | is_default |
|----------|--------|---------|---------|------------|
| Almuerzo | no     | 60      | 1       | true       |
| Descanso | sí     | 15      | 2       | false      |
| Baño     | sí     | null    | null    | false      |
| Personal | no     | 30      | 1       | false      |
| Médica   | sí     | null    | null    | false      |

Solo `Almuerzo` tiene `is_default: true` (es el tipo que define la duración de almuerzo de la empresa).

## Risks / Trade-offs

- **[Riesgo] DemoSeeder duplicado** → el `DemoSeeder` sigue teniendo su propia lista. No se elimina para no romper nada, pero queda duplicación. Mitigación: el `DemoSeeder` es solo para entorno de desarrollo; la acción es la fuente de verdad.
- **[Riesgo] Observer no dispara en tests que usan `Company::factory()->create()`** → el observer sí dispara porque usa el modelo Eloquent. Los tests que necesiten empresa sin pausas deben usar `withoutObservers()`.
