<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Individual - {{ $report['employee']['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.4; }
        .page { padding: 30px; }
        .header { border-bottom: 2px solid #3b82f6; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; color: #1e3a5f; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #6b7280; }
        .meta { display: flex; margin-bottom: 20px; }
        .meta-item { margin-right: 30px; }
        .meta-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta-value { font-size: 12px; font-weight: bold; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 13px; font-weight: bold; color: #1e3a5f; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 10px; }
        .kpi-grid { width: 100%; margin-bottom: 20px; }
        .kpi-grid td { padding: 8px 12px; text-align: center; border: 1px solid #e5e7eb; }
        .kpi-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .kpi-value { font-size: 18px; font-weight: bold; color: #111827; }
        table.data { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.data th { background: #f3f4f6; padding: 6px 8px; text-align: left; border: 1px solid #e5e7eb; font-weight: 600; }
        table.data td { padding: 5px 8px; border: 1px solid #e5e7eb; }
        table.data tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background: #eff6ff !important; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <h1>Reporte Individual</h1>
            <p>{{ $report['employee']['name'] }}</p>
        </div>

        <table style="width:100%; margin-bottom: 20px;">
            <tr>
                <td>
                    <span class="meta-label">Período</span><br>
                    <span class="meta-value">{{ $report['period']['start'] }} — {{ $report['period']['end'] }}</span>
                </td>
                <td>
                    <span class="meta-label">Departamento</span><br>
                    <span class="meta-value">{{ $report['employee']['department'] ?? 'N/A' }}</span>
                </td>
                <td>
                    <span class="meta-label">Cargo</span><br>
                    <span class="meta-value">{{ $report['employee']['position'] ?? 'N/A' }}</span>
                </td>
                <td>
                    <span class="meta-label">Tarifa/hora</span><br>
                    <span class="meta-value">${{ number_format($report['employee']['hourly_rate'], 0, ',', '.') }}</span>
                </td>
            </tr>
        </table>

        {{-- KPIs --}}
        <table class="kpi-grid">
            <tr>
                <td>
                    <div class="kpi-label">Días trabajados</div>
                    <div class="kpi-value">{{ $report['totals']['days_worked'] }}</div>
                </td>
                <td>
                    <div class="kpi-label">Horas netas</div>
                    <div class="kpi-value">{{ $report['totals']['net_hours'] }}h</div>
                </td>
                <td>
                    <div class="kpi-label">Horas brutas</div>
                    <div class="kpi-value">{{ $report['totals']['gross_hours'] }}h</div>
                </td>
                <td>
                    <div class="kpi-label">Costo total</div>
                    <div class="kpi-value">${{ number_format($report['cost_summary']['total'], 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>

        {{-- Desglose de horas --}}
        <div class="section">
            <div class="section-title">Desglose de Horas</div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Tipo de hora</th>
                        <th class="text-right">Horas</th>
                        <th class="text-right">Recargo %</th>
                        <th class="text-right">Costo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Ordinarias</td>
                        <td class="text-right">{{ $report['totals']['regular_hours'] }}</td>
                        <td class="text-right">0%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['regular'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Nocturnas</td>
                        <td class="text-right">{{ $report['totals']['night_hours'] }}</td>
                        <td class="text-right">35%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['night'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Extras</td>
                        <td class="text-right">{{ $report['totals']['overtime_hours'] }}</td>
                        <td class="text-right">25%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['overtime'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Dom/Festivas</td>
                        <td class="text-right">{{ $report['totals']['sunday_holiday_hours'] }}</td>
                        <td class="text-right">75%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['sunday_holiday'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td class="text-right">{{ $report['totals']['net_hours'] }}</td>
                        <td></td>
                        <td class="text-right">${{ number_format($report['cost_summary']['total'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Pausas --}}
        @if(!empty($report['breaks_by_type']))
        <div class="section">
            <div class="section-title">Desglose de Pausas</div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th class="text-right">Minutos</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Pagada</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['breaks_by_type'] as $bt)
                    <tr>
                        <td>{{ $bt['name'] }}</td>
                        <td class="text-right">{{ $bt['total_minutes'] }}</td>
                        <td class="text-right">{{ $bt['count'] }}</td>
                        <td class="text-right">{{ $bt['is_paid'] ? 'Sí' : 'No' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Detalle diario --}}
        <div class="section">
            <div class="section-title">Detalle Diario</div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th class="text-right">Brutas</th>
                        <th class="text-right">Pausas</th>
                        <th class="text-right">Netas</th>
                        <th class="text-right">Ordinarias</th>
                        <th class="text-right">Nocturnas</th>
                        <th class="text-right">Extras</th>
                        <th class="text-right">Dom/Fest.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['daily_breakdown'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td class="text-right">{{ $day['gross_hours'] }}</td>
                        <td class="text-right">{{ $day['break_hours'] }}</td>
                        <td class="text-right">{{ $day['net_hours'] }}</td>
                        <td class="text-right">{{ $day['regular_hours'] }}</td>
                        <td class="text-right">{{ $day['night_hours'] }}</td>
                        <td class="text-right">{{ $day['overtime_hours'] }}</td>
                        <td class="text-right">{{ $day['sunday_holiday_hours'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer">
            Generado el {{ now()->setTimezone('America/Bogota')->format('d/m/Y H:i') }} — MangoApp
        </div>
    </div>
</body>
</html>
