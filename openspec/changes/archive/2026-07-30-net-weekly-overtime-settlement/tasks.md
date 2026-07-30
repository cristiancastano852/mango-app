## 1. Balance semanal de dominio

- [x] 1.1 Crear pruebas PHPUnit para el balance `-24m + 63m = 39m`, dos semanas deficitarias, dos semanas excedidas, ventana vacía y aislamiento por empleado/compañía.
- [x] 1.2 Crear una Action en `TimeTracking` que agrupe las semanas completas de la ventana, calcule saldos en minutos y retorne overtime trabajado, compensado, liquidable y déficit informativo.
- [x] 1.3 Ejecutar las pruebas específicas de la Action y confirmar que no consulta semanas abiertas ni mezcla empleados.

## 2. Integración de reportes y costos

- [x] 2.1 Actualizar las pruebas de `GenerateEmployeeReport` para los escenarios de compensación, déficit sin descuento, modo diario sin cambios y semana diferida.
- [x] 2.2 Integrar el balance en `GenerateEmployeeReport`, ampliar `overtime_settlement` y aplicar el factor proporcional a los seis buckets enviados a `CalculateReportCosts`.
- [x] 2.3 Actualizar las pruebas de `GenerateCompanyReport` para verificar el balance independiente por empleado y los totales agregados.
- [x] 2.4 Integrar la misma Action en `GenerateCompanyReport` sin introducir consultas por semana o compensación entre empleados.
- [x] 2.5 Ejecutar las pruebas específicas de reportes y costos, incluyendo `WeeklyOvertimeSettlementReportTest` y regresiones del modo diario.

## 3. Presentación y exports

- [x] 3.1 Añadir textos i18n y un resumen compacto en los reportes Vue de empleado y empresa para overtime trabajado, compensación, liquidación y déficit informativo.
- [x] 3.2 Actualizar PDF y Excel para mostrar el mismo resumen, y ampliar sus pruebas de exportación.
- [x] 3.3 Ejecutar las pruebas de exports y `npm run build` para validar la presentación.

## 4. Documentación y verificación

- [x] 4.1 Actualizar `ai-specs/specs/domain-model.md` con la nueva Action y el comportamiento neto de la liquidación semanal.
- [x] 4.2 Revisar cobertura de casos límite con el skill `check-tests`, especialmente cero exacto, múltiples categorías de overtime, salario mensual y permisos existentes.
- [x] 4.3 Ejecutar `vendor/bin/pint --dirty --format agent`, las pruebas PHPUnit afectadas y `npm run build` como verificación final.
