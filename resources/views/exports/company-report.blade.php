<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte General de la Empresa</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.4; }
        .page { padding: 30px; }
        .header { border-bottom: 2px solid #10b981; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; color: #064e3b; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #6b7280; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 13px; font-weight: bold; color: #064e3b; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 10px; }
        .kpi-grid { width: 100%; margin-bottom: 20px; }
        .kpi-grid td { padding: 8px 12px; text-align: center; border: 1px solid #e5e7eb; }
        .kpi-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .kpi-value { font-size: 18px; font-weight: bold; color: #111827; }
        table.data { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.data th { background: #f3f4f6; padding: 6px 8px; text-align: left; border: 1px solid #e5e7eb; font-weight: 600; }
        table.data td { padding: 5px 8px; border: 1px solid #e5e7eb; }
        table.data tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background: #ecfdf5 !important; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <h1>Reporte General de la Empresa</h1>
            <p>Período: {{ $report['period']['start'] }} — {{ $report['period']['end'] }}</p>
        </div>

        {{-- KPIs --}}
        <table class="kpi-grid">
            <tr>
                <td>
                    <div class="kpi-label">Empleados</div>
                    <div class="kpi-value">{{ $report['totals']['total_employees'] }}</div>
                </td>
                <td>
                    <div class="kpi-label">Horas netas</div>
                    <div class="kpi-value">{{ $report['totals']['net_hours'] }}h</div>
                </td>
                <td>
                    <div class="kpi-label">Días trabajados</div>
                    <div class="kpi-value">{{ $report['totals']['total_days_worked'] }}</div>
                </td>
                <td>
                    <div class="kpi-label">Costo total</div>
                    <div class="kpi-value">${{ number_format($report['cost_summary']['total'], 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>

        {{-- Costos --}}
        <div class="section">
            <div class="section-title">Resumen de Costos</div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th class="text-right">Horas</th>
                        <th class="text-right">Costo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Ordinarias</td>
                        <td class="text-right">{{ $report['totals']['regular_hours'] }}</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['regular'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Nocturnas</td>
                        <td class="text-right">{{ $report['totals']['night_hours'] }}</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['night'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Extras</td>
                        <td class="text-right">{{ $report['totals']['overtime_hours'] }}</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['overtime'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Dom/Festivas</td>
                        <td class="text-right">{{ $report['totals']['sunday_holiday_hours'] }}</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['sunday_holiday'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td class="text-right">{{ $report['totals']['net_hours'] }}</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['total'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Ranking de empleados --}}
        <div class="section">
            <div class="section-title">Ranking de Empleados</div>
            <table class="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Empleado</th>
                        <th>Departamento</th>
                        <th class="text-right">Tarifa/hora</th>
                        <th class="text-right">Días</th>
                        <th class="text-right">Horas netas</th>
                        <th class="text-right">Ordinarias</th>
                        <th class="text-right">Nocturnas</th>
                        <th class="text-right">Extras</th>
                        <th class="text-right">Dom/Fest.</th>
                        <th class="text-right">Costo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['employees'] as $idx => $emp)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $emp['name'] }}</td>
                        <td>{{ $emp['department'] ?? 'N/A' }}</td>
                        <td class="text-right">${{ number_format($emp['hourly_rate'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $emp['days_worked'] }}</td>
                        <td class="text-right">{{ $emp['net_hours'] }}</td>
                        <td class="text-right">{{ $emp['regular_hours'] }}</td>
                        <td class="text-right">{{ $emp['night_hours'] }}</td>
                        <td class="text-right">{{ $emp['overtime_hours'] }}</td>
                        <td class="text-right">{{ $emp['sunday_holiday_hours'] }}</td>
                        <td class="text-right">${{ number_format($emp['cost'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="4">TOTAL</td>
                        <td class="text-right">{{ $report['totals']['total_days_worked'] }}</td>
                        <td class="text-right">{{ $report['totals']['net_hours'] }}</td>
                        <td class="text-right">{{ $report['totals']['regular_hours'] }}</td>
                        <td class="text-right">{{ $report['totals']['night_hours'] }}</td>
                        <td class="text-right">{{ $report['totals']['overtime_hours'] }}</td>
                        <td class="text-right">{{ $report['totals']['sunday_holiday_hours'] }}</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['total'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="footer">
            Generado el {{ now()->setTimezone('America/Bogota')->format('d/m/Y H:i') }} — MangoApp
        </div>
    </div>
</body>
</html>
