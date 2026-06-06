# Incidente: Duración de pausas negativa (`duration_minutes < 0`)

**Fecha de detección:** 2026-06-05
**Componente afectado:** Time Tracking — cálculo de duración de pausas y horas netas
**Severidad:** Alta (afecta horas pagadas en todos los turnos con pausas no pagadas registradas por el reloj en vivo)
**Estado:** Código corregido + tests ✅ · Reparación de datos en producción pendiente ⏳

---

## Resumen

Todas las pausas cerradas a través del **reloj en vivo** (empleado finalizando su pausa, o clock-out con una pausa activa) quedaron guardadas con `duration_minutes` **negativo**. Como las pausas **no pagadas** se restan de las horas trabajadas, un valor negativo terminaba **sumando** tiempo en lugar de restarlo, inflando `net_hours` (las horas pagadas).

### Caso real que lo destapó

```json
{
  "id": 15,
  "clock_in": "2026-06-05 14:10:39",
  "clock_out": "2026-06-05 21:35:03",
  "gross_hours": "7.41",
  "break_hours": "-0.25",
  "net_hours": "7.66",
  "regular_hours": "4.99",
  "night_hours": "2.67"
}
```

- `gross_hours` = 7.41 (7h 24m reales entre check-in y check-out).
- `break_hours` = **−0.25** (−15 min) → imposible: una pausa no puede durar tiempo negativo.
- `net_hours` = `gross − break` = `7.41 − (−0.25)` = **7.66** (7h 40m).

En el frontend se mostraba **"7h 40m"** (que es `net_hours`) bajo un rango `14:10 → 21:35` de solo 7h 24m. El neto salía **mayor** que el tiempo transcurrido — la señal visible del bug.

---

## Causa raíz

En **Carbon 3** (incluido en Laravel 12) los métodos `diffIn*` devuelven un valor **con signo**. `$a->diffInMinutes($b)` mide desde `$a` hacia `$b`: si `$b` es anterior a `$a`, el resultado es **negativo**.

El código calculaba la duración con el orden invertido:

```php
// ❌ ANTES — now() está después de started_at → resultado negativo
$duration = (int) now()->diffInMinutes($breakEntry->started_at);
```

Como toda pausa **siempre** se inicia antes de cerrarse, `started_at` siempre está en el pasado respecto a `now()`, por lo que el resultado era **siempre negativo**.

Comprobación en el entorno:

```
now()->diffInMinutes(started_at)  = -15   ← lo que se guardaba
started_at->diffInMinutes(now())  = +15   ← lo correcto
```

### Por qué no lo detectaron los tests existentes

Los tests del reloj hacían clock-in, start-break y end-break **en el mismo instante** (`now()`), por lo que la duración daba `0`. Un cero no revela el signo, así que el bug pasó desapercibido.

---

## Relación con pausas pagadas / no pagadas

El signo negativo **no depende** del tipo de pausa: le ocurría a **todas** las pausas del reloj en vivo. Lo que el tipo decide es **si ese error corrompe el cálculo de horas**.

`break_hours` solo suma pausas **no pagadas** (`RecalculateTimeEntry.php` / `ClockOut.php`):

```php
$breakHours = round(
    $timeEntry->breaks()
        ->whereNotNull('ended_at')
        ->whereHas('breakType', fn ($q) => $q->where('is_paid', false)) // solo NO pagadas
        ->sum('duration_minutes') / 60,
    2
);
$netHours = round(max(0, $grossHours - $breakHours), 2);
```

| Tipo de pausa | ¿Guarda valor negativo? | ¿Entra a `break_hours`? | Efecto en `net_hours` |
|---|---|---|---|
| **No pagada** (`is_paid=false`) | Sí | Sí | `net = gross − (negativo)` → **infla las horas pagadas** |
| **Pagada** (`is_paid=true`) | Sí | No (se filtra) | Ninguno (el dato está corrupto en `breaks` pero no afecta el cálculo) |

---

## Alcance

Solo afectaba a los **dos** puntos del reloj en vivo. Los demás usos de `diffInMinutes` tenían el orden correcto:

| Ubicación | Código | Estado original |
|---|---|---|
| `app/Domain/TimeTracking/Actions/EndBreak.php` | `now()->diffInMinutes($started_at)` | ❌ negativo |
| `app/Domain/TimeTracking/Actions/ClockOut.php` (cierre de pausa activa) | `now()->diffInMinutes($activeBreak->started_at)` | ❌ negativo |
| `ClockOut.php` (gross) | `$clockIn->diffInMinutes($clockOut)` | ✅ correcto |
| `RecalculateTimeEntry.php` (gross) | `$clock_in->diffInMinutes($clock_out)` | ✅ correcto |
| `TimeEntryBreakController.php` (admin) | `$started_at->diffInMinutes($ended_at)` | ✅ correcto |
| `CalculateWorkHours.php` | `$segStart->diffInSeconds($segEnd)` | ✅ correcto |

**Implicación:** las pausas creadas o editadas desde el **panel admin** (`TimeEntryBreakController`) ya tenían el orden correcto → están **positivas**. Las negativas son exclusivamente las del **reloj en vivo**.

---

## Cambio realizado (2026-06-05)

Se invirtió el orden del `diffInMinutes`, alineándolo con la convención que ya usaba `TimeEntryBreakController`:

```php
// EndBreak.php
$duration = (int) $breakEntry->started_at->diffInMinutes(now());

// ClockOut.php
'duration_minutes' => (int) $activeBreak->started_at->diffInMinutes(now()),
```

### Tests añadidos (`tests/Feature/TimeClockTest.php`)

- `test_ending_break_records_positive_duration` — cerrar una pausa de 30 min → `duration_minutes = 30` (positivo).
- `test_clock_out_with_active_break_records_positive_duration_and_deducts_unpaid_break` — clock-out con pausa activa no pagada → duración positiva, `break_hours = 0.5`, `net = gross − break`.
- `test_paid_break_is_not_deducted_from_net_hours` — pausa pagada no afecta `net_hours`.

Los tres fijan la hora con `travelTo(...09:00)` para ser deterministas (los `travel()` sin base fija podían cruzar la medianoche y romper las búsquedas por `date`).

**Verificación:** `TimeClockTest` (13 tests) + suite relacionada (WorkHourCalculation, CalculateWorkHours, Kiosk, TimeEntryBreak, TimeEntry: 72 tests) en verde. Pint limpio.

---

## Reparación de datos en producción (pendiente)

El código ya está corregido, pero **los datos históricos siguen mal** hasta ejecutar una corrección. Dos pasos:

1. **Corregir las magnitudes:** pasar a positivo los `breaks.duration_minutes < 0`. El valor absoluto ya es la duración real; solo el signo está mal.
2. **Recalcular los turnos afectados:** recomputar `break_hours` / `net_hours` y re-correr `CalculateWorkHours` para cada `TimeEntry` cerrado con pausas, **sin** marcarlos como `edited` ni tocar `edited_by` (es una corrección de sistema, no una edición humana).

### Opciones evaluadas

| Opción | Pros | Contras |
|---|---|---|
| **Comando Artisan con `--dry-run`** (recomendada) | Idempotente; primero reporta y luego aplica; control de cuándo ejecutarlo; reutilizable | Requiere ejecución manual en prod |
| **Migración one-off** | Se ejecuta sola en el deploy | Menos control; difícil de re-ejecutar o auditar; recálculo pesado dentro de una migración |
| **Corrección manual SQL + recálculo** | Rápido para un volumen pequeño | Propenso a error; no recalcula buckets; no auditable |

### Esquema sugerido del comando

```
php artisan time-entries:fix-break-durations [--dry-run]
```

- **Dry-run:** cuenta pausas con `duration_minutes < 0` y turnos que cambiarían (`break_hours`/`net_hours` antes → después), sin escribir.
- **Aplicar:**
  1. `UPDATE breaks SET duration_minutes = ABS(duration_minutes) WHERE duration_minutes < 0`.
  2. Por cada `TimeEntry` cerrado con pausas: recomputar `break_hours`/`net_hours` y `CalculateWorkHours->execute(...)`, conservando `status`/`edited_by` originales.
- Envolver en transacción y registrar un resumen (pausas corregidas, turnos recalculados, delta total de horas).

> **Nota:** Verificar antes el orden de la corrección — primero arreglar los signos en `breaks`, **luego** recalcular los `TimeEntry`, para que el recálculo lea ya las duraciones positivas.

---

## Prevención

- En este proyecto, **siempre** calcular duraciones como `$inicio->diffInMethod($fin)` (el momento anterior primero), nunca al revés, por el signed-diff de Carbon 3.
- Los tests de tiempo deben **viajar en el tiempo** (`travelTo` + `travel`) para que las duraciones sean distintas de cero y se valide el signo y la magnitud.
