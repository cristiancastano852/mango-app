<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\SurchargeRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateCompanyReport
{
    public function __construct(
        private CalculateReportCosts $costCalculator,
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
    public function execute(int $companyId, CarbonInterface $startDate, CarbonInterface $endDate, ?int $departmentId = null): array
    {
        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->firstOrFail();

        $employeeBreakdown = $this->getEmployeeBreakdown($companyId, $startDate, $endDate, $departmentId);
        $dailyAttendance = $this->getDailyAttendance($companyId, $startDate, $endDate, $departmentId);

        // Calcular totales sumando los datos de empleados (ya agregados por BD)
        $totals = $this->sumEmployeeTotals($employeeBreakdown);

        // Calcular costos por empleado y acumular total
        $totalCost = [
            'regular' => 0.0,
            'night' => 0.0,
            'overtime' => 0.0,
            'sunday_holiday' => 0.0,
            'total' => 0.0,
        ];

        $employeesWithCosts = $employeeBreakdown->map(function ($emp) use ($rules, &$totalCost) {
            $cost = $this->costCalculator->execute(
                (float) $emp->hourly_rate,
                [
                    'regular_hours' => (float) $emp->total_regular,
                    'night_hours' => (float) $emp->total_night,
                    'overtime_hours' => (float) $emp->total_overtime,
                    'sunday_holiday_hours' => (float) $emp->total_sunday_holiday,
                ],
                $rules,
            );

            $totalCost['regular'] += $cost['regular'];
            $totalCost['night'] += $cost['night'];
            $totalCost['overtime'] += $cost['overtime'];
            $totalCost['sunday_holiday'] += $cost['sunday_holiday'];
            $totalCost['total'] += $cost['total'];

            return [
                'employee_id' => $emp->employee_id,
                'name' => $emp->employee_name,
                'department' => $emp->department_name,
                'hourly_rate' => (float) $emp->hourly_rate,
                'days_worked' => (int) $emp->days_worked,
                'gross_hours' => round((float) $emp->total_gross, 2),
                'net_hours' => round((float) $emp->total_net, 2),
                'regular_hours' => round((float) $emp->total_regular, 2),
                'night_hours' => round((float) $emp->total_night, 2),
                'overtime_hours' => round((float) $emp->total_overtime, 2),
                'sunday_holiday_hours' => round((float) $emp->total_sunday_holiday, 2),
                'cost' => $cost['total'],
            ];
        })->toArray();

        // Redondear costos totales
        $totalCost = array_map(fn ($v) => round($v, 2), $totalCost);

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
            ->whereNotNull('time_entries.clock_out')
            ->when($departmentId, fn ($q) => $q->where('employees.department_id', $departmentId))
            ->groupBy('employees.id', 'users.name', 'employees.hourly_rate', 'departments.name')
            ->selectRaw('
                employees.id as employee_id,
                users.name as employee_name,
                departments.name as department_name,
                employees.hourly_rate,
                COUNT(*) as days_worked,
                COALESCE(SUM(time_entries.gross_hours), 0) as total_gross,
                COALESCE(SUM(time_entries.break_hours), 0) as total_breaks,
                COALESCE(SUM(time_entries.net_hours), 0) as total_net,
                COALESCE(SUM(time_entries.regular_hours), 0) as total_regular,
                COALESCE(SUM(time_entries.overtime_hours), 0) as total_overtime,
                COALESCE(SUM(time_entries.night_hours), 0) as total_night,
                COALESCE(SUM(time_entries.sunday_holiday_hours), 0) as total_sunday_holiday
            ')
            ->orderByDesc('total_net')
            ->get();
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
            'overtime_hours' => round((float) $employees->sum('total_overtime'), 2),
            'night_hours' => round((float) $employees->sum('total_night'), 2),
            'sunday_holiday_hours' => round((float) $employees->sum('total_sunday_holiday'), 2),
        ];
    }
}
