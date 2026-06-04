<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\SurchargeRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateCompanyReport
{
    public function __construct(
        private CalculateReportCosts $costCalculator,
        private CalculatePeriodBaseSalary $baseSalaryCalculator,
    ) {}

    /**
     * Genera el reporte general de la empresa para un rango de fechas.
     *
     * Toda la agregación se hace a nivel de BD:
     * - Una query para totales generales
     * - Una query para desglose por empleado (con JOIN, sin N+1)
     * - Una query para tendencia diaria de asistencia
     *
     * @return array{
     *     totals: array,
     *     employees: array,
     *     daily_attendance: array,
     *     cost_summary: array,
     *     period: array
     * }
     */
    public function execute(int $companyId, CarbonInterface $startDate, CarbonInterface $endDate, ?int $departmentId = null, bool $payOvertime = true): array
    {
        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->firstOrFail();

        $employeeBreakdown = $this->getEmployeeBreakdown($companyId, $startDate, $endDate, $departmentId);

        // Los empleados con salario mensual cobran su base aunque no tengan turnos en el periodo.
        // El breakdown anterior parte de time_entries (inner join), así que se agregan aquí los
        // empleados monthly sin registros para que su base entre al total de la empresa.
        $employeeBreakdown = $this->includeMonthlyEmployeesWithoutEntries($companyId, $departmentId, $employeeBreakdown);

        $dailyAttendance = $this->getDailyAttendance($companyId, $startDate, $endDate, $departmentId);

        // Calcular totales sumando los datos de empleados (ya agregados por BD)
        $totals = $this->sumEmployeeTotals($employeeBreakdown);

        // Calcular costos por empleado y acumular total
        $totalCost = [
            'regular' => 0.0,
            'night' => 0.0,
            'sunday_holiday' => 0.0,
            'night_sunday' => 0.0,
            'overtime_day' => 0.0,
            'overtime_night' => 0.0,
            'overtime_day_sunday' => 0.0,
            'overtime_night_sunday' => 0.0,
            'base' => 0.0,
            'total' => 0.0,
        ];

        $employeesWithCosts = $employeeBreakdown->map(function ($emp) use ($rules, $payOvertime, $startDate, $endDate, &$totalCost) {
            $salaryType = $emp->salary_type ?? 'hourly';
            $baseSalary = $salaryType === 'monthly'
                ? $this->baseSalaryCalculator->execute((float) $emp->monthly_base_salary, $startDate, $endDate)
                : 0.0;

            $cost = $this->costCalculator->execute(
                (float) $emp->hourly_rate,
                [
                    'regular_hours' => (float) $emp->total_regular,
                    'night_hours' => (float) $emp->total_night,
                    'sunday_holiday_hours' => (float) $emp->total_sunday_holiday,
                    'night_sunday_hours' => (float) $emp->total_night_sunday,
                    'overtime_day_hours' => (float) $emp->total_overtime_day,
                    'overtime_night_hours' => (float) $emp->total_overtime_night,
                    'overtime_day_sunday_hours' => (float) $emp->total_overtime_day_sunday,
                    'overtime_night_sunday_hours' => (float) $emp->total_overtime_night_sunday,
                ],
                $rules,
                $payOvertime,
                $salaryType,
                $baseSalary,
            );

            $totalCost['regular'] += $cost['regular'];
            $totalCost['night'] += $cost['night'];
            $totalCost['sunday_holiday'] += $cost['sunday_holiday'];
            $totalCost['night_sunday'] += $cost['night_sunday'];
            $totalCost['overtime_day'] += $cost['overtime_day'];
            $totalCost['overtime_night'] += $cost['overtime_night'];
            $totalCost['overtime_day_sunday'] += $cost['overtime_day_sunday'];
            $totalCost['overtime_night_sunday'] += $cost['overtime_night_sunday'];
            $totalCost['base'] += $cost['base'];
            $totalCost['total'] += $cost['total'];

            return [
                'employee_id' => $emp->employee_id,
                'name' => $emp->employee_name,
                'department' => $emp->department_name,
                'hourly_rate' => (float) $emp->hourly_rate,
                'salary_type' => $salaryType,
                'monthly_base_salary' => $emp->monthly_base_salary !== null ? (float) $emp->monthly_base_salary : null,
                'base' => $cost['base'],
                'days_worked' => (int) $emp->days_worked,
                'gross_hours' => round((float) $emp->total_gross, 2),
                'net_hours' => round((float) $emp->total_net, 2),
                'regular_hours' => round((float) $emp->total_regular, 2),
                'night_hours' => round((float) $emp->total_night, 2),
                'sunday_holiday_hours' => round((float) $emp->total_sunday_holiday, 2),
                'night_sunday_hours' => round((float) $emp->total_night_sunday, 2),
                'overtime_day_hours' => round((float) $emp->total_overtime_day, 2),
                'overtime_night_hours' => round((float) $emp->total_overtime_night, 2),
                'overtime_day_sunday_hours' => round((float) $emp->total_overtime_day_sunday, 2),
                'overtime_night_sunday_hours' => round((float) $emp->total_overtime_night_sunday, 2),
                'cost' => $cost['total'],
            ];
        })->toArray();

        // Redondear costos totales
        $totalCost = array_map(fn ($v) => round($v, 2), $totalCost);
        $totalCost['pay_overtime'] = $payOvertime;

        return [
            'totals' => $totals,
            'employees' => $employeesWithCosts,
            'daily_attendance' => $dailyAttendance,
            'cost_summary' => $totalCost,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Desglose por empleado con una sola query (JOIN employees + users + departments).
     * Evita N+1: no se carga ningún modelo Eloquent individual.
     */
    private function getEmployeeBreakdown(
        int $companyId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $departmentId,
    ): \Illuminate\Support\Collection {
        return DB::table('time_entries')
            ->join('employees', 'time_entries.employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->where('time_entries.company_id', $companyId)
            ->whereBetween('time_entries.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNull('time_entries.deleted_at')
            ->whereNotNull('time_entries.clock_out')
            ->when($departmentId, fn ($q) => $q->where('employees.department_id', $departmentId))
            ->groupBy('employees.id', 'users.name', 'employees.hourly_rate', 'employees.salary_type', 'employees.monthly_base_salary', 'departments.name')
            ->selectRaw('
                employees.id as employee_id,
                users.name as employee_name,
                departments.name as department_name,
                employees.hourly_rate,
                employees.salary_type,
                employees.monthly_base_salary,
                COUNT(*) as days_worked,
                COALESCE(SUM(time_entries.gross_hours), 0) as total_gross,
                COALESCE(SUM(time_entries.break_hours), 0) as total_breaks,
                COALESCE(SUM(time_entries.net_hours), 0) as total_net,
                COALESCE(SUM(time_entries.regular_hours), 0) as total_regular,
                COALESCE(SUM(time_entries.night_hours), 0) as total_night,
                COALESCE(SUM(time_entries.sunday_holiday_hours), 0) as total_sunday_holiday,
                COALESCE(SUM(time_entries.night_sunday_hours), 0) as total_night_sunday,
                COALESCE(SUM(time_entries.overtime_day_hours), 0) as total_overtime_day,
                COALESCE(SUM(time_entries.overtime_night_hours), 0) as total_overtime_night,
                COALESCE(SUM(time_entries.overtime_day_sunday_hours), 0) as total_overtime_day_sunday,
                COALESCE(SUM(time_entries.overtime_night_sunday_hours), 0) as total_overtime_night_sunday
            ')
            ->orderByDesc('total_net')
            ->get();
    }

    /**
     * Agrega los empleados con salario mensual que no tienen turnos en el periodo, con totales de
     * horas en cero. Así su salario base prorrateado se incluye en el reporte de empresa, igual que
     * lo haría el reporte individual. Los empleados por hora sin turnos no se agregan (no tienen base).
     */
    private function includeMonthlyEmployeesWithoutEntries(
        int $companyId,
        ?int $departmentId,
        \Illuminate\Support\Collection $breakdown,
    ): \Illuminate\Support\Collection {
        $existingIds = $breakdown->pluck('employee_id')->all();

        $missing = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->where('employees.company_id', $companyId)
            ->where('employees.salary_type', 'monthly')
            ->when($departmentId, fn ($q) => $q->where('employees.department_id', $departmentId))
            ->when($existingIds !== [], fn ($q) => $q->whereNotIn('employees.id', $existingIds))
            ->selectRaw('
                employees.id as employee_id,
                users.name as employee_name,
                departments.name as department_name,
                employees.hourly_rate,
                employees.salary_type,
                employees.monthly_base_salary
            ')
            ->get()
            ->map(fn ($e) => (object) [
                'employee_id' => $e->employee_id,
                'employee_name' => $e->employee_name,
                'department_name' => $e->department_name,
                'hourly_rate' => $e->hourly_rate,
                'salary_type' => $e->salary_type,
                'monthly_base_salary' => $e->monthly_base_salary,
                'days_worked' => 0,
                'total_gross' => 0,
                'total_breaks' => 0,
                'total_net' => 0,
                'total_regular' => 0,
                'total_night' => 0,
                'total_sunday_holiday' => 0,
                'total_night_sunday' => 0,
                'total_overtime_day' => 0,
                'total_overtime_night' => 0,
                'total_overtime_day_sunday' => 0,
                'total_overtime_night_sunday' => 0,
            ]);

        return $breakdown->concat($missing);
    }

    /**
     * Tendencia diaria de asistencia para gráfica.
     * Una query con GROUP BY date.
     *
     * @return array<array{date: string, employees_present: int, total_net_hours: float}>
     */
    private function getDailyAttendance(
        int $companyId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $departmentId,
    ): array {
        $query = DB::table('time_entries')
            ->where('time_entries.company_id', $companyId)
            ->whereBetween('time_entries.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNull('time_entries.deleted_at')
            ->whereNotNull('time_entries.clock_out');

        if ($departmentId) {
            $query->join('employees', 'time_entries.employee_id', '=', 'employees.id')
                ->where('employees.department_id', $departmentId);
        }

        return $query
            ->groupBy('time_entries.date')
            ->selectRaw('
                time_entries.date,
                COUNT(DISTINCT time_entries.employee_id) as employees_present,
                COALESCE(SUM(time_entries.net_hours), 0) as total_net_hours
            ')
            ->orderBy('time_entries.date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'employees_present' => (int) $row->employees_present,
                'total_net_hours' => round((float) $row->total_net_hours, 2),
            ])
            ->toArray();
    }

    /**
     * Suma los totales de todos los empleados (ya agregados).
     */
    private function sumEmployeeTotals(\Illuminate\Support\Collection $employees): array
    {
        return [
            'total_employees' => $employees->count(),
            'total_days_worked' => (int) $employees->sum('days_worked'),
            'gross_hours' => round((float) $employees->sum('total_gross'), 2),
            'break_hours' => round((float) $employees->sum('total_breaks'), 2),
            'net_hours' => round((float) $employees->sum('total_net'), 2),
            'regular_hours' => round((float) $employees->sum('total_regular'), 2),
            'night_hours' => round((float) $employees->sum('total_night'), 2),
            'sunday_holiday_hours' => round((float) $employees->sum('total_sunday_holiday'), 2),
            'night_sunday_hours' => round((float) $employees->sum('total_night_sunday'), 2),
            'overtime_day_hours' => round((float) $employees->sum('total_overtime_day'), 2),
            'overtime_night_hours' => round((float) $employees->sum('total_overtime_night'), 2),
            'overtime_day_sunday_hours' => round((float) $employees->sum('total_overtime_day_sunday'), 2),
            'overtime_night_sunday_hours' => round((float) $employees->sum('total_overtime_night_sunday'), 2),
        ];
    }
}
