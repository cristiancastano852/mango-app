---
name: check-tests
description: "Revisión sistemática de cobertura de tests. Úsalo después de implementar un feature o fix para verificar que todos los roles, casos de error y edge cases están cubiertos."
license: MIT
metadata:
  author: mango-app
---

# Check Tests — Revisión de Cobertura

Eres un revisor experto de tests para esta aplicación Laravel multi-tenant con roles `super-admin`, `admin` y `employee`.

## Tu tarea

Revisa los cambios recientes (archivos modificados en el branch actual vs main) y para cada controlador o acción modificada:

### 1. Identifica las rutas afectadas
- ¿Qué middleware de roles protege cada ruta? (`role:admin|super-admin`, etc.)
- ¿Qué acciones tiene el controlador? (index, store, update, destroy...)

### 2. Verifica cobertura por rol
Para CADA rol que puede acceder a la ruta, debe existir un test de happy path:
- `super-admin` (company_id = null, acceso global)
- `admin` (company_id definido, acceso limitado a su empresa)
- `employee` (solo sus propios recursos)

Para CADA rol que NO debe acceder, debe existir un test que verifique `assertForbidden()`.

### 3. Verifica edge cases críticos de esta app
- **Cross-company**: ¿puede un admin acceder a recursos de otra empresa? Debe fallar con error de validación (no 404).
- **Super-admin sin company_id**: ¿las validaciones con `exists:table,id` tienen en cuenta que `company_id` puede ser null?
- **Global scopes**: ¿los queries usan `withoutGlobalScopes()` donde corresponde?
- **Estado inválido**: doble clock-in, entry sin clock_out, etc.

### 4. Verifica aserciones completas
Los tests deben verificar no solo el status HTTP sino también:
- El estado final en base de datos (`assertDatabaseHas` con TODOS los campos relevantes)
- Que campos NO cambiaron cuando no debían
- El mensaje de error correcto en `assertSessionHasErrors`

### 5. Output esperado

Lista en formato:

```
✅ CUBIERTO   test_admin_can_...
✅ CUBIERTO   test_super_admin_can_...
✅ CUBIERTO   test_employee_cannot_...
❌ FALTANTE   test_super_admin_can_... [descripción del caso]
❌ FALTANTE   test_cross_company_...  [descripción del caso]
⚠️  INCOMPLETO test_admin_can_update — falta assertDatabaseHas para campo 'status'
```

Luego escribe los tests faltantes y ejecútalos con `php artisan test --compact`.

## Contexto de la app

- Multi-tenant: todos los modelos tienen `company_id` y un global scope
- Roles: `super-admin` (sin company_id), `admin`, `employee`
- Las rutas admin usan `middleware('role:admin|super-admin')`
- Tests deben crear `Role::create` en setUp para cada rol usado
- Usar `Employee::withoutGlobalScopes()->create()` para crear empleados de otras empresas en tests de cross-company
