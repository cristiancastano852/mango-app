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
        // Horas de presentación (premium colapsado fundido en su base); fallback a horas factuales.
        $hours = fn (string $type, $fallback) => $c['display_hours'][$type] ?? $fallback;

        $costRows = [
            ['Salario base (total)', $c['base'] ?? 0],
        ];

        if (($c['transport_allowance'] ?? 0) > 0) {
            $costRows[] = ['Auxilio de transporte (total)', $c['transport_allowance']];
        }

        $costRows = array_merge($costRows, [
            ['Costo ordinarias', $c['regular']],
            ['Costo nocturnas', $c['night']],
            ['Costo dominicales', $c['dominical']],
            ['Costo noc. dominicales', $c['night_dominical']],
            ['Costo festivas', $c['holiday']],
            ['Costo noc. festivas', $c['night_holiday']],
            ['Costo extra diurnas', $overtimeCost($c['overtime_day'])],
            ['Costo extra nocturnas', $overtimeCost($c['overtime_night'])],
            ['Costo extra dom diurnas', $overtimeCost($c['overtime_day_dominical'])],
            ['Costo extra dom nocturnas', $overtimeCost($c['overtime_night_dominical'])],
            ['Costo extra fest diurnas', $overtimeCost($c['overtime_day_holiday'])],
            ['Costo extra fest nocturnas', $overtimeCost($c['overtime_night_holiday'])],
            ['COSTO TOTAL', $c['total']],
        ]);

        return array_merge([
            ['Empleados', $t['total_employees']],
            ['Días trabajados (total)', $t['total_days_worked']],
            ['Horas brutas', $t['gross_hours']],
            ['Horas en pausas', $t['break_hours']],
            ['Horas netas', $t['net_hours']],
            [],
            ['--- Desglose de horas ---', ''],
            ['Ordinarias', $hours('regular', $t['regular_hours'])],
            ['Nocturnas', $hours('night', $t['night_hours'])],
            ['Dominicales', $hours('dominical', $t['dominical_hours'])],
            ['Noc. dominicales', $hours('night_dominical', $t['night_dominical_hours'])],
            ['Festivas', $hours('holiday', $t['holiday_hours'])],
            ['Noc. festivas', $hours('night_holiday', $t['night_holiday_hours'])],
            ['Extra diurnas', $hours('overtime_day', $t['overtime_day_hours'])],
            ['Extra nocturnas', $hours('overtime_night', $t['overtime_night_hours'])],
            ['Extra dom diurnas', $hours('overtime_day_dominical', $t['overtime_day_dominical_hours'])],
            ['Extra dom nocturnas', $hours('overtime_night_dominical', $t['overtime_night_dominical_hours'])],
            ['Extra fest diurnas', $hours('overtime_day_holiday', $t['overtime_day_holiday_hours'])],
            ['Extra fest nocturnas', $hours('overtime_night_holiday', $t['overtime_night_holiday_hours'])],
            [],
            ['--- Costos ---', $payOvertime ? '' : 'Horas extra compensadas con tiempo (pago $0)'],
        ], $costRows);
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
            'Dominicales',
            'Noc. Dom.',
            'Festivas',
            'Noc. Fest.',
            'Extra Diurnas',
            'Extra Noc.',
            'Extra Dom Diurnas',
            'Extra Dom Noc.',
            'Extra Fest Diurnas',
            'Extra Fest Noc.',
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
            $emp['dominical_hours'],
            $emp['night_dominical_hours'],
            $emp['holiday_hours'],
            $emp['night_holiday_hours'],
            $emp['overtime_day_hours'],
            $emp['overtime_night_hours'],
            $emp['overtime_day_dominical_hours'],
            $emp['overtime_night_dominical_hours'],
            $emp['overtime_day_holiday_hours'],
            $emp['overtime_night_holiday_hours'],
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
