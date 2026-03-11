<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompanyReportExport implements WithMultipleSheets
{
    public function __construct(private array $report) {}

    public function sheets(): array
    {
        return [
            new CompanyReportSummarySheet($this->report),
            new CompanyReportEmployeesSheet($this->report),
            new CompanyReportAttendanceSheet($this->report),
        ];
    }
}

class CompanyReportSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Resumen';
    }

    public function headings(): array
    {
        return [
            ['Reporte General de la Empresa'],
            ['Período: '.$this->report['period']['start'].' a '.$this->report['period']['end']],
            [],
            ['Indicador', 'Valor'],
        ];
    }

    public function array(): array
    {
        $t = $this->report['totals'];
        $c = $this->report['cost_summary'];

        return [
            ['Empleados', $t['total_employees']],
            ['Días trabajados (total)', $t['total_days_worked']],
            ['Horas brutas', $t['gross_hours']],
            ['Horas en pausas', $t['break_hours']],
            ['Horas netas', $t['net_hours']],
            [],
            ['--- Desglose de horas ---', ''],
            ['Ordinarias', $t['regular_hours']],
            ['Nocturnas', $t['night_hours']],
            ['Extras', $t['overtime_hours']],
            ['Dom/Festivas', $t['sunday_holiday_hours']],
            [],
            ['--- Costos ---', ''],
            ['Costo ordinarias', $c['regular']],
            ['Costo nocturnas', $c['night']],
            ['Costo extras', $c['overtime']],
            ['Costo dom/festivas', $c['sunday_holiday']],
            ['COSTO TOTAL', $c['total']],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}

class CompanyReportEmployeesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Empleados';
    }

    public function headings(): array
    {
        return [
            'Empleado',
            'Departamento',
            'Tarifa/hora',
            'Días',
            'Horas brutas',
            'Horas netas',
            'Ordinarias',
            'Nocturnas',
            'Extras',
            'Dom/Festivas',
            'Costo',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $emp) => [
            $emp['name'],
            $emp['department'] ?? 'N/A',
            $emp['hourly_rate'],
            $emp['days_worked'],
            $emp['gross_hours'],
            $emp['net_hours'],
            $emp['regular_hours'],
            $emp['night_hours'],
            $emp['overtime_hours'],
            $emp['sunday_holiday_hours'],
            $emp['cost'],
        ], $this->report['employees']);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class CompanyReportAttendanceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Asistencia Diaria';
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Empleados presentes',
            'Horas netas totales',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $day) => [
            $day['date'],
            $day['employees_present'],
            $day['total_net_hours'],
        ], $this->report['daily_attendance']);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
