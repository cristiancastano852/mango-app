# Novedades y Prorrateo por Ausencias (Fase 3 — pendiente)

> **Estado:** diseño documentado, **no implementado**.
> **Depende de:** el cambio "Salario base mensual + recargos correctos" (Fase 1+2) debe existir
> primero: columnas `employees.monthly_base_salary`, `employees.salary_type` (`hourly`|`monthly`),
> `hourly_rate` como valor hora editable, defaults por empresa en `surcharge_rules`
> (`default_monthly_salary`, `default_hourly_rate`) y el prorrateo del base **por fechas**
> (ingreso/retiro/rango parcial) contra denominador fijo de mes comercial de 30 días.

---

## Por qué existe esta fase

El rediseño de nómina colombiana parte de un hecho: en modo `monthly`, la hora ordinaria **ya está
incluida en el salario base** fijo de la quincena. El sistema paga:

```
total_periodo =
    base_periodo                              ← salario fijo, prorrateado
  + Σ horas_recargo × valor_hora × (%/100)    ← noct, dom, noct-dom: SOLO el %
  + Σ horas_extra  × valor_hora × (1 + %/100) ← 4 de overtime: valor COMPLETO
    regular → 0 (absorbido en el base)
```

En Fase 1+2 el `base_periodo` se prorratea **solo por fechas** (días dentro del rango / días_base),
asumiendo que todo día esperado del periodo es pagable. Esta fase agrega lo que falta: **descontar del
base los días de ausencia injustificada**, sin descontar las ausencias justificadas (vacaciones,
incapacidad, permiso remunerado), que se pagan.

El denominador sigue siendo el **mes comercial de 30 días** (15 por quincena). Las ausencias solo
restan del numerador; nunca se divide por días calendario reales. Esto preserva la regla central:
febrero y octubre pagan igual cuando no hay ausencias.

---

## Extensión de la fórmula

```
base_periodo = (monthly_base_salary / divisor) × (días_pagables / días_base)

  divisor    : 2 si quincena, 1 si mes
  días_base  : 15 (quincena) / 30 (mes)   ← mes comercial, NO calendario
  días_pagables = días_esperados_en_rango − días_ausencia_injustificada

  días_esperados_en_rango : días del rango cuyo weekday ∈ Schedule.days_of_week
                            (acotado por ingreso/retiro — esto ya viene de Fase 2)
```

Solo cambia el cálculo de `días_pagables`. Las ausencias **justificadas pagadas** NO se restan.
Recargos y horas extra no se ven afectados (se calculan sobre `TimeEntry` reales, que por definición
no existen en días de ausencia).

---

## Modelo de datos nuevo

Tabla `employee_absences` (o `novedades`), con `BelongsToCompany` + scope de tenant como el resto del
dominio.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | id | |
| `company_id` | FK | tenant scope |
| `employee_id` | FK | empleado |
| `start_date` | date | inicio de la novedad |
| `end_date` | date | fin (inclusive); permite rangos multi-día |
| `type` | string/enum | ver tabla de tipos |
| `is_paid` | boolean | derivado del tipo, pero persistido para auditoría/override |
| `notes` | text nullable | observaciones del admin |
| `created_by` | FK users nullable | quién la registró |
| timestamps | | |

Índices: `(company_id, employee_id, start_date)`.

### Tipos de novedad (enum, TitleCase por convención del proyecto)

| Tipo | `is_paid` | ¿Resta del base? |
|---|---|---|
| `Vacaciones` | sí | No |
| `IncapacidadComun` | sí (según EPS, ver nota) | No descuenta el base; el reporte la marca |
| `IncapacidadLaboral` | sí | No |
| `LicenciaRemunerada` | sí | No |
| `PermisoRemunerado` | sí | No |
| `LicenciaNoRemunerada` | no | Sí |
| `PermisoNoRemunerado` | no | Sí |
| `FaltaInjustificada` | no | Sí |

> **Nota incapacidades:** en la realidad colombiana las incapacidades las paga la EPS/ARL con
> porcentajes (66.67%, etc.) y reglas de días de carencia. Para esta fase basta con **no descontarlas
> del base** y marcarlas en el reporte; el cálculo exacto del subsidio de incapacidad queda fuera de
> alcance (posible fase futura).

---

## Detección de días esperados

`Schedule.days_of_week` (array JSON, ya existe) define qué días de la semana trabaja el empleado vía
`employees.schedule_id`. Un **día esperado** del rango es aquel cuyo weekday está en `days_of_week` y
no es festivo de la empresa (`Holiday`, recurrente o puntual — reutilizar `loadHolidayDates()` de
`CalculateWorkHours`).

```
días_ausencia_injustificada =
    count(días_esperados_en_rango
          que caen dentro de alguna employee_absences con is_paid = false)
```

Un día esperado **sin** `TimeEntry` y **sin** novedad registrada: decisión de diseño pendiente
(ver "Preguntas abiertas"). El modelo elegido fue **novedades explícitas**, así que por defecto un día
sin marca se considera pagable; el descuento ocurre solo cuando hay una novedad no remunerada.

---

## Punto de integración en el código

El cambio es localizado:

| Archivo | Cambio |
|---|---|
| `app/Domain/TimeTracking/Actions/CalculateReportCosts.php` | Recibir `días_pagables` ya calculado (no calcular ausencias aquí; mantenerlo puro) |
| `app/Domain/TimeTracking/Actions/GenerateEmployeeReport.php` | Calcular `días_esperados` y `días_ausencia_injustificada` para el rango, pasar `días_pagables` al cálculo del base; exponer desglose de novedades en el payload |
| `app/Domain/TimeTracking/Actions/GenerateCompanyReport.php` | Igual, agregado por empleado |
| Nuevo `app/Domain/.../Models/EmployeeAbsence.php` | Modelo |
| Nuevo CRUD (controller + request + Vue) | Registrar/editar novedades |

La aritmética del base prorrateado vive donde ya se arma `base_periodo` (introducido en Fase 2). Esta
fase solo cambia el numerador `días_pagables`.

---

## Superficie de frontend

- Nueva vista CRUD de novedades por empleado (registrar ausencia con tipo, rango, notas).
- En `Reports/Employee.vue` y `Reports/Company.vue`: mostrar las novedades del periodo y el efecto del
  descuento sobre el base como línea propia (ej. "Base: 1.000.000 − 2 días injustificados = 866.667").
- Posible indicador en el calendario / detalle diario de qué días tienen novedad.

---

## Casos de prueba (cuando se implemente)

- Empleado sin ausencias → base completo (idéntico a Fase 2).
- 2 faltas injustificadas en quincena de 15 → `base × 13/15`.
- Vacaciones toda la quincena → base completo (pagadas), 0 horas trabajadas.
- Incapacidad → no descuenta base, se marca en reporte.
- Ausencia que cae en día NO esperado (día de descanso) → no descuenta nada.
- Ausencia que cae en festivo → no descuenta (no era día esperado).
- Novedad que cruza el borde del rango/quincena → contar solo los días dentro del rango.
- Febrero vs octubre con 1 falta injustificada cada uno → mismo descuento proporcional (denominador 15
  en ambos), confirmando que el mes calendario no influye.
- Modo `hourly` → las novedades NO afectan el cálculo (no hay base que prorratear).

---

## Preguntas abiertas a resolver al implementar

1. **Día esperado sin TimeEntry y sin novedad:** ¿se asume trabajado/pagable (modelo actual elegido) o
   se alerta al admin para que clasifique la falta antes de liquidar? Recomendado: mostrar advertencia
   en el reporte ("N días esperados sin registro ni novedad") sin descontar automáticamente.
2. **Solapamiento** entre una novedad y un `TimeEntry` real el mismo día (trabajó parte del día):
   ¿cómo se concilia? Probablemente la novedad de día completo y el turno son excluyentes; definir
   regla.
3. **Días de descanso compensatorio** por trabajo dominical: ¿se modela como novedad o queda fuera?
4. **Incapacidades con subsidio EPS/ARL** (66.67%, días de carencia): fuera de alcance de esta fase;
   evaluar como fase futura aparte.
5. **Denominador en rango libre parcial:** confirmar que un rango que no calza con quincena/mes use
   como `días_base` los días comerciales equivalentes y no los calendario.
