<?php

namespace App\Exports;

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

        $rows = [
            ['Días trabajados', $totals['days_worked'], '', ''],
            ['Horas brutas', $totals['gross_hours'], '', ''],
            ['Horas en pausas', $totals['break_hours'], '', ''],
            ['Horas netas', $totals['net_hours'], '', ''],
            [],
            ['Horas ordinarias', $totals['regular_hours'], ($surcharges['regular']['surcharge'] ?? 0).'%', $costs['regular']],
            ['Horas nocturnas', $totals['night_hours'], ($surcharges['night']['surcharge'] ?? 35).'%', $costs['night']],
            ['Horas extras', $totals['overtime_hours'], ($surcharges['overtime']['surcharge'] ?? 25).'%', $costs['overtime']],
            ['Horas dom/festivas', $totals['sunday_holiday_hours'], ($surcharges['sunday_holiday']['surcharge'] ?? 75).'%', $costs['sunday_holiday']],
            [],
            ['TOTAL', $totals['net_hours'], '', $costs['total']],
        ];

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
            'Horas brutas',
            'Pausas',
            'Horas netas',
            'Ordinarias',
            'Nocturnas',
            'Extras',
            'Dom/Festivas',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $day) => [
            $day['date'],
            $day['gross_hours'],
            $day['break_hours'],
            $day['net_hours'],
            $day['regular_hours'],
            $day['night_hours'],
            $day['overtime_hours'],
            $day['sunday_holiday_hours'],
        ], $this->report['daily_breakdown']);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
