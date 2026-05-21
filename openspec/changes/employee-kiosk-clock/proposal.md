## Why

Los empleados comparten un mismo computador físico para registrar su tiempo, lo que los obliga a iniciar y cerrar sesión repetidamente durante el día. Se necesita una vista de kiosco pública por empresa donde los empleados se identifiquen solo con su número de documento para fichar entrada, pausas y salida, sin necesidad de autenticación.

## What Changes

- **Nuevo campo `document_number`** en la tabla `employees` (string, nullable, unique por empresa), que representa la cédula o número de identificación del trabajador.
- **Nueva vista pública de kiosco** en `/kiosk/{company:slug}` (sin autenticación requerida): el empleado ingresa su número de documento, el sistema lo saluda por nombre y le muestra las acciones disponibles según su estado actual del día.
- **Acciones disponibles en el kiosco**: iniciar jornada, iniciar pausa (con selección de tipo), finalizar pausa, finalizar jornada.
- **Confirmación post-acción** con countdown de 5 segundos y auto-reset a la pantalla de ingreso de documento.
- **Vista del día actual solamente**: muestra estado actual (entrada, pausa en curso, jornada finalizada), sin históricos de días anteriores.
- **Campo `document_number`** disponible al crear y editar empleados en el panel de administración.

## Capabilities

### New Capabilities

- `employee-kiosk`: Vista pública de kiosco por empresa que permite a los empleados registrar tiempo usando su número de documento sin autenticación.

### Modified Capabilities

- `employee-management`: Se añade el campo `document_number` al flujo de creación y edición de empleados.

## Impact

- **Base de datos**: Migración para añadir `document_number` a `employees` (string, nullable, unique scoped por `company_id`).
- **Backend**: Nuevo `KioskController` con rutas públicas (excluidas de auth y CSRF para las acciones POST del kiosco). Reutiliza actions existentes: `ClockIn`, `ClockOut`, `StartBreak`, `EndBreak`.
- **Frontend**: Nueva página Vue `Kiosk/Index.vue` sin layout autenticado. Actualización de formularios de creación/edición de empleados.
- **Multi-tenancy**: El kiosco opera dentro del scope de la empresa dada por el slug en la URL. El `document_number` es único solo dentro de la misma empresa.
- **Roles**: El kiosco es accesible sin rol (`guest`). No expone datos sensibles: solo nombre del empleado y estado del día actual.
- **Rutas**: Grupo de rutas nuevas prefijadas con `/kiosk/{company:slug}`, fuera del middleware `auth`.

## Non-goals

- No incluye historial de días anteriores en el kiosco.
- No incluye cronómetro en tiempo real en el kiosco.
- No incluye soporte de subdominios por empresa (futuro).
- No incluye PIN o contraseña adicional en el kiosco (el número de documento es suficiente).
- No modifica el flujo de autenticación existente para usuarios admin/employee.
