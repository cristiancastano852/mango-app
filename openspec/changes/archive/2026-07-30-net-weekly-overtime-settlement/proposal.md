## Why

La liquidación semanal actual suma únicamente los excedentes positivos de cada semana completa e ignora las semanas por debajo del límite. Esto sobreestima el overtime del periodo cuando una semana tiene déficit y otra tiene excedente; por ejemplo, `-24 min + 63 min` debe liquidar `39 min`, no `63 min`.

## What Changes

- En modo `weekly`, calcular el saldo firmado de cada semana completa como `minutos netos trabajados - max_weekly_minutes`.
- Sumar los saldos semanales de la ventana de liquidación y usar solo el resultado positivo como overtime liquidable.
- Mostrar el resultado negativo como tiempo faltante informativo, sin descuento salarial ni arrastre automático a otro periodo.
- Conservar sin cambios los 12 buckets de cada `time_entry`; el balance se aplica solamente al generar reportes y costos.
- Exponer en pantalla, PDF y Excel un resumen simple del overtime trabajado, la compensación entre semanas, el overtime liquidado y el déficit informativo.
- Mantener sin cambios el modo `daily`, la regla del domingo dueño y el diferimiento de semanas todavía abiertas.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `weekly-overtime-accrual`: la liquidación de semanas completas compensará saldos positivos y negativos dentro de su ventana antes de determinar el overtime pagable.

## Impact

- **Dominio:** `TimeTracking`, principalmente la generación de reportes de empleado y empresa y el cálculo de costos.
- **Frontend y exports:** reportes de empleado/empresa, PDF y Excel mostrarán el balance semanal neto.
- **Multi-tenant:** las consultas continuarán limitadas al empleado y compañía del reporte; no se introducen datos compartidos entre compañías.
- **Roles:** no cambian los permisos; `admin` y `super-admin` continúan accediendo a reportes y exports, y `employee` no obtiene acceso nuevo.
- **Base de datos:** no requiere migración ni valores negativos persistidos. El saldo se deriva de `time_entries` y `max_weekly_minutes`.
- **Dependencias/API:** no agrega dependencias ni endpoints; amplía el payload interno de reportes.

## Non-goals

- No modificar `CalculateWorkHours` ni reclasificar los registros históricos.
- No guardar saldos negativos en los buckets de `time_entries`.
- No crear un banco de horas ni arrastrar déficit entre ventanas de liquidación.
- No descontar dinero, días, salario base ni prestaciones por un saldo negativo.
- No cambiar la configuración del límite semanal ni el comportamiento del modo `daily`.
