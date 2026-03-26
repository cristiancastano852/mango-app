## ADDED Requirements

### Requirement: Admin puede definir contraseña al crear empleado
Al crear un empleado, el formulario SHALL incluir un campo `password` opcional. Si el admin lo completa, ese valor se usa como contraseña del usuario creado. Si se deja vacío, el sistema MUST generar automáticamente una contraseña aleatoria de 16 caracteres.

**Authorization:** Solo roles `admin` y `super-admin` pueden crear empleados (sin cambio respecto al comportamiento actual).

**Business Rules:**
- La contraseña ingresada MUST tener mínimo 8 caracteres y máximo 128.
- La contraseña MUST almacenarse hasheada (`Hash::make`) en `users.password`. No se almacena en texto plano ni cifrada en ninguna columna adicional.
- El campo `password` es nullable en el Form Request; su ausencia no es un error.

#### Scenario: Admin ingresa contraseña custom
- **WHEN** el admin completa el campo `password` con un valor válido (≥ 8 chars) y envía el formulario
- **THEN** el usuario creado tiene esa contraseña hasheada en `users.password`

#### Scenario: Admin deja el campo password vacío
- **WHEN** el admin no completa el campo `password` y envía el formulario
- **THEN** el sistema genera una contraseña aleatoria de 16 caracteres y la hashea en `users.password`

#### Scenario: Admin ingresa contraseña demasiado corta
- **WHEN** el admin ingresa una contraseña con menos de 8 caracteres
- **THEN** el formulario muestra un error de validación en el campo `password` y no crea el empleado

#### Scenario: Super-admin crea empleado con contraseña custom
- **WHEN** un super-admin completa el campo `password` y envía el formulario
- **THEN** el usuario creado tiene esa contraseña hasheada, con el mismo comportamiento que para admin

---

### Requirement: Contraseña mostrada una única vez post-creación
Tras crear exitosamente un empleado, el sistema SHALL mostrar la contraseña en texto plano una única vez en la pantalla de detalle del empleado (`employees.show`). Esta pantalla MUST incluir un banner de confirmación con la contraseña enmascarada por defecto, un toggle de visibilidad y un botón de copiar al portapapeles. El banner MUST incluir un aviso explícito de que la contraseña no volverá a mostrarse.

**Business Rules:**
- La contraseña se entrega al frontend mediante el mecanismo de flash de Inertia (`created_password`). No se persiste en BD.
- El banner solo aparece en la carga inmediatamente posterior a la creación (flash de un solo uso).
- La contraseña enmascarada SHALL mostrarse como `••••••••••••` por defecto.
- El botón de copiar SHALL usar `navigator.clipboard.writeText()` y mostrar feedback visual (icono `Check` por ~2 segundos).

#### Scenario: Admin ve la contraseña tras crear un empleado
- **WHEN** el admin crea exitosamente un empleado
- **THEN** es redirigido a `employees.show` y ve un banner con la contraseña enmascarada, el toggle de visibilidad y el botón de copiar

#### Scenario: Admin revela la contraseña con el toggle
- **WHEN** el admin hace clic en el botón toggle de visibilidad dentro del banner
- **THEN** la contraseña se muestra en texto plano en el banner

#### Scenario: Admin copia la contraseña al portapapeles
- **WHEN** el admin hace clic en el botón de copiar dentro del banner
- **THEN** la contraseña se copia al portapapeles y el icono cambia a `Check` por ~2 segundos como confirmación visual

#### Scenario: El banner desaparece al navegar
- **WHEN** el admin navega a otra página desde `employees.show`
- **THEN** el banner ya no aparece al regresar al detalle del empleado (el flash se consumió)

#### Scenario: Acceso al detalle sin creación reciente
- **WHEN** el admin accede a `employees.show` de un empleado existente sin haber creado uno en esa sesión
- **THEN** el banner de contraseña NO se muestra
