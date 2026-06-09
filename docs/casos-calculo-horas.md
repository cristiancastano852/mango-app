# Casos de Cálculo de Horas y Costos

Referencia completa de todos los escenarios posibles al clasificar horas trabajadas y calcular el total a pagar. Basado en legislación colombiana 2026.

---

## Configuración de referencia (defaults Colombia)

| Parámetro | Valor | Descripción |
|-----------|-------|-------------|
| `max_daily_hours` | 8h | Límite ordinario diario |
| `max_weekly_hours` | 42h | Límite ordinario semanal |
| `night_start_time` | 21:00 | Inicio horario nocturno |
| `night_end_time` | 06:00 | Fin horario nocturno |
| `night_surcharge` | 35% | Recargo nocturno ordinario |
| `overtime_day` | 25% | Extra diurna (semana) |
| `overtime_night` | 75% | Extra nocturna (semana) |
| `sunday_holiday` | 75% | Dominical/festivo diurno |
| `night_sunday` | 110% | Nocturno dominical/festivo |
| `overtime_day_sunday` | 100% | Extra diurna dominical |
| `overtime_night_sunday` | 150% | Extra nocturna dominical |

**Tarifa de ejemplo:** $10.000/hora  
**Regla de clasificación:** cada minuto cae en exactamente un tipo. Prioridad: extra > dominical/festivo > nocturno > ordinario.

---

## Tipos de hora resultantes

| # | Tipo | Condición |
|---|------|-----------|
| 1 | **Ordinaria** | Semana + diurno (06:00–21:00) + dentro de límites |
| 2 | **Nocturna** | Semana + nocturno (21:00–06:00) + dentro de límites |
| 3 | **Dominical/festiva diurna** | Dom/festivo + diurno + dentro de límites |
| 4 | **Nocturna dominical** | Dom/festivo + nocturno + dentro de límites |
| 5 | **Extra diurna** | Semana + diurno + supera límite diario o semanal |
| 6 | **Extra nocturna** | Semana + nocturno + supera límite diario o semanal |
| 7 | **Extra diurna dominical** | Dom/festivo + diurno + supera límite |
| 8 | **Extra nocturna dominical** | Dom/festivo + nocturno + supera límite |

---

## Grupo 1 — Casos puros (un solo tipo)

### Caso 1.1 – Solo ordinarias
**Turno:** Lunes 08:00–16:00, sin pausas  
**Horas brutas:** 8h · **Horas netas:** 8h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Ordinaria | 8h | 0% | $80.000 |
| **Total** | **8h** | | **$80.000** |

> Turno estándar de jornada completa. No cruza ningún límite ni horario nocturno.

---

### Caso 1.2 – Solo nocturnas
**Turno:** Lunes 21:00–23:00, sin pausas (0h previas ese día)  
**Horas brutas:** 2h · **Horas netas:** 2h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Nocturna | 2h | 35% | $27.000 |
| **Total** | **2h** | | **$27.000** |

> Inicio de turno exactamente en el umbral nocturno (21:00). No hay horas previas → no hay extras.

---

### Caso 1.3 – Solo dominical diurna
**Turno:** Domingo 09:00–17:00, sin pausas  
**Horas brutas:** 8h · **Horas netas:** 8h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Dominical diurna | 8h | 75% | $140.000 |
| **Total** | **8h** | | **$140.000** |

> Jornada completa en domingo dentro del límite diario. No genera extras.

---

### Caso 1.4 – Solo nocturna dominical
**Turno:** Domingo 21:00–23:00, sin pausas (0h previas ese domingo)  
**Horas brutas:** 2h · **Horas netas:** 2h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Nocturna dominical | 2h | 110% | $42.000 |
| **Total** | **2h** | | **$42.000** |

> El minuto es nocturno Y dominical. La prioridad es dominical → va a `night_sunday` (no a `night`).

---

### Caso 1.5 – Solo extra diurna
**Contexto:** Empleado ya trabajó 8h diurnas (08:00–16:00) ese mismo lunes.  
**Turno adicional:** Lunes 16:00–18:00, sin pausas  
**Horas brutas:** 2h · **Horas netas:** 2h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Extra diurna | 2h | 25% | $25.000 |
| **Total** | **2h** | | **$25.000** |

> El límite diario ya estaba agotado al iniciar el turno. Todo lo que entre es extra diurna.

---

### Caso 1.6 – Solo extra nocturna
**Contexto:** Empleado ya trabajó 8h diurnas (08:00–16:00) ese mismo lunes.  
**Turno adicional:** Lunes 21:00–23:00, sin pausas  
**Horas brutas:** 2h · **Horas netas:** 2h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Extra nocturna | 2h | 75% | $35.000 |
| **Total** | **2h** | | **$35.000** |

> El límite diario está agotado y el turno es nocturno → extra nocturna (no `night`, no `overtime_day`).

---

### Caso 1.7 – Solo extra diurna dominical
**Contexto:** Empleado ya trabajó 8h dominicales (08:00–16:00) ese mismo domingo.  
**Turno adicional:** Domingo 17:00–19:00, sin pausas  
**Horas brutas:** 2h · **Horas netas:** 2h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Extra diurna dominical | 2h | 100% | $40.000 |
| **Total** | **2h** | | **$40.000** |

> Límite diario agotado en domingo, horario diurno → `overtime_day_sunday`.

---

### Caso 1.8 – Solo extra nocturna dominical
**Contexto:** Empleado ya trabajó 8h dominicales (08:00–16:00) ese mismo domingo.  
**Turno adicional:** Domingo 21:00–23:00, sin pausas  
**Horas brutas:** 2h · **Horas netas:** 2h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Extra nocturna dominical | 2h | 150% | $50.000 |
| **Total** | **2h** | | **$50.000** |

> El peor recargo posible: límite agotado + es domingo + es nocturno.

---

## Grupo 2 — Cruce de umbral diurno/nocturno

### Caso 2.1 – Ordinaria → Nocturna (cruza 21:00)
**Turno:** Lunes 19:00–23:00, sin pausas (0h previas)  
**Horas brutas:** 4h · **Horas netas:** 4h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 19:00–21:00 | Ordinaria | 2h | 0% | $20.000 |
| 21:00–23:00 | Nocturna | 2h | 35% | $27.000 |
| **Total** | | **4h** | | **$47.000** |

> El sistema crea un breakpoint exacto en 21:00 y clasifica cada segmento por separado.

---

### Caso 2.2 – Nocturna → Ordinaria (cruza 06:00)
**Turno:** Martes 04:00–08:00, sin pausas (0h previas ese martes)  
**Horas brutas:** 4h · **Horas netas:** 4h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 04:00–06:00 | Nocturna | 2h | 35% | $27.000 |
| 06:00–08:00 | Ordinaria | 2h | 0% | $20.000 |
| **Total** | | **4h** | | **$47.000** |

> La madrugada antes de las 06:00 sigue siendo nocturna, aunque sea "mañana temprano".

---

### Caso 2.3 – Nocturna cruzando medianoche (semana → semana)
**Turno:** Lunes 22:00–04:00 martes, sin pausas (0h previas en ambos días)  
**Horas brutas:** 6h · **Horas netas:** 6h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 22:00–04:00 | Nocturna | 6h | 35% | $81.000 |
| **Total** | | **6h** | | **$81.000** |

> Todo el rango 22:00–04:00 cae dentro del horario nocturno (21:00–06:00). El cruce de medianoche no cambia el tipo porque lunes y martes son ambos días de semana y no se supera el límite diario en ninguno.

---

## Grupo 3 — Cruce del límite diario (generando extras)

### Caso 3.1 – Ordinaria + Extra diurna
**Turno:** Lunes 06:00–18:00, sin pausas (0h previas)  
**Horas brutas:** 12h · **Horas netas:** 12h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 06:00–14:00 | Ordinaria | 8h | 0% | $80.000 |
| 14:00–18:00 | Extra diurna | 4h | 25% | $50.000 |
| **Total** | | **12h** | | **$130.000** |

> A las 14:00 se agota el cupo diario de 8h. Todo lo posterior (diurno) es extra diurna.

---

### Caso 3.2 – Ordinaria + Extra diurna **con pausa de almuerzo**
*(Ejemplo del usuario: "de 6am a 5pm con 1 hora de almuerzo")*

**Turno:** Lunes 06:00–17:00, 1h de pausa  
**Horas brutas:** 11h · **Horas netas:** 10h · **net_ratio:** 10/11 ≈ 0.909

> El límite de 8h **netas** se alcanza cuando han transcurrido 8 / 0.909 ≈ 8.8h **brutas** desde las 06:00, es decir, aproximadamente a las 14:48.

| Segmento | Tipo | Horas netas | Recargo | Subtotal |
|----------|------|-------------|---------|----------|
| 06:00–14:48 | Ordinaria | 8h | 0% | $80.000 |
| 14:48–17:00 | Extra diurna | 2h | 25% | $25.000 |
| **Total** | | **10h** | | **$105.000** |

> La pausa se distribuye proporcionalmente a lo largo de todo el turno mediante el `net_ratio`. Por eso el umbral de 8h netas no ocurre exactamente a las 14:00 sino un poco después.

---

### Caso 3.3 – Turno muy largo: Ordinaria + Extra diurna + Extra nocturna
**Turno:** Lunes 06:00–23:00, sin pausas (0h previas)  
**Horas brutas:** 17h · **Horas netas:** 17h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 06:00–14:00 | Ordinaria | 8h | 0% | $80.000 |
| 14:00–21:00 | Extra diurna | 7h | 25% | $87.500 |
| 21:00–23:00 | Extra nocturna | 2h | 75% | $35.000 |
| **Total** | | **17h** | | **$202.500** |

> El límite diario se agota en la tarde. A las 21:00 el horario se vuelve nocturno pero el límite diario ya está excedido → las horas nocturnas posteriores son `overtime_night`, no `night`.

---

### Caso 3.4 – Ordinaria + Nocturna ordinaria + Extra nocturna + Nocturna del día siguiente
**Turno:** Lunes 14:00–02:00 martes, sin pausas (0h previas en ambos días)  
**Horas brutas:** 12h · **Horas netas:** 12h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 14:00–21:00 (lun) | Ordinaria | 7h | 0% | $70.000 |
| 21:00–22:00 (lun) | Nocturna | 1h | 35% | $13.500 |
| 22:00–00:00 (lun) | Extra nocturna | 2h | 75% | $35.000 |
| 00:00–02:00 (mar) | Nocturna | 2h | 35% | $27.000 |
| **Total** | | **12h** | | **$145.500** |

> A las 21:00 el acumulador diario llega a 7h + 1h = 8h (límite). A las 22:00 comienza la sobretiempo nocturna. A medianoche el acumulador diario se **reinicia** para el martes, por lo que 00:00–02:00 vuelve a ser nocturna ordinaria.

---

### Caso 3.5 – Nocturna + Ordinaria + Extra diurna (madrugada larga)
**Turno:** Martes 03:00–18:00, sin pausas (0h previas ese martes)  
**Horas brutas:** 15h · **Horas netas:** 15h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 03:00–06:00 | Nocturna | 3h | 35% | $40.500 |
| 06:00–11:00 | Ordinaria | 5h | 0% | $50.000 |
| 11:00–18:00 | Extra diurna | 7h | 25% | $87.500 |
| **Total** | | **15h** | | **$178.000** |

> Las primeras 3h son nocturnas pero cuentan para el acumulador diario. A las 11:00 el acumulador llega a 8h (3+5) → todo lo posterior es extra diurna.

---

## Grupo 4 — Dominical / Festivo

### Caso 4.1 – Dominical diurna + Nocturna dominical (cruza 21:00)
**Turno:** Domingo 19:00–23:00, sin pausas (0h previas)  
**Horas brutas:** 4h · **Horas netas:** 4h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 19:00–21:00 | Dominical diurna | 2h | 75% | $35.000 |
| 21:00–23:00 | Nocturna dominical | 2h | 110% | $42.000 |
| **Total** | | **4h** | | **$77.000** |

> Al cruzar las 21:00 en domingo, el tipo cambia de `sunday_holiday` a `night_sunday`.

---

### Caso 4.2 – Dominical con extra diurna dominical (supera límite diario)
**Turno:** Domingo 06:00–18:00, sin pausas (0h previas)  
**Horas brutas:** 12h · **Horas netas:** 12h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 06:00–14:00 | Dominical diurna | 8h | 75% | $140.000 |
| 14:00–18:00 | Extra diurna dominical | 4h | 100% | $80.000 |
| **Total** | | **12h** | | **$220.000** |

> Límite diario agotado en domingo → las extras son `overtime_day_sunday`, no `overtime_day`.

---

### Caso 4.3 – Dominical completo (los 4 tipos dominicales)
**Turno:** Domingo 06:00–23:00, sin pausas (0h previas)  
**Horas brutas:** 17h · **Horas netas:** 17h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 06:00–14:00 | Dominical diurna | 8h | 75% | $140.000 |
| 14:00–21:00 | Extra diurna dominical | 7h | 100% | $140.000 |
| 21:00–23:00 | Extra nocturna dominical | 2h | 150% | $50.000 |
| **Total** | | **17h** | | **$330.000** |

> El turno más costoso posible en un solo día. Nótese que no hay `night_sunday` porque el límite diario ya estaba agotado antes de las 21:00.

---

### Caso 4.4 – Festivo en día de semana (mismo comportamiento que domingo)
**Turno:** Jueves festivo 08:00–16:00, sin pausas  
**Horas brutas:** 8h · **Horas netas:** 8h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Dominical/festiva diurna | 8h | 75% | $140.000 |
| **Total** | **8h** | | **$140.000** |

> Un festivo de semana se clasifica exactamente igual que un domingo. El modelo aplica `sunday_holiday` a ambos.

---

### Caso 4.5 – Festivo con turno nocturno cruzando a día hábil
**Turno:** Festivo jueves 21:00–01:00 viernes, sin pausas (0h previas en ambos días)  
**Horas brutas:** 4h · **Horas netas:** 4h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 21:00–00:00 (festivo jue) | Nocturna dominical | 3h | 110% | $63.000 |
| 00:00–01:00 (viernes normal) | Nocturna | 1h | 35% | $13.500 |
| **Total** | | **4h** | | **$76.500** |

> A medianoche el día cambia a viernes (hábil), por lo que el recargo baja de 110% a 35%.

---

### Caso 4.6 – Festivo con todos los tipos (diurna + nocturna + extras)
**Turno:** Festivo jueves 06:00–23:00, sin pausas (0h previas)  
**Horas brutas:** 17h · **Horas netas:** 17h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 06:00–14:00 | Dominical/festiva diurna | 8h | 75% | $140.000 |
| 14:00–21:00 | Extra diurna dominical | 7h | 100% | $140.000 |
| 21:00–23:00 | Extra nocturna dominical | 2h | 150% | $50.000 |
| **Total** | | **17h** | | **$330.000** |

> Idéntico al caso 4.3 (domingo completo). Un festivo de semana produce exactamente el mismo desglose.

---

## Grupo 5 — Cruce de medianoche con cambio de tipo de día

### Caso 5.1 – Sábado noche → domingo madrugada
**Turno:** Sábado 22:00–04:00 domingo, sin pausas (0h previas en ambos días)  
**Horas brutas:** 6h · **Horas netas:** 6h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 22:00–00:00 (sábado) | Nocturna | 2h | 35% | $27.000 |
| 00:00–04:00 (domingo) | Nocturna dominical | 4h | 110% | $84.000 |
| **Total** | | **6h** | | **$111.000** |

> En el momento en que el reloj cruza medianoche y entra al domingo, el mismo horario nocturno pasa de 35% a 110%. La diferencia de costo es significativa.

---

### Caso 5.2 – Domingo noche → lunes madrugada
**Turno:** Domingo 22:00–04:00 lunes, sin pausas (0h previas en ambos días)  
**Horas brutas:** 6h · **Horas netas:** 6h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 22:00–00:00 (domingo) | Nocturna dominical | 2h | 110% | $42.000 |
| 00:00–04:00 (lunes) | Nocturna | 4h | 35% | $54.000 |
| **Total** | | **6h** | | **$96.000** |

> El inverso del caso 5.1. Al cruzar a lunes el recargo baja de 110% a 35%.

---

### Caso 5.3 – Sábado tarde → domingo, con cambio diurno/nocturno en medio
**Turno:** Sábado 20:00–04:00 domingo, sin pausas (0h previas en ambos días)  
**Horas brutas:** 8h · **Horas netas:** 8h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 20:00–21:00 (sáb) | Ordinaria | 1h | 0% | $10.000 |
| 21:00–00:00 (sáb) | Nocturna | 3h | 35% | $40.500 |
| 00:00–04:00 (dom) | Nocturna dominical | 4h | 110% | $84.000 |
| **Total** | | **8h** | | **$134.500** |

> Tres tipos en un solo turno generados por dos breakpoints distintos: 21:00 (umbral nocturno) y 00:00 (cambio de día).

---

## Grupo 6 — Límite semanal

### Caso 6.1 – Extras por límite semanal (no por límite diario)
**Contexto:** Lun–Vie 5 × 7h/día = 35h semanales (dentro del límite diario de 8h cada día).  
**Turno del sábado:** Sábado 08:00–18:00 = 10h netas  
**Acumulado semanal previo:** 35h · **Límite semanal:** 42h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 08:00–15:00 | Ordinaria | 7h | 0% | $70.000 |
| 15:00–18:00 | Extra diurna | 3h | 25% | $37.500 |
| **Total** | | **10h** | | **$107.500** |

> El límite semanal (42h) se agota a las 35+7=42h, que ocurre a las 15:00. A partir de ahí, aunque sea dentro del límite diario, las horas son extra diurna.

---

### Caso 6.2 – Límite semanal se agota durante turno nocturno
**Contexto:** Lun–Jue 4 × 10h/día = 40h semanales (cada día tiene 8h ordinaria + 2h extra diurna).  
**Turno del viernes:** Viernes 20:00–02:00 sábado, sin pausas (0h previas ese viernes)  
**Acumulado semanal previo:** 40h · **Límite semanal:** 42h · **Restante ordinario:** 2h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 20:00–21:00 (vie) | Ordinaria | 1h | 0% | $10.000 |
| 21:00–22:00 (vie) | Nocturna | 1h | 35% | $13.500 |
| 22:00–00:00 (vie) | Extra nocturna | 2h | 75% | $35.000 |
| 00:00–02:00 (sáb) | Nocturna | 2h | 35% | $27.000 |
| **Total** | | **6h** | | **$85.500** |

> El límite semanal se agota a las 22:00 (40 + 1 ordinaria + 1 nocturna = 42h). A partir de ahí el horario nocturno ya es extra nocturna. Al cruzar medianoche hacia el sábado el acumulador semanal sigue excedido, pero el acumulador diario del sábado se reinicia en 0. El sábado no es domingo, así que 00:00–02:00 es nocturna ordinaria (no dominical).

---

### Caso 6.3 – Límite semanal agotado en turno dominical
**Contexto:** Lun–Sáb: 40h trabajadas. Turno dominical 08:00–12:00 domingo.  
**Acumulado semanal previo:** 40h · **Restante ordinario semanal:** 2h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 08:00–10:00 | Dominical diurna | 2h | 75% | $35.000 |
| 10:00–12:00 | Extra diurna dominical | 2h | 100% | $40.000 |
| **Total** | | **4h** | | **$75.000** |

> El límite semanal se agota a las 10:00 (40+2=42h). A partir de ahí, aunque sea dentro del límite diario y sea domingo, se aplica `overtime_day_sunday`.

---

### Caso 6.4 – Límite diario y semanal se agotan en el mismo turno (diario primero)
**Contexto:** Semana anterior: 41h. Turno del lunes: 12:00–22:00, sin pausas.  
**Acumulado semanal previo:** 41h · **Acumulado diario previo:** 0h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 12:00–13:00 | Ordinaria | 1h | 0% | $10.000 |
| 13:00–20:00 | Extra diurna | 7h | 25% | $87.500 |
| 20:00–21:00 | Extra diurna | 1h | 25% | $12.500 |
| 21:00–22:00 | Extra nocturna | 1h | 75% | $17.500 |
| **Total** | | **10h** | | **$127.500** |

> A la 1h de turno (13:00) se agota el semanal (41+1=42h). A las 21:00 el tipo cambia a nocturno, pero la condición `isOvertime=true` sigue activa, por lo que es extra nocturna.

---

## Grupo 7 — Ejemplos con pausa (net_ratio)

### Caso 7.1 – Ordinaria + Extra diurna con almuerzo *(ejemplo del usuario)*
**Turno:** Lunes 06:00–17:00, 1h de pausa  
**Horas brutas:** 11h · **Horas netas:** 10h · **net_ratio:** 10/11 ≈ 0.9091

El sistema calcula cuántas horas **brutas** deben pasar para acumular 8h **netas**:  
`8 / 0.9091 ≈ 8.8h brutas → límite diario a las 14:48`

| Segmento | Tipo | Horas netas | Recargo | Subtotal |
|----------|------|-------------|---------|----------|
| 06:00–14:48 | Ordinaria | 8h | 0% | $80.000 |
| 14:48–17:00 | Extra diurna | 2h | 25% | $25.000 |
| **Total** | | **10h** | | **$105.000** |

---

### Caso 7.2 – Turno 12:00–11:00 del día siguiente con 1h de almuerzo *(ejemplo del usuario)*
**Turno:** Martes 12:00–11:00 miércoles, 1h de pausa  
**Horas brutas:** 23h · **Horas netas:** 22h · **net_ratio:** 22/23 ≈ 0.9565

Cálculo del límite diario del martes:  
`8 / 0.9565 ≈ 8.36h brutas → límite diario a las 20:22 aprox.`

| Segmento | Tipo | Horas netas aprox. | Recargo | Subtotal |
|----------|----- |--------------------|---------|----------|
| 12:00–20:22 (mar) | Ordinaria | 8h | 0% | $80.000 |
| 20:22–21:00 (mar) | Extra diurna | ~0.6h | 25% | ~$7.500 |
| 21:00–00:00 (mar) | Extra nocturna | ~2.9h | 75% | ~$50.750 |
| 00:00–06:00 (mié) | Nocturna | ~5.7h | 35% | ~$77.000 |
| 06:00–08:21 (mié) | Ordinaria | ~2.3h | 0% | ~$23.000 |
| 08:21–11:00 (mié) | Extra diurna | ~2.5h | 25% | ~$31.250 |
| **Total** | | **~22h** | | **~$269.500** |

> Al cruzar la medianoche hacia el miércoles, el acumulador diario se reinicia a 0. Las 6h de madrugada del miércoles (00:00–06:00) son nocturnas ordinarias, no extras. A las 08:21 aprox. se agota el cupo diario del miércoles y el resto del turno es extra diurna.

---

## Grupo 8 — Edge cases

### Caso 8.1 – Exactamente en el límite diario (sin extras)
**Turno:** Lunes 08:00–16:00, sin pausas = 8h netas exactas

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Ordinaria | 8h | 0% | $80.000 |
| **Total** | **8h** | | **$80.000** |

> El minuto 08:00 del clock_out no se incluye → exactamente 8h netas, ninguna es extra.

---

### Caso 8.2 – 1 minuto extra (primer minuto de extra diurna)
**Turno:** Lunes 08:00–16:01, sin pausas = 8h 1min netas

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Ordinaria | 8h | 0% | $80.000 |
| Extra diurna | 1min | 25% | ~$208 |
| **Total** | **8h 1min** | | **~$80.208** |

> El breakpoint de límite diario se crea exactamente al minuto en que se alcanza la 8va hora neta.

---

### Caso 8.3 – Festivo recurrente (ej: 1 de enero de cualquier año)
**Configuración:** Festivo `is_recurring = true` con fecha `2025-01-01`.  
**Turno:** 2026-01-01 08:00–16:00 = 8h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Dominical/festiva diurna | 8h | 75% | $140.000 |
| **Total** | **8h** | | **$140.000** |

> Los festivos recurrentes se verifican por mes-día sin importar el año. El primero de enero de 2026 coincide aunque el festivo fue registrado con fecha 2025.

---

### Caso 8.4 – Festivo que cae en domingo (doble condición, mismo resultado)
**Turno:** Domingo festivo 09:00–17:00 = 8h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Dominical/festiva diurna | 8h | 75% | $140.000 |
| **Total** | **8h** | | **$140.000** |

> No hay recargo doble. Que sea simultáneamente domingo y festivo no modifica el resultado: sigue siendo `sunday_holiday`.

---

### Caso 8.5 – Turno con horas previas en el mismo día (dos turnos en el mismo día)
**Turno 1 (ya calculado):** Lunes 06:00–10:00 = 4h ordinarias  
**Turno 2 (nuevo):** Lunes 14:00–20:00 = 6h brutas, 6h netas  
**Acumulado diario previo al turno 2:** 4h · **Restante ordinario diario:** 4h

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 14:00–18:00 | Ordinaria | 4h | 0% | $40.000 |
| 18:00–20:00 | Extra diurna | 2h | 25% | $25.000 |
| **Total** | | **6h** | | **$65.000** |

> El sistema consulta los turnos anteriores del mismo día antes de clasificar. A las 18:00 se llega a 4+4=8h totales en el día → extras.

---

### Caso 8.6 – Límite diario distinto al default (empresa con jornada de 10h)
**Configuración:** `max_daily_hours = 10`  
**Turno:** Lunes 06:00–18:00 = 12h netas

| Segmento | Tipo | Horas | Recargo | Subtotal |
|----------|------|-------|---------|----------|
| 06:00–16:00 | Ordinaria | 10h | 0% | $100.000 |
| 16:00–18:00 | Extra diurna | 2h | 25% | $25.000 |
| **Total** | | **12h** | | **$125.000** |

> El breakpoint de límite diario se desplaza a las 16:00 en lugar de las 14:00.

---

### Caso 8.7 – Límite semanal distinto al default (empresa con jornada de 36h semanales)
**Configuración:** `max_weekly_hours = 36`  
**Contexto:** Lun–Jue 4 × 9h = 36h acumuladas.  
**Turno del viernes:** Viernes 08:00–16:00 = 8h

| Tipo | Horas | Recargo | Subtotal |
|------|-------|---------|----------|
| Extra diurna | 8h | 25% | $100.000 |
| **Total** | **8h** | | **$100.000** |

> El cupo semanal ya estaba 100% agotado al iniciar el turno del viernes. Todas las horas del viernes son extra, aunque sean las primeras del día.

---

## Resumen de combinaciones posibles

| # | Sem/Dom | Diurno/Noc | Dentro/Extra | Tipo resultante | Recargo |
|---|---------|------------|--------------|-----------------|---------|
| 1 | Semana | Diurno | Dentro | Ordinaria | 0% |
| 2 | Semana | Nocturno | Dentro | Nocturna | 35% |
| 3 | Dom/Fest | Diurno | Dentro | Dom/festiva diurna | 75% |
| 4 | Dom/Fest | Nocturno | Dentro | Nocturna dominical | 110% |
| 5 | Semana | Diurno | Extra | Extra diurna | 25% |
| 6 | Semana | Nocturno | Extra | Extra nocturna | 75% |
| 7 | Dom/Fest | Diurno | Extra | Extra diurna dom. | 100% |
| 8 | Dom/Fest | Nocturno | Extra | Extra nocturna dom. | 150% |

**Regla de oro:** los 3 atributos (día de la semana, horario, y si supera límite) determinan unívocamente el tipo de hora. La prioridad en el código es: **extra > dom/festivo > nocturno > ordinario**.
