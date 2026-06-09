# Incidente: Festivos por defecto con fechas incorrectas (recargos no aplicados)

**Fecha de detección:** 2026-06-08
**Componente afectado:** Company — festivos por defecto (`ColombianHolidaysSeeder`) y clasificación de horas (`CalculateWorkHours`)
**Severidad:** Alta (los turnos trabajados en festivos mal calculados quedan sin recargo dominical/festivo en nómina)
**Estado:** Seeder corregido + tests ✅ · Reparación de datos en producción pendiente ⏳

---

## Resumen

El seeder de festivos por defecto (`database/seeders/ColombianHolidaysSeeder.php`), que se ejecuta automáticamente al crear cada empresa (`CompanyObserver@created`), tenía **4 fechas móviles mal calculadas** y le **faltaba 1 festivo nuevo**. Las fechas de Semana Santa estaban bien (Pascua = 5 abril 2026), pero los festivos **posteriores a Pascua** estaban corridos una semana de más.

Como `CalculateWorkHours` consulta los festivos **en el momento del cierre del turno** y **persiste** la clasificación de horas en el `TimeEntry`, los turnos trabajados en una fecha que *debía* ser festiva pero no estaba registrada se clasificaron como **día ordinario**, sin el recargo dominical/festivo (~35%) ni el nocturno-festivo.

### Caso real que lo destapó

El **8 de junio de 2026** (Corpus Christi) varios empleados de una empresa en producción finalizaron su turno y el día **no figuraba como festivo**. El seeder tenía Corpus Christi el **15 de junio** en vez del **8 de junio**. Sus horas quedaron en `regular_hours` / `night_hours` en lugar de `sunday_holiday_hours` / `night_sunday_hours`.

---

## Causa raíz

Las fechas móviles colombianas se rigen por la **Ley Emiliani** (festivos que se trasladan al lunes siguiente) y por la fecha de **Pascua**. El seeder usó la Pascua correcta (5 abril 2026) para Jueves y Viernes Santo, pero calculó los festivos posteriores como si la Pascua fuera una semana después. Además, San Pedro y San Pablo se trasladó al lunes siguiente sin necesidad (el 29 de junio de 2026 ya cae lunes).

### Fechas corregidas

| Festivo | Antes (incorrecto) | Ahora (correcto 2026) | Motivo |
|---|---|---|---|
| Ascensión del Señor | 2026-05-25 | **2026-05-18** | Pascua + Emiliani |
| Corpus Christi | 2026-06-15 | **2026-06-08** | Pascua + Emiliani |
| Sagrado Corazón | 2026-06-22 | **2026-06-15** | Pascua + Emiliani |
| San Pedro y San Pablo | 2026-07-06 | **2026-06-29** | El 29/06/2026 ya es lunes; no se traslada |

### Festivo nuevo agregado

| Festivo | Fecha 2026 | Fundamento |
|---|---|---|
| **Virgen de Chiquinquirá** | **2026-07-13** | **Ley 2578 de 2026** (sancionada 2026-06-04): declara el 9 de julio como Fiesta Nacional de Nuestra Señora del Rosario de Chiquinquirá, patrona de Colombia. Por Ley Emiliani, como el 9/07/2026 cae jueves, el descanso se traslada al lunes 13/07/2026. |

Con esto el seeder genera los **19 festivos nacionales** de 2026.

---

## Por qué los datos ya calculados quedaron mal

El recargo **no se calcula al vuelo** en el reporte. El flujo es:

1. Al cerrar el turno (`ClockOut` → `CalculateWorkHours`), se consultan los festivos de la empresa en ese instante (`loadHolidayDates`).
2. Se clasifican las horas en 8 buckets mutuamente excluyentes y se **guardan en columnas del `TimeEntry`** (`regular_hours`, `sunday_holiday_hours`, `night_sunday_hours`, etc.), con `status = 'calculated'`.
3. Los reportes y la nómina **suman esas columnas ya guardadas** (`GenerateEmployeeReport` hace `SUM(sunday_holiday_hours)`, etc.). No recalculan festivos.

**Implicación:** corregir el seeder o agregar el festivo en la configuración **no toca** los registros ya calculados. La búsqueda de festivos solo corre durante el cálculo, no retroactivamente. Los `TimeEntry` afectados conservan la clasificación vieja (sin recargo) hasta que se **recalculen** explícitamente.

```
TimeEntry del 8/06 cerrado ANTES de agregar el festivo:
  regular_hours / night_hours  ← clasificación incorrecta (día ordinario)

Lo correcto tras recálculo:
  sunday_holiday_hours / night_sunday_hours  ← recargo festivo aplicado
```

---

## Cambio realizado (2026-06-08)

- Corregidas las 4 fechas móviles en `ColombianHolidaysSeeder.php` y agregada la Virgen de Chiquinquirá (2026-07-13).
- Nuevo test `tests/Feature/ColombianHolidaysSeederTest.php`:
  - `test_it_seeds_the_nineteen_colombian_national_holidays_for_2026` — verifica los 19 festivos y sus fechas exactas.
  - `test_it_seeds_the_new_virgen_de_chiquinquira_holiday` — verifica el festivo nuevo.

**Verificación:** seeder test (2) + `HolidayControllerTest` (10) en verde. Pint limpio.

> **Nota:** el seeder tiene las fechas **hardcodeadas para 2026**. El propio archivo advierte que para 2027+ hay que recalcular manualmente las móviles. Pendiente evaluar calcularlas automáticamente con Pascua + Ley Emiliani para no tocarlo cada año.

---

## Alcance del cambio en el seeder vs. datos existentes

| Qué | Estado |
|---|---|
| **Empresas nuevas** (creadas a partir de ahora) | ✅ Reciben los 19 festivos correctos automáticamente |
| **Empresas existentes en producción** | ❌ Mantienen en su tabla `holidays` las fechas viejas/incorrectas y/o sin el festivo nuevo |
| **`TimeEntry` ya calculados** en una fecha que debía ser festiva | ❌ Conservan la clasificación sin recargo hasta recalcularlos |

El seeder **no** actualiza empresas ya creadas. La corrección de la configuración de festivos de cada empresa existente y el recálculo de sus turnos son pasos manuales pendientes (ver siguiente sección).

---

## Reparación de datos en producción (pendiente)

El código ya está corregido, pero **los datos históricos siguen mal** hasta ejecutar una corrección. Dos frentes:

### 1. Corregir la configuración de festivos de cada empresa existente

Las empresas creadas antes de este fix tienen en su tabla `holidays`:
- Las 4 fechas móviles con valores incorrectos.
- Sin la Virgen de Chiquinquirá.

Hay que actualizar/insertar esas filas por empresa. (En el caso que destapó el incidente, el festivo del 8 de junio ya se agregó manualmente desde el panel.)

### 2. Recalcular los `TimeEntry` afectados

Por cada turno cerrado que **toca** una fecha que pasó a ser festiva, re-correr el cálculo para que reclasifique las horas. Existe `RecalculateTimeEntry`, que recomputa gross/break/net y vuelve a correr `CalculateWorkHours`.

> **Importante — turnos nocturnos:** la clasificación es **por segmento** según la fecha real de cada tramo (`$segStart->toDateString()`). Un turno que inició el **día anterior** y cruzó la medianoche hacia el festivo también tiene tramos festivos. Para el 8 de junio hay que recalcular los turnos con `date` en **2026-06-07 y 2026-06-08**, no solo los del 8.

> **Importante — `status`:** `RecalculateTimeEntry` marca el registro como `'edited'` y puede setear `edited_by` / `edit_reason`. Por ser una **corrección de sistema** (no una edición humana), conviene preservar el `status` y `edited_by` originales — igual que se acordó para el [incidente de duración de pausas](incidente-duracion-pausas-negativa-2026-06-05.md). Evaluar un recálculo que solo re-corra `CalculateWorkHours` (deja `status = 'calculated'`) en vez de pasar por `RecalculateTimeEntry`.

### Opciones evaluadas

| Opción | Pros | Contras |
|---|---|---|
| **Comando Artisan con `--dry-run`** (recomendada) | Idempotente; reporta antes de aplicar; reutilizable para futuros festivos agregados tarde | Requiere ejecución manual en prod |
| **Script tinker puntual** | Rápido para una empresa/fecha concreta | No reutilizable; no auditable |
| **Migración one-off** | Se ejecuta sola en el deploy | Recálculo pesado en una migración; difícil de re-ejecutar/auditar |

### Esquema sugerido del comando

```
php artisan time-entries:recalculate-holidays --date=2026-06-08 [--company=ID] [--dry-run]
```

- **Dry-run:** lista los `TimeEntry` (date en `--date` y el día anterior) que cambiarían de bucket, con delta de horas festivas, sin escribir.
- **Aplicar:** por cada turno cerrado afectado, re-correr `CalculateWorkHours->execute(...)` preservando `status` / `edited_by`. Envolver en transacción y registrar resumen (turnos recalculados, delta total de horas festivas).

---

## Prevención

- Mantener el seeder de festivos verificado contra el calendario oficial **cada año** (las móviles cambian con Pascua y pueden aparecer festivos nuevos por ley, como la Virgen de Chiquinquirá en 2026).
- Considerar calcular las fechas móviles automáticamente (Pascua + Ley Emiliani) para eliminar el hardcode anual.
- Recordar que la clasificación de festivos se **persiste** al cerrar el turno: cualquier cambio en festivos (corrección de fecha, festivo nuevo, festivo agregado tarde) **exige recalcular** los turnos ya cerrados de las fechas afectadas; no basta con corregir la configuración.
