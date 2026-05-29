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

        $payOvertime = $c['pay_overtime'] ?? true;
        $overtimeCost = fn (float|int $value) => $payOvertime ? $value : 'Compensado con tiempo';

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
            ['Dom/Festivas', $t['sunday_holiday_hours']],
            ['Noc. dominicales', $t['night_sunday_hours']],
            ['Extra diurnas', $t['overtime_day_hours']],
            ['Extra nocturnas', $t['overtime_night_hours']],
            ['Extra dom diurnas', $t['overtime_day_sunday_hours']],
            ['Extra dom nocturnas', $t['overtime_night_sunday_hours']],
            [],
            ['--- Costos ---', $payOvertime ? '' : 'Horas extra compensadas con tiempo (pago $0)'],
            ['Salario base (total)', $c['base'] ?? 0],
            ['Costo ordinarias', $c['regular']],
            ['Costo nocturnas', $c['night']],
            ['Costo dom/festivas', $c['sunday_holiday']],
            ['Costo noc. dominicales', $c['night_sunday']],
            ['Costo extra diurnas', $overtimeCost($c['overtime_day'])],
            ['Costo extra nocturnas', $overtimeCost($c['overtime_night'])],
            ['Costo extra dom diurnas', $overtimeCost($c['overtime_day_sunday'])],
            ['Costo extra dom nocturnas', $overtimeCost($c['overtime_night_sunday'])],
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
            'Tipo salario',
            'Valor hora',
            'Salario base',
            'Días',
            'Horas brutas',
            'Horas netas',
            'Ordinarias',
            'Nocturnas',
            'Dom/Festivas',
            'Noc. Dom.',
            'Extra Diurnas',
            'Extra Noc.',
            'Extra Dom Diurnas',
            'Extra Dom Noc.',
            'Costo',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $emp) => [
            $emp['name'],
            $emp['department'] ?? 'N/A',
            ($emp['salary_type'] ?? 'hourly') === 'monthly' ? 'Mensual' : 'Por hora',
            $emp['hourly_rate'],
            $emp['base'] ?? 0,
            $emp['days_worked'],
            $emp['gross_hours'],
            $emp['net_hours'],
            $emp['regular_hours'],
            $emp['night_hours'],
            $emp['sunday_holiday_hours'],
            $emp['night_sunday_hours'],
            $emp['overtime_day_hours'],
            $emp['overtime_night_hours'],
            $emp['overtime_day_sunday_hours'],
            $emp['overtime_night_sunday_hours'],
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
