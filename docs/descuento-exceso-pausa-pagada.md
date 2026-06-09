# Descuento del exceso de pausas pagadas

> **Estado:** implementado.
> **Código:** `app/Domain/TimeTracking/Actions/RecalculateTimeEntry.php` (`deductibleBreakMinutes()`)
> y `app/Domain/TimeTracking/Actions/CalculateWorkHours.php` (reparto vía `net_ratio`).
> **OpenSpec:** `openspec/changes/discount-paid-break-excess/`.

---

## Qué descuenta cada tipo de pausa

Al recomputar las horas de un turno cerrado, cada pausa finalizada aporta a `break_hours`
según su tipo:

```
no pagada                       → descuenta su duración completa
pagada con tope (max_duration)  → descuenta max(0, duración − tope)
pagada sin tope (null)          → no descuenta nada
```

El tope es el campo `break_types.max_duration_minutes`. Antes de este cambio ese campo era
puramente decorativo: una pausa pagada nunca descontaba, sin importar cuánto durara. Ahora el
**exceso** sobre el tope sí se descuenta.

```php
// RecalculateTimeEntry::deductibleBreakMinutes()
no pagada       → duration_minutes
pagada, cap=N   → max(0, duration_minutes − N)
pagada, cap=null→ 0
```

---

## Cómo se reparte el descuento entre tipos de hora

Esta es la parte sutil. El descuento **no sale de un solo tipo de hora** (ni todo diurno ni
todo nocturno): se reparte **proporcionalmente** entre todos los buckets del turno mediante el
factor `net_ratio` (`CalculateWorkHours.php`):

```
net_ratio = net_hours / gross_hours

horas_netas_del_segmento = horas_brutas_del_segmento × net_ratio
```

### Ejemplo

**Turno:** 12:00–22:00 (en esta empresa el inicio nocturno está configurado a las 18:00).
**Pausa pagada con tope de 1h, el empleado tomó 2h** → exceso descontado = 1h.

```
gross_hours = 10h · break descontado = 1h · net_hours = 9h
net_ratio = 9 / 10 = 0.9
```

| Segmento | Tipo | Horas brutas | × net_ratio | Horas netas pagadas |
|----------|------|--------------|-------------|---------------------|
| 12:00–18:00 | Diurna | 6h | × 0.9 | **5.4h** |
| 18:00–22:00 | Nocturna | 4h | × 0.9 | **3.6h** |
| **Total** | | **10h** | | **9h** |

El empleado **no** pierde "1 hora diurna" ni "1 hora nocturna": pierde un pedazo proporcional de
cada tipo. Como el 60% del turno era diurno, 0.6h del descuento salió de las diurnas; el 40%
nocturno aportó las otras 0.4h. Las dos sumas dan la 1h descontada.

---

## Limitación conocida (decisión de diseño)

> El algoritmo **no considera la hora real en que ocurrió la pausa.**

Aunque el almuerzo haya sido 12:00–14:00 (puro horario diurno), el descuento se prorratea igual
sobre diurnas **y** nocturnas, no solo sobre las diurnas. Lo mismo aplica a overtime, dominical y
festivo: todos los buckets se escalan por el mismo `net_ratio`.

El feature de descuento de exceso solo cambió **cuánto** se descuenta, no **cómo se reparte**: el
prorrateo proporcional ya existía para las pausas no pagadas. Cambiar este comportamiento a
"descontar del tipo de hora donde realmente ocurrió la pausa" sería un rediseño mayor del
clasificador de horas.

**Se deja así por ahora.** Este documento existe para dejar registrado el comportamiento y que la
decisión sea explícita, no un efecto colateral silencioso.

---

## Visibilidad

El detalle administrativo del turno (`resources/js/pages/Admin/TimeEntries/Edit.vue`) muestra, por
cada pausa pagada cuya duración supera su tope, los minutos descontados por exceso
(`duration_minutes − max_duration_minutes`). El valor se deriva en la vista; no se persiste.

---

## Alcance temporal

Solo aplica a turnos calculados o recalculados a partir de este cambio. Los registros previos en
producción **no** se recalculan retroactivamente.
