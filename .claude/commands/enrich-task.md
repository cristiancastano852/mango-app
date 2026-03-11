Actúa como product owner técnico de mango-app con conocimiento profundo del stack y del dominio de negocio.

## Paso 1 — Leer contexto del proyecto

Leer obligatoriamente estos archivos antes de continuar:
- ai-specs/specs/base-standards.mdc
- ai-specs/specs/data-model.md
- ai-specs/specs/domain-model.md
- ai-specs/specs/backend-standards.mdc
- ai-specs/specs/frontend-standards.mdc

## Paso 2 — Obtener la tarea a enriquecer

Determinar el modo según `$ARGUMENTS`:

**Modo A — nombre de archivo** (sin espacios, kebab-case): `$ARGUMENTS` es el nombre del archivo.
- Leer `tasks/$ARGUMENTS.md` como descripción inicial.
- El nombre del archivo de salida es `$ARGUMENTS`.

**Modo B — descripción libre** (contiene espacios o es una frase):
- Usar `$ARGUMENTS` directamente como descripción inicial (el texto [Original]).
- Derivar un nombre kebab-case en español que resuma la tarea en 3-5 palabras. Ejemplos:
  - "implementar reportes en excel para empleados" → `reportes-excel-empleados`
  - "gestión sobre a qué hora empieza el horario nocturno..." → `configuracion-horario-nocturno`
- Verificar que NO exista ya `tasks/{nombre-derivado}.md`. Si existe, leerlo y fusionar el contenido.
- El nombre del archivo de salida es el nombre kebab-case derivado.

## Paso 3 — Analizar la tarea

Antes de escribir, razonar internamente:
- ¿A qué dominio pertenece? (Company/Employee/Organization/TimeTracking/Shared)
- ¿Qué tablas del data-model están involucradas?
- ¿Qué roles tienen acceso? (super-admin/admin/employee)
- ¿Requiere migración de BD?
- ¿Tiene implicaciones multi-tenant (company_id)?
- ¿Es backend, frontend, o ambos?
- ¿Qué Actions del domain-model se crean o modifican?

## Paso 4 — Generar la tarea enriquecida

Crear o sobreescribir `tasks/{nombre-derivado}.md` (el nombre determinado en el Paso 2) con la siguiente estructura:

---

## [Original]

> _(descripción inicial tal como fue proporcionada)_

---

## [Enhanced]

### User Story
Como **[rol]**, quiero **[funcionalidad]** para **[valor de negocio]**.

### Descripción
_(2-4 párrafos explicando el contexto, el problema que resuelve y el comportamiento esperado)_

### Contexto técnico
- **Dominio:** `app/Domain/{dominio}/`
- **Tablas involucradas:** lista con columnas relevantes del data-model
- **Roles con acceso:** lista de roles y sus permisos
- **Multi-tenant:** ¿sí/no? y cómo aplica

### Criterios de aceptación
- [ ] _(criterio funcional concreto y verificable)_
- [ ] _(criterio funcional concreto y verificable)_
- [ ] _(comportamiento esperado ante errores/validaciones)_
- [ ] _(comportamiento esperado por rol si aplica)_

### Desglose técnico — Backend
- **Migración:** _(si aplica: tablas/columnas nuevas)_
- **Action(s):** _(nombre y responsabilidad de cada Action a crear)_
- **Form Request:** _(reglas de validación principales)_
- **Controller:** _(endpoint, método HTTP, middleware de roles)_
- **Ruta:** _(path y nombre, en web.php o settings.php)_
- **Tests requeridos:** _(casos por rol, cross-company, errores de validación)_

### Desglose técnico — Frontend
- **Páginas Vue:** _(paths en resources/js/pages/)_
- **Componentes UI a reutilizar:** _(de resources/js/components/ui/)_
- **Props de Inertia:** _(shape del objeto que llega del controller)_
- **Wayfinder imports:** _(acciones/rutas a importar)_
- **i18n:** _(claves nuevas en lang/en/ y lang/es/)_

### Requisitos no funcionales
- **Seguridad:** _(validaciones de autorización, cross-company isolation)_
- **Performance:** _(eager loading, paginación si aplica)_

### Definición de Done
- [ ] Tests pasando (`php artisan test --compact --filter=FeatureName`)
- [ ] `vendor/bin/pint --dirty` sin errores
- [ ] `npm run build` exitoso
- [ ] `php artisan wayfinder:generate` ejecutado si se agregaron rutas
- [ ] Specs actualizados si hubo cambio de schema (`ai-specs/specs/data-model.md`)

---

## Paso 5 — Confirmar al usuario

Mostrar un resumen de una línea por sección de lo que se generó y la ruta del archivo guardado.
