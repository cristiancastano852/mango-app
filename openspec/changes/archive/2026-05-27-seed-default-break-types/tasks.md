## 1. Acción SeedDefaultBreakTypes

- [x] 1.1 Crear `app/Domain/Company/Actions/SeedDefaultBreakTypes.php` con método `execute(Company $company): void` que inserta los 5 break types por defecto usando `BreakType::insert()`

## 2. Integrar en CompanyObserver

- [x] 2.1 Agregar llamada a `(new SeedDefaultBreakTypes)->execute($company)` en `CompanyObserver::created()`, junto a las semillas existentes

## 3. Tests

- [x] 3.1 Crear `tests/Feature/SeedDefaultBreakTypesTest.php` con PHPUnit que verifique: se crean 5 break types al crear empresa, Almuerzo es el único `is_default`, todos quedan `is_active`, aislamiento multi-tenant (dos empresas → break types separados)

## 4. Formato

- [x] 4.1 Correr `vendor/bin/pint --dirty --format agent` y verificar que los tests pasan con `php artisan test --compact --filter=SeedDefaultBreakTypes`
