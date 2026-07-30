## Context

`CalculateWorkHours` clasifica cada turno y persiste overtime únicamente cuando una semana supera `max_weekly_minutes`. `GenerateEmployeeReport` y `GenerateCompanyReport` liquidan las semanas completas de la ventana resuelta por `ResolveOvertimeSettlementWindow`, pero hoy solo suman esos buckets positivos. Como una semana corta no produce un bucket negativo, su déficit no compensa el excedente de otra semana del mismo periodo.

El cambio cruza reportes, costos, UI y exports, pero no necesita alterar fichajes, clasificación ni esquema de base de datos.

## Goals / Non-Goals

**Goals:**

- Obtener un saldo firmado por cada semana completa de la ventana semanal.
- Liquidar como overtime únicamente el saldo positivo combinado del periodo.
- Mostrar un saldo negativo como información sin afectar dinero.
- Reutilizar la misma lógica en reportes de empleado y empresa.
- Mantener la implementación pequeña y derivada de los datos existentes.

**Non-Goals:**

- Persistir saldos semanales o negativos.
- Crear un banco de horas entre periodos.
- Cambiar `CalculateWorkHours`, los 12 buckets o la regla del domingo dueño.
- Agregar configuración, formularios, endpoints o dependencias.
- Corregir en este cambio la precisión histórica de horas decimales.

## Decisions

### Decisión 1: Calcular el balance al generar el reporte

Una Action de `TimeTracking` calculará, para cada empleado, los minutos netos de cada semana completa incluida en `overtimeWindow` y su saldo `worked_minutes - max_weekly_minutes`. El balance del periodo será la suma de esos saldos.

Esto evita migraciones, backfills y sincronización de datos derivados. Se descarta guardar negativos en `time_entries` porque sus buckets representan clasificación real del turno y son consumidos por detalles, costos y exports.

### Decisión 2: Separar overtime trabajado de overtime liquidado

Los buckets persistidos y el detalle diario continuarán mostrando el overtime realmente clasificado. El reporte derivará:

- `worked_overtime_minutes`: suma positiva de los buckets de overtime de la ventana.
- `offset_minutes`: parte del overtime compensada por semanas deficitarias.
- `payable_overtime_minutes`: `min(worked_overtime_minutes, max(0, period_balance_minutes))`.
- `deficit_minutes`: `max(0, -period_balance_minutes)`.

Así el ejemplo `-24 + 63` conserva `63` minutos trabajados, compensa `24` y liquida `39`. Dos semanas de `40h` y `41h` producen `180` minutos de déficit informativo y cero overtime liquidado.

### Decisión 3: Ajustar categorías con un único factor proporcional

Para que el costo liquidado funcione también cuando existen varias categorías de overtime, los seis totales de overtime enviados a `CalculateReportCosts` se multiplicarán por `payable_overtime_minutes / worked_overtime_minutes`. Si no hay overtime trabajado, el factor será cero.

El factor proporcional conserva la mezcla de recargos sin reconstruir segmentos cronológicos ni introducir prioridades arbitrarias entre overtime diurno, nocturno, dominical y festivo. Se descarta reconstruir minuto a minuto durante el reporte porque duplicaría `CalculateWorkHours` y añadiría complejidad innecesaria.

### Decisión 4: Usar minutos enteros en el nuevo balance

La Action normalizará los agregados semanales a minutos enteros antes de calcular saldos. `max_weekly_minutes` ya usa esa unidad. Las horas decimales se mantendrán únicamente en las interfaces existentes con `CalculateReportCosts` y en el payload donde sean necesarias.

### Decisión 5: Ampliar el payload existente

`overtime_settlement` incorporará el resumen del balance y una lista breve de semanas con rango, minutos trabajados y saldo. Las vistas, PDF y Excel usarán esos mismos datos. No se crearán endpoints ni Form Requests porque no hay entrada nueva del usuario.

El reporte de empresa calculará el resultado por empleado antes de agregar totales, evitando compensar déficit de un empleado con overtime de otro.

### Decisión 6: El déficit es local e informativo

El déficit se reinicia en cada ventana de liquidación semanal. No se guarda, no se arrastra y no se pasa a `CalculateReportCosts`; por tanto, no modifica salario base, auxilio, seguridad social, ajustes ni deducciones.

## Risks / Trade-offs

- **[La reducción proporcional no representa minutos cronológicos exactos]** -> Se mostrará claramente la diferencia entre overtime trabajado y liquidado; la proporcionalidad mantiene la composición de recargos con una regla simple y determinista.
- **[Redondeo entre horas decimales y minutos]** -> El balance nuevo operará en minutos y se limitará al overtime trabajado; la migración completa de horas decimales queda fuera de alcance.
- **[Consultas adicionales en reporte de empresa]** -> La Action aceptará datos agrupados por empleado y semana o hará una consulta agrupada para la ventana, evitando una consulta por semana.
- **[Confusión entre déficit y descuento]** -> La UI y exports lo etiquetarán como informativo y el valor no entrará al cálculo monetario.

## Migration Plan

1. Incorporar la Action de balance y sus pruebas unitarias.
2. Integrarla en reportes de empleado y empresa, y aplicar el factor a los totales enviados a costos.
3. Mostrar el resumen en Vue, PDF y Excel.
4. Desplegar sin migración ni backfill; reportes anteriores se recalcularán con la nueva regla al consultarse.
5. Para rollback, revertir la integración y presentación; los datos persistidos permanecen intactos.

## Open Questions

Ninguna. El déficit no se arrastra y la distribución proporcional se adopta para mantener el alcance mínimo.
