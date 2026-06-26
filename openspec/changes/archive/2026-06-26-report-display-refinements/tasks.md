## 1. Vista del reporte (frontend)

- [x] 1.1 En `resources/js/pages/Reports/Employee.vue` agregar un `computed visibleDetails` que filtre las filas premium ocultables: oculta `dominical`, `night_dominical`, `night_holiday`, `overtime_day_dominical`, `overtime_night_dominical`, `overtime_day_holiday`, `overtime_night_holiday` cuando su flag de pago está OFF y la fila no representa pago real (subtotal 0 y horas 0). Nunca ocultar `holiday`. Usar `visibleDetails` en el `v-for`.
- [x] 1.2 En la celda de "horas" de las filas `dominical` y `holiday`, mostrar `{n} día(s)` cuando `dominical_mode === 'day'` / `holiday_mode === 'day'` (usando `dominical_paid_days` / `holiday_worked_days`); en modo `hour` conservar las horas.
- [x] 1.3 Agregar la clave i18n `reports.costs.days_count` con pluralización (1 día / N días) en `es.json` y `en.json`.
- [x] 1.4 `npm run build` y verificar que compila sin errores.

## 2. Export PDF

- [x] 2.1 En `resources/views/exports/employee-report.blade.php` envolver cada fila de recargo premium ocultable en la misma condición de visibilidad (flag OFF + subtotal 0 + horas 0 → no renderizar), conservando siempre el festivo diurno.
- [x] 2.2 En las filas `dominical` y `holiday` del PDF, mostrar días en modo por día y horas en modo por hora.

## 3. Export Excel

- [x] 3.1 En `app/Exports/EmployeeReportExport.php` aplicar la misma condición de visibilidad para no emitir las filas premium ocultables.
- [x] 3.2 En las filas `dominical` y `holiday` del Excel, emitir días en modo por día y horas en modo por hora.
- [x] 3.3 `vendor/bin/pint --dirty --format agent`.

## 4. Tests

- [x] 4.1 Añadir/actualizar tests de export (`ReportExportTest`): fila premium con flag OFF no aparece en Excel ni en el HTML del PDF; festivo diurno siempre presente; dominical hourly OFF permanece visible.
- [x] 4.2 Añadir test de export que verifique que en modo por día la fila dominical/festivo muestra los días (no las horas).
- [x] 4.3 Ejecutar `php artisan test --compact --filter=ReportExportTest` y confirmar que pasa.

## 5. Verificación final

- [x] 5.1 Confirmar que el reporte de empresa y su cálculo no se vieron afectados (cambios solo de presentación del reporte individual).
