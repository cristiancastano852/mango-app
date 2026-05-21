## 1. Migración y modelo

- [x] 1.1 Crear migración para añadir `document_number` (string, nullable) a `employees` con unique index scoped `(document_number, company_id)`
- [x] 1.2 Actualizar `Employee` model: añadir `document_number` a `$fillable` y actualizar `ai-specs/specs/data-model.md`
- [x] 1.3 Actualizar `EmployeeFactory` para incluir `document_number` como nullable por defecto

## 2. Backend — employee-management

- [x] 2.1 Actualizar `StoreEmployeeRequest` y `UpdateEmployeeRequest`: añadir regla de validación para `document_number` (nullable, string, max:50, unique scoped por company)
- [x] 2.2 Actualizar `CreateEmployee` y `UpdateEmployee` actions: persistir `document_number`
- [x] 2.3 Correr `vendor/bin/pint --dirty --format agent` y tests de empleados existentes

## 3. Backend — KioskController y rutas

- [x] 3.1 Crear `KioskLookupRequest` con validación de `document_number` requerido
- [x] 3.2 Crear `KioskController` con métodos: `index`, `lookup`, `clockIn`, `clockOut`, `startBreak`, `endBreak`
- [x] 3.3 Registrar rutas del kiosco en `routes/web.php` fuera del middleware `auth`, con `throttle:10,1` en POST
- [x] 3.4 Correr `php artisan wayfinder:generate` y `npm run build`

## 4. i18n backend y frontend

- [x] 4.1 Añadir claves de traducción del kiosco en `lang/es/messages.php` y `lang/en/messages.php` (confirmaciones de acción)
- [x] 4.2 Añadir claves de traducción en `resources/js/locales/es.json` y `en.json` (UI del kiosco)

## 5. Frontend — Kiosk/Index.vue

- [x] 5.1 Crear `resources/js/layouts/KioskLayout.vue`: layout público con nombre de empresa, sin sidebar ni nav autenticado
- [x] 5.2 Crear `resources/js/pages/Kiosk/Index.vue` con tres estados internos: `document-input` → `action-select` → `confirmation`
- [x] 5.3 Implementar pantalla `document-input`: campo de texto para número de documento + botón continuar, con lookup via Inertia form
- [x] 5.4 Implementar pantalla `action-select`: saludo con nombre, estado del día (hora de entrada, pausa activa si existe), botones de acción según estado
- [x] 5.5 Implementar pantalla `confirmation`: mensaje de éxito con hora, barra de progreso countdown 5s, botón de reset manual; auto-navega a `document-input` al terminar

## 6. Frontend — formularios de empleado

- [x] 6.1 Añadir campo `document_number` en `resources/js/pages/Employees/Create.vue` (o el componente de formulario compartido)
- [x] 6.2 Añadir campo `document_number` en `resources/js/pages/Employees/Edit.vue`
- [x] 6.3 Correr `npm run build` y verificar que no hay errores

## 7. Tests PHPUnit

- [x] 7.1 Test `KioskLookupTest`: lookup exitoso, documento no encontrado, empresa con slug inválido (404)
- [x] 7.2 Test `KioskActionsTest`: clock-in, clock-out, start-break, end-break exitosos; acción con sesión inválida (403); acción de empresa cruzada (403)
- [x] 7.3 Test `EmployeeDocumentNumberTest`: creación con document_number, creación sin document_number, duplicado en misma empresa (error), duplicado en empresa diferente (ok), edición
- [x] 7.4 Correr `php artisan test --compact --filter=Kiosk` y `--filter=EmployeeDocumentNumber`

## 8. Limpieza final

- [x] 8.1 Correr `vendor/bin/pint --dirty --format agent` en todos los archivos PHP modificados
- [x] 8.2 Correr `php artisan test --compact` para verificar suite completa sin regresiones
