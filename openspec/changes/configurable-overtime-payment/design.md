## Context

Hoy el flujo de reportes separa limpiamente horas y dinero:

1. `CalculateWorkHours` clasifica los minutos de cada turno en 8 buckets mutuamente excluyentes y los guarda en `time_entries` (4 de ellos son overtime). No toca dinero.
2. `GenerateEmployeeReport` / `GenerateCompanyReport` agregan esas horas por rango de fechas (SQL) y llaman a `CalculateReportCosts`.
3. `CalculateReportCosts::execute(float $hourlyRate, array $hourTotals, SurchargeRule $rules)` calcula `costo = horas × tarifa × (1 + recargo%)` por tipo, suma el `total` y devuelve `details[]`.
4. `ReportController` renderiza `Reports/Employee.vue` / `Reports/Company.vue`, o exporta a Excel (`EmployeeReportExport`/`CompanyReportExport`) y PDF (vistas Blade `exports.employee-report`/`exports.company-report`).

No existe ningún desprendible persistido: cada reporte se calcula al vuelo. `SurchargeRule` es la config por compañía (única por `company_id`, `BelongsToCompany`).

Esta separación permite que el feature sea quirúrgico: las horas no se tocan; solo se condiciona el cálculo de dinero en el paso 3.

## Goals / Non-Goals

**Goals:**
- Permitir que las horas extra se muestren con sus horas reales pero con costo `$0` y excluidas del total, cuando se elija no pagarlas.
- Default por compañía (`pay_overtime_by_default`) + override por reporte (empleado y empresa, independientes).
- Persistir la decisión efectiva al exportar (PDF/Excel), con upsert por `(company_id, employee_id, start_date, end_date)`.
- Multi-tenant en todas las tablas y consultas.

**Non-Goals:**
- Snapshot inmutable del reporte (se descartó a favor del registro ligero).
- Control por cada tipo de overtime (un solo switch cubre las 4 categorías).
- Cambios a `CalculateWorkHours` o a la clasificación de horas.

## Decisions

### 1. El flag se aplica en `CalculateReportCosts`, no en la clasificación de horas
`CalculateReportCosts::execute()` recibe un nuevo parámetro `bool $payOvertime = true`. Cuando es `false`:
- Los 4 costos de overtime se fuerzan a `0.0`.
- El `total` se calcula sin ellos.
- Cada uno de los 4 `details[]` gana `'compensated' => true` (y `'subtotal' => 0`), conservando `hours` y `surcharge` originales para mostrar contexto.

Para los tipos no-overtime, `compensated` es `false`. Esto mantiene el contrato de `details[]` estable y deja al frontend pintar el badge "Compensado con tiempo".

**Alternativa descartada:** condicionar dentro de `GenerateEmployeeReport` post-cálculo (poner los subtotales en 0 después). Se descarta porque duplicaría la lógica entre el reporte de empleado y el de empresa y dejaría el `total` inconsistente con `details`.

### 2. Resolución del flag efectivo (precedencia)
En `ReportController`, para cada generación:
```
flag efectivo =
  request('pay_overtime')                      // override explícito del usuario (toggle)
  ?? decisión guardada del periodo             // overtime_payment_decisions
  ?? surcharge_rules.pay_overtime_by_default   // default de compañía
```
- En la **vista** (GET reporte): si no viene `pay_overtime` en el request, se precarga desde la decisión guardada del periodo y, si no hay, desde el default. El valor resuelto se pasa como prop para inicializar el switch.
- En el **export**: se usa el `pay_overtime` enviado por el toggle; ese valor es el que se persiste.

### 3. Persistencia: una tabla con `employee_id` nullable
Nueva tabla `overtime_payment_decisions`:

| columna | tipo | nota |
|---|---|---|
| id | bigint | |
| company_id | FK companies | `BelongsToCompany` |
| employee_id | FK employees, **nullable** | lleno = decisión de empleado; NULL = decisión de reporte de empresa |
| start_date | date | periodo resuelto |
| end_date | date | periodo resuelto |
| pay_overtime | boolean | |
| exported_by | FK users, nullable | quién exportó |
| exported_at | timestamp | cuándo |
| timestamps | | |

- Índice único `(company_id, employee_id, start_date, end_date)` para soportar `updateOrCreate` (upsert, gana la última exportación).
- El periodo se persiste con las fechas **resueltas** (`resolveDateRange`), no el preset, para que presets y rangos custom convivan.
- Modelo `OvertimePaymentDecision` con `BelongsToCompany`, en dominio Company.

**Alternativa descartada:** dos tablas (empleado vs empresa). Una sola tabla con `employee_id` nullable es suficiente y el índice único distingue ambos casos (NULL cuenta como valor distinto en el índice según el driver; en MySQL/SQLite múltiples NULL se permiten, por lo que el upsert de empresa se resuelve filtrando `whereNull('employee_id')` explícitamente).

### 4. La persistencia ocurre solo al exportar
Los métodos `exportEmployeeExcel/Pdf` y `exportCompanyExcel/Pdf` hacen el `updateOrCreate` antes de generar el archivo. Los métodos de vista (`employee`, `company`) **no** escriben. Esto cumple "guardar al exportar" y evita ruido de escrituras por simples visualizaciones.

### 5. Reporte de empresa: flag global independiente
El reporte de empresa agrega muchos empleados. Su switch aplica un único `pay_overtime` a todo el total (no consulta las decisiones por empleado). Se persiste con `employee_id = NULL`. `GenerateCompanyReport` propaga el mismo flag a todos los cálculos de costo de la agregación.

## Risks / Trade-offs

- **[Recalculo vs snapshot]** Como las horas se recalculan desde `time_entries`, editar un turno luego de exportar cambia los montos aunque el flag persista. → Aceptado explícitamente al elegir el registro ligero; documentado para el usuario.
- **[Incoherencia empresa vs empleado]** El total de empresa puede no cuadrar con la suma de desprendibles individuales si los flags difieren. → Es intencional por diseño (flags independientes); no se concilian.
- **[NULL en índice único]** El comportamiento de UNIQUE con NULL varía por driver. → Mitigación: en el upsert de empresa filtrar con `whereNull('employee_id')` en el `updateOrCreate` en vez de confiar solo en la constraint; tests cubren ambos drivers (SQLite en test, MySQL en prod).
- **[data-model.md desactualizado]** El doc lista `overtime_hours` y omite `max_daily_hours`/los 8 buckets. → Actualizar `ai-specs/specs/data-model.md` con las dos nuevas estructuras en el mismo commit (paso del backend-standards).

## Migration Plan

1. Migración A: `ALTER surcharge_rules ADD pay_overtime_by_default BOOLEAN DEFAULT true` (after `night_sunday` o donde corresponda). Empresas existentes quedan en `true` → comportamiento idéntico al actual.
2. Migración B: `CREATE overtime_payment_decisions` con índice único.
3. Sin backfill necesario (default cubre filas existentes; la tabla nueva arranca vacía).
4. Rollback: ambas migraciones tienen `down()` (drop columna / drop tabla). Sin pérdida de datos de horas porque nunca se tocan.
