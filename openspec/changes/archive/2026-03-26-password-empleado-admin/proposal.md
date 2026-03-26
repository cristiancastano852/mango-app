## Why

Al crear un empleado, el sistema genera una contraseña aleatoria que se hashea inmediatamente sin exponerla. El administrador no tiene manera de conocerla, creando fricción en el onboarding: el empleado no puede acceder sin pasar por un flujo de recuperación que podría no estar configurado.

## What Changes

- **Campo `password` opcional** en el formulario de creación de empleados (admin/super-admin). Si se deja vacío, el sistema genera una contraseña aleatoria de 16 caracteres.
- **Pantalla de confirmación post-creación**: tras crear el empleado, se redirige a `employees.show` con un banner que muestra la contraseña una única vez (enmascarada por defecto, con toggle de visibilidad y botón de copiar).
- **`CreateEmployee` action** retorna la contraseña en texto plano para que el controller la incluya en el flash de Inertia.
- **Sin cambios de schema de BD**: la contraseña no se almacena en texto plano ni cifrada.

## Capabilities

### New Capabilities
- `employee-password-setup`: Permite al administrador definir la contraseña de un empleado al crearlo, o recibir la contraseña generada automáticamente, mostrándola una única vez tras la creación.

### Modified Capabilities
_(ninguna — no hay cambios en requisitos de specs existentes)_

## Impact

- **Backend:** `app/Domain/Employee/Actions/CreateEmployee.php`, `app/Http/Controllers/EmployeeController.php`, `app/Http/Requests/Employee/StoreEmployeeRequest.php`
- **Frontend:** `resources/js/pages/Employees/Show.vue`, `resources/js/pages/Employees/partials/EmployeeForm.vue`
- **i18n:** claves nuevas en `lang/en/` y `lang/es/`
- **Sin migración de BD**
- **Roles:** solo `admin` y `super-admin` crean empleados (no cambia)
- **Multi-tenant:** el flujo existente ya aísla por `company_id` vía `BelongsToCompany`; no hay implicaciones adicionales
