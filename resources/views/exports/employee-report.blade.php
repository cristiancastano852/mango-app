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

        @if (($report['overtime_settlement']['mode'] ?? 'daily') === 'weekly')
            <p style="font-size: 10px; color: #92400e; background: #fffbeb; border: 1px solid #fcd34d; padding: 8px; border-radius: 4px; margin-bottom: 16px;">
                @if (($report['overtime_settlement']['start'] ?? null) && ($report['overtime_settlement']['end'] ?? null))
                    Horas extra liquidadas de las semanas {{ $report['overtime_settlement']['start'] }} a {{ $report['overtime_settlement']['end'] }} (semanas con domingo dentro del periodo).
                @else
                    Este periodo no cierra ninguna semana completa: las horas extra se liquidan en el próximo periodo.
                @endif
                @if ($report['overtime_settlement']['deferred'] ?? false)
                    La semana en curso al cierre se liquidará en el próximo periodo.
                @endif
            </p>
        @endif

        @if (($report['night_settlement']['mode'] ?? 'immediate') === 'deferred')
            <p style="font-size: 10px; color: #3730a3; background: #eef2ff; border: 1px solid #a5b4fc; padding: 8px; border-radius: 4px; margin-bottom: 16px;">
                Recargo nocturno liquidado del rango {{ $report['night_settlement']['start'] }} a {{ $report['night_settlement']['end'] }}. El recargo nocturno del día de corte se paga en la siguiente quincena.
            </p>
        @endif

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
        @php
            $surcharges = collect($report['cost_summary']['details'])->keyBy('type');
            $payOvertime = $report['cost_summary']['pay_overtime'] ?? true;
            $cs = $report['cost_summary'];
            // Horas de presentación: el recargo premium colapsado se funde en su base (night/overtime)
            // y el renglón premium queda en 0h, igual que el costo.
            $hours = fn (string $type, $fallback) => $surcharges[$type]['hours'] ?? $fallback;
            // Flag de pago por tipo de recargo premium (los demás tipos no se ocultan).
            $premiumPayFlag = [
                'dominical' => $cs['pay_dominical'] ?? true,
                'night_dominical' => $cs['pay_night_dominical'] ?? true,
                'night_holiday' => $cs['pay_night_holiday'] ?? true,
                'overtime_day_dominical' => $cs['pay_overtime_dominical'] ?? true,
                'overtime_night_dominical' => ($cs['pay_overtime_dominical'] ?? true) && ($cs['pay_overtime_night'] ?? true),
                'overtime_day_holiday' => $cs['pay_overtime_holiday'] ?? true,
                'overtime_night_holiday' => ($cs['pay_overtime_holiday'] ?? true) && ($cs['pay_overtime_night'] ?? true),
                'overtime_night' => $cs['pay_overtime_night'] ?? true,
            ];
            // Oculta la fila premium cuando su toggle está OFF y no representa pago real (0h y $0).
            $showRow = function (string $type) use ($premiumPayFlag, $cs, $surcharges) {
                $flag = $premiumPayFlag[$type] ?? true;
                if ($flag) { return true; }
                return ($cs[$type] ?? 0) != 0 || ($surcharges[$type]['hours'] ?? 0) != 0;
            };
            // En modo por día, mostrar días en vez de horas para dominical/festivo.
            $dominicalByDay = ($cs['dominical_mode'] ?? 'hour') === 'day';
            $holidayByDay = ($cs['holiday_mode'] ?? 'hour') === 'day';
            $daysLabel = fn (int $n) => $n.' '.($n === 1 ? 'día' : 'días');
        @endphp
        <div class="section">
            <div class="section-title">Desglose de Horas</div>
            @unless($payOvertime)
                <p style="font-size: 9px; color: #b45309; margin-bottom: 8px;">
                    Las horas extra de este período se compensan con tiempo: se muestran las horas trabajadas, pero su pago es $0.
                </p>
            @endunless
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
                    @if (($report['cost_summary']['salary_type'] ?? 'hourly') === 'monthly')
                    <tr>
                        <td>Salario base del periodo</td>
                        <td class="text-right">&mdash;</td>
                        <td class="text-right">&mdash;</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['base'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if (($report['cost_summary']['transport_allowance'] ?? 0) > 0)
                    <tr>
                        <td>Auxilio de transporte</td>
                        <td class="text-right">&mdash;</td>
                        <td class="text-right">&mdash;</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['transport_allowance'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Ordinarias diurnas</td>
                        <td class="text-right">{{ $hours('regular', $report['totals']['regular_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['regular']['surcharge'] ?? 0 }}%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['regular'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Recargo nocturno</td>
                        <td class="text-right">{{ $hours('night', $report['totals']['night_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['night']['surcharge'] ?? 35 }}%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['night'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Extra diurna</td>
                        <td class="text-right">{{ $hours('overtime_day', $report['totals']['overtime_day_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['overtime_day']['surcharge'] ?? 25 }}%</td>
                        <td class="text-right">
                            ${{ number_format($report['cost_summary']['overtime_day'], 0, ',', '.') }}
                            @unless($payOvertime)<span style="color:#b45309;"> (Compensado)</span>@endunless
                        </td>
                    </tr>
                    @if($showRow('overtime_night'))
                    <tr>
                        <td>Extra nocturna</td>
                        <td class="text-right">{{ $hours('overtime_night', $report['totals']['overtime_night_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['overtime_night']['surcharge'] ?? 75 }}%</td>
                        <td class="text-right">
                            ${{ number_format($report['cost_summary']['overtime_night'], 0, ',', '.') }}
                            @unless($payOvertime)<span style="color:#b45309;"> (Compensado)</span>@endunless
                        </td>
                    </tr>
                    @endif
                    @if($showRow('dominical'))
                    <tr>
                        <td>Recargo dominical</td>
                        <td class="text-right">{{ $dominicalByDay ? $daysLabel((int) ($cs['dominical_paid_days'] ?? 0)) : $hours('dominical', $report['totals']['dominical_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['dominical']['surcharge'] ?? 75 }}%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['dominical'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($showRow('night_dominical'))
                    <tr>
                        <td>Recargo nocturno dominical</td>
                        <td class="text-right">{{ $hours('night_dominical', $report['totals']['night_dominical_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['night_dominical']['surcharge'] ?? 110 }}%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['night_dominical'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Recargo festivo</td>
                        <td class="text-right">{{ $holidayByDay ? $daysLabel((int) ($cs['holiday_worked_days'] ?? 0)) : $hours('holiday', $report['totals']['holiday_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['holiday']['surcharge'] ?? 75 }}%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['holiday'], 0, ',', '.') }}</td>
                    </tr>
                    @if($showRow('night_holiday'))
                    <tr>
                        <td>Recargo nocturno festivo</td>
                        <td class="text-right">{{ $hours('night_holiday', $report['totals']['night_holiday_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['night_holiday']['surcharge'] ?? 110 }}%</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['night_holiday'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($showRow('overtime_day_dominical'))
                    <tr>
                        <td>Extra diurna dominical</td>
                        <td class="text-right">{{ $hours('overtime_day_dominical', $report['totals']['overtime_day_dominical_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['overtime_day_dominical']['surcharge'] ?? 100 }}%</td>
                        <td class="text-right">
                            ${{ number_format($report['cost_summary']['overtime_day_dominical'], 0, ',', '.') }}
                            @unless($payOvertime)<span style="color:#b45309;"> (Compensado)</span>@endunless
                        </td>
                    </tr>
                    @endif
                    @if($showRow('overtime_night_dominical'))
                    <tr>
                        <td>Extra nocturna dominical</td>
                        <td class="text-right">{{ $hours('overtime_night_dominical', $report['totals']['overtime_night_dominical_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['overtime_night_dominical']['surcharge'] ?? 150 }}%</td>
                        <td class="text-right">
                            ${{ number_format($report['cost_summary']['overtime_night_dominical'], 0, ',', '.') }}
                            @unless($payOvertime)<span style="color:#b45309;"> (Compensado)</span>@endunless
                        </td>
                    </tr>
                    @endif
                    @if($showRow('overtime_day_holiday'))
                    <tr>
                        <td>Extra diurna festivo</td>
                        <td class="text-right">{{ $hours('overtime_day_holiday', $report['totals']['overtime_day_holiday_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['overtime_day_holiday']['surcharge'] ?? 100 }}%</td>
                        <td class="text-right">
                            ${{ number_format($report['cost_summary']['overtime_day_holiday'], 0, ',', '.') }}
                            @unless($payOvertime)<span style="color:#b45309;"> (Compensado)</span>@endunless
                        </td>
                    </tr>
                    @endif
                    @if($showRow('overtime_night_holiday'))
                    <tr>
                        <td>Extra nocturna festivo</td>
                        <td class="text-right">{{ $hours('overtime_night_holiday', $report['totals']['overtime_night_holiday_hours']) }}</td>
                        <td class="text-right">{{ $surcharges['overtime_night_holiday']['surcharge'] ?? 150 }}%</td>
                        <td class="text-right">
                            ${{ number_format($report['cost_summary']['overtime_night_holiday'], 0, ',', '.') }}
                            @unless($payOvertime)<span style="color:#b45309;"> (Compensado)</span>@endunless
                        </td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td>TOTAL DEVENGADO</td>
                        <td class="text-right">{{ $report['totals']['net_hours'] }}</td>
                        <td></td>
                        <td class="text-right">${{ number_format($report['cost_summary']['total'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="3">Salud ({{ $report['cost_summary']['health_rate'] }}%)</td>
                        <td class="text-right">-${{ number_format($report['cost_summary']['health_deduction'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="3">Pensión ({{ $report['cost_summary']['pension_rate'] }}%)</td>
                        <td class="text-right">-${{ number_format($report['cost_summary']['pension_deduction'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="@if(empty($report['adjustments'])) total-row @endif">
                        <td colspan="3">NETO A PAGAR</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['net_pay'], 0, ',', '.') }}</td>
                    </tr>
                    @forelse($report['adjustments'] ?? [] as $adjustment)
                    <tr>
                        <td colspan="3">{{ $adjustment['type'] === 'Bonus' ? 'Bonificación' : 'Deducción' }}@if(!empty($adjustment['concept'])): {{ $adjustment['concept'] }}@endif</td>
                        <td class="text-right">{{ $adjustment['type'] === 'Bonus' ? '+' : '-' }}${{ number_format($adjustment['amount'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    @endforelse
                    @unless(empty($report['adjustments']))
                    <tr class="total-row">
                        <td colspan="3">TOTAL A PAGAR</td>
                        <td class="text-right">${{ number_format($report['cost_summary']['final_pay'], 0, ',', '.') }}</td>
                    </tr>
                    @endunless
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
                        <th>Horario</th>
                        <th class="text-right">Brutas</th>
                        <th class="text-right">Pausas</th>
                        <th class="text-right">Netas</th>
                        <th class="text-right">Ordinarias</th>
                        <th class="text-right">Nocturnas</th>
                        <th class="text-right">Dom/Fest.</th>
                        <th class="text-right">Extra Diurnas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['daily_breakdown'] as $day)
                    @continue(($day['status'] ?? null) === 'in_progress')
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>
                            {{ isset($day['clock_in']) ? \Carbon\Carbon::parse($day['clock_in'])->format('g:i A') : '—' }}
                            –
                            {{ isset($day['clock_out']) ? \Carbon\Carbon::parse($day['clock_out'])->format('g:i A') : '—' }}
                        </td>
                        <td class="text-right">{{ $day['gross_hours'] }}</td>
                        <td class="text-right">{{ $day['break_hours'] }}</td>
                        <td class="text-right">{{ $day['net_hours'] }}</td>
                        <td class="text-right">{{ $day['regular_hours'] }}</td>
                        <td class="text-right">{{ $day['night_hours'] }}</td>
                        <td class="text-right">{{ $day['dominical_hours'] + $day['holiday_hours'] }}</td>
                        <td class="text-right">{{ $day['overtime_day_hours'] }}</td>
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
