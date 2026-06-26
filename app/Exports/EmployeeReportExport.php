<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeReportExport implements WithMultipleSheets
{
    public function __construct(private array $report) {}

    public function sheets(): array
    {
        return [
            new EmployeeReportSummarySheet($this->report),
            new EmployeeReportDailySheet($this->report),
        ];
    }
}

class EmployeeReportSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Resumen';
    }

    public function headings(): array
    {
        return [
            ['Reporte Individual - '.$this->report['employee']['name']],
            ['Período: '.$this->report['period']['start'].' a '.$this->report['period']['end']],
            ['Departamento: '.($this->report['employee']['department'] ?? 'N/A').' | Cargo: '.($this->report['employee']['position'] ?? 'N/A')],
            [],
            ['Concepto', 'Horas', 'Recargo %', 'Costo'],
        ];
    }

    public function array(): array
    {
        $totals = $this->report['totals'];
        $costs = $this->report['cost_summary'];

        $surcharges = collect($costs['details'])->keyBy('type');
        $payOvertime = $costs['pay_overtime'] ?? true;
        $overtimeCost = fn (float|int $value) => $payOvertime ? $value : 'Compensado con tiempo';
        $isMonthly = ($costs['salary_type'] ?? 'hourly') === 'monthly';

        $rows = [
            ['Días trabajados', $totals['days_worked'], '', ''],
            ['Horas brutas', $totals['gross_hours'], '', ''],
            ['Horas en pausas', $totals['break_hours'], '', ''],
            ['Exceso pausas pagadas (descontado)', $totals['paid_break_overage_hours'] ?? 0, '', ''],
            ['Horas netas', $totals['net_hours'], '', ''],
            [],
        ];

        if ($isMonthly) {
            $rows[] = ['Salario base del periodo', '', '', $costs['base'] ?? 0];
        }

        if (($costs['transport_allowance'] ?? 0) > 0) {
            $rows[] = ['Auxilio de transporte', '', '', $costs['transport_allowance']];
        }

        // Las horas vienen de details[] (horas de presentación): el recargo premium colapsado
        // se funde en su base (night/overtime) y el renglón premium queda en 0h, igual que el costo.
        $hours = fn (string $type, float $fallback): float => (float) ($surcharges[$type]['hours'] ?? $fallback);

        // Flag de pago por recargo premium; los tipos ausentes nunca se ocultan.
        $premiumPayFlag = [
            'dominical' => $costs['pay_dominical'] ?? true,
            'night_dominical' => $costs['pay_night_dominical'] ?? true,
            'night_holiday' => $costs['pay_night_holiday'] ?? true,
            'overtime_day_dominical' => $costs['pay_overtime_dominical'] ?? true,
            'overtime_night_dominical' => $costs['pay_overtime_dominical'] ?? true,
            'overtime_day_holiday' => $costs['pay_overtime_holiday'] ?? true,
            'overtime_night_holiday' => $costs['pay_overtime_holiday'] ?? true,
        ];
        // Oculta la fila premium cuando su toggle está OFF y no representa pago real (0h y $0).
        $showRow = function (string $type) use ($premiumPayFlag, $costs, $surcharges): bool {
            $flag = $premiumPayFlag[$type] ?? true;
            if ($flag) {
                return true;
            }

            return (float) ($costs[$type] ?? 0) !== 0.0 || (float) ($surcharges[$type]['hours'] ?? 0) !== 0.0;
        };
        // En modo por día, mostrar días en lugar de horas para dominical/festivo.
        $dominicalByDay = ($costs['dominical_mode'] ?? 'hour') === 'day';
        $holidayByDay = ($costs['holiday_mode'] ?? 'hour') === 'day';
        $daysLabel = fn (int $n): string => $n.' '.($n === 1 ? 'día' : 'días');
        $dominicalQty = $dominicalByDay ? $daysLabel((int) ($costs['dominical_paid_days'] ?? 0)) : $hours('dominical', $totals['dominical_hours']);
        $holidayQty = $holidayByDay ? $daysLabel((int) ($costs['holiday_worked_days'] ?? 0)) : $hours('holiday', $totals['holiday_hours']);

        $rows[] = ['Horas ordinarias', $hours('regular', $totals['regular_hours']), ($surcharges['regular']['surcharge'] ?? 0).'%', $costs['regular']];
        $rows[] = ['Horas nocturnas', $hours('night', $totals['night_hours']), ($surcharges['night']['surcharge'] ?? 35).'%', $costs['night']];
        if ($showRow('dominical')) {
            $rows[] = ['Horas dominicales', $dominicalQty, ($surcharges['dominical']['surcharge'] ?? 75).'%', $costs['dominical']];
        }
        if ($showRow('night_dominical')) {
            $rows[] = ['Horas nocturnas dominicales', $hours('night_dominical', $totals['night_dominical_hours']), ($surcharges['night_dominical']['surcharge'] ?? 110).'%', $costs['night_dominical']];
        }
        $rows[] = ['Horas festivas', $holidayQty, ($surcharges['holiday']['surcharge'] ?? 75).'%', $costs['holiday']];
        if ($showRow('night_holiday')) {
            $rows[] = ['Horas nocturnas festivas', $hours('night_holiday', $totals['night_holiday_hours']), ($surcharges['night_holiday']['surcharge'] ?? 110).'%', $costs['night_holiday']];
        }
        $rows[] = ['Horas extra diurnas', $hours('overtime_day', $totals['overtime_day_hours']), ($surcharges['overtime_day']['surcharge'] ?? 25).'%', $overtimeCost($costs['overtime_day'])];
        $rows[] = ['Horas extra nocturnas', $hours('overtime_night', $totals['overtime_night_hours']), ($surcharges['overtime_night']['surcharge'] ?? 75).'%', $overtimeCost($costs['overtime_night'])];
        if ($showRow('overtime_day_dominical')) {
            $rows[] = ['Horas extra dominicales diurnas', $hours('overtime_day_dominical', $totals['overtime_day_dominical_hours']), ($surcharges['overtime_day_dominical']['surcharge'] ?? 100).'%', $overtimeCost($costs['overtime_day_dominical'])];
        }
        if ($showRow('overtime_night_dominical')) {
            $rows[] = ['Horas extra dominicales nocturnas', $hours('overtime_night_dominical', $totals['overtime_night_dominical_hours']), ($surcharges['overtime_night_dominical']['surcharge'] ?? 150).'%', $overtimeCost($costs['overtime_night_dominical'])];
        }
        if ($showRow('overtime_day_holiday')) {
            $rows[] = ['Horas extra festivas diurnas', $hours('overtime_day_holiday', $totals['overtime_day_holiday_hours']), ($surcharges['overtime_day_holiday']['surcharge'] ?? 100).'%', $overtimeCost($costs['overtime_day_holiday'])];
        }
        if ($showRow('overtime_night_holiday')) {
            $rows[] = ['Horas extra festivas nocturnas', $hours('overtime_night_holiday', $totals['overtime_night_holiday_hours']), ($surcharges['overtime_night_holiday']['surcharge'] ?? 150).'%', $overtimeCost($costs['overtime_night_holiday'])];
        }

        $rows = array_merge($rows, [
            [],
            ['TOTAL DEVENGADO', $totals['net_hours'], '', $costs['total']],
            ['Salud ('.$costs['health_rate'].'%)', '', '', -$costs['health_deduction']],
            ['Pensión ('.$costs['pension_rate'].'%)', '', '', -$costs['pension_deduction']],
            ['NETO A PAGAR', '', '', $costs['net_pay']],
        ]);

        if (! empty($this->report['breaks_by_type'])) {
            $rows[] = [];
            $rows[] = ['--- Pausas ---', 'Minutos', 'Cantidad', 'Pagada'];
            foreach ($this->report['breaks_by_type'] as $breakType) {
                $rows[] = [
                    $breakType['name'],
                    $breakType['total_minutes'],
                    $breakType['count'],
                    $breakType['is_paid'] ? 'Sí' : 'No',
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true]],
            3 => ['font' => ['italic' => true]],
            5 => ['font' => ['bold' => true]],
        ];
    }
}

class EmployeeReportDailySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Detalle Diario';
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Entrada',
            'Salida',
            'Horas brutas',
            'Pausas',
            'Horas netas',
            'Ordinarias',
            'Nocturnas',
            'Dominicales',
            'Noc. Dominicales',
            'Festivas',
            'Noc. Festivas',
            'Extra Diurnas',
            'Extra Nocturnas',
            'Extra Dom Diurnas',
            'Extra Dom Nocturnas',
            'Extra Fest Diurnas',
            'Extra Fest Nocturnas',
        ];
    }

    public function array(): array
    {
        $finishedDays = array_filter(
            $this->report['daily_breakdown'],
            fn (array $day) => ($day['status'] ?? null) !== 'in_progress',
        );

        return array_map(fn (array $day) => [
            $day['date'],
            isset($day['clock_in']) ? Carbon::parse($day['clock_in'])->format('g:i A') : '',
            isset($day['clock_out']) ? Carbon::parse($day['clock_out'])->format('g:i A') : '',
            $day['gross_hours'],
            $day['break_hours'],
            $day['net_hours'],
            $day['regular_hours'],
            $day['night_hours'],
            $day['dominical_hours'],
            $day['night_dominical_hours'],
            $day['holiday_hours'],
            $day['night_holiday_hours'],
            $day['overtime_day_hours'],
            $day['overtime_night_hours'],
            $day['overtime_day_dominical_hours'],
            $day['overtime_night_dominical_hours'],
            $day['overtime_day_holiday_hours'],
            $day['overtime_night_holiday_hours'],
        ], array_values($finishedDays));
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
