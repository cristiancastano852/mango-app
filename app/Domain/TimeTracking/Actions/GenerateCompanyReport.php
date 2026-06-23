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
            'dominical' => 0.0,
            'night_dominical' => 0.0,
            'holiday' => 0.0,
            'night_holiday' => 0.0,
            'overtime_day' => 0.0,
            'overtime_night' => 0.0,
            'overtime_day_dominical' => 0.0,
            'overtime_night_dominical' => 0.0,
            'overtime_day_holiday' => 0.0,
            'overtime_night_holiday' => 0.0,
            'base' => 0.0,
            'transport_allowance' => 0.0,
            'total' => 0.0,
        ];

        // Decisiones dominicales guardadas del periodo, keyed por employee_id (una query, sin N+1).
        $dominicalDecisions = $this->loadDominicalDecisions($companyId, $startDate, $endDate);

        $employeesWithCosts = $employeeBreakdown->map(function ($emp) use ($rules, $payOvertime, $startDate, $endDate, $dominicalDecisions, &$totalCost) {
            $salaryType = $emp->salary_type ?? 'hourly';
            $baseSalary = $salaryType === 'monthly'
                ? $this->baseSalaryCalculator->execute((float) $emp->monthly_base_salary, $startDate, $endDate)
                : 0.0;

            $transportAllowance = $salaryType === 'monthly' && $emp->receives_transport_allowance
                ? $this->baseSalaryCalculator->execute((float) $rules->transport_allowance, $startDate, $endDate)
                : 0.0;

            $cost = $this->costCalculator->execute(
                (float) $emp->hourly_rate,
                [
                    'regular_hours' => (float) $emp->total_regular,
                    'night_hours' => (float) $emp->total_night,
                    'dominical_hours' => (float) $emp->total_dominical,
                    'night_dominical_hours' => (float) $emp->total_night_dominical,
                    'holiday_hours' => (float) $emp->total_holiday,
                    'night_holiday_hours' => (float) $emp->total_night_holiday,
                    'overtime_day_hours' => (float) $emp->total_overtime_day,
                    'overtime_night_hours' => (float) $emp->total_overtime_night,
                    'overtime_day_dominical_hours' => (float) $emp->total_overtime_day_dominical,
                    'overtime_night_dominical_hours' => (float) $emp->total_overtime_night_dominical,
                    'overtime_day_holiday_hours' => (float) $emp->total_overtime_day_holiday,
                    'overtime_night_holiday_hours' => (float) $emp->total_overtime_night_holiday,
                ],
                $rules,
                $payOvertime,
                $salaryType,
                $baseSalary,
                $transportAllowance,
                [
                    'pay' => (bool) $rules->pay_dominical_by_default,
                    'mode' => $emp->dominical_payment_mode ?? 'hour',
                    'day_value' => (float) $emp->dominical_day_value,
                    'payable_count' => $dominicalDecisions[$emp->employee_id] ?? null,
                    'worked_days' => (int) ($emp->dominical_worked_days ?? 0),
                ],
            );

            $totalCost['regular'] += $cost['regular'];
            $totalCost['night'] += $cost['night'];
            $totalCost['dominical'] += $cost['dominical'];
            $totalCost['night_dominical'] += $cost['night_dominical'];
            $totalCost['holiday'] += $cost['holiday'];
            $totalCost['night_holiday'] += $cost['night_holiday'];
            $totalCost['overtime_day'] += $cost['overtime_day'];
            $totalCost['overtime_night'] += $cost['overtime_night'];
            $totalCost['overtime_day_dominical'] += $cost['overtime_day_dominical'];
            $totalCost['overtime_night_dominical'] += $cost['overtime_night_dominical'];
            $totalCost['overtime_day_holiday'] += $cost['overtime_day_holiday'];
            $totalCost['overtime_night_holiday'] += $cost['overtime_night_holiday'];
            $totalCost['base'] += $cost['base'];
            $totalCost['transport_allowance'] += $cost['transport_allowance'];
            $totalCost['total'] += $cost['total'];

            return [
                'employee_id' => $emp->employee_id,
                'name' => $emp->employee_name,
                'department' => $emp->department_name,
                'hourly_rate' => (float) $emp->hourly_rate,
                'salary_type' => $salaryType,
                'monthly_base_salary' => $emp->monthly_base_salary !== null ? (float) $emp->monthly_base_salary : null,
                'base' => $cost['base'],
                'transport_allowance' => $cost['transport_allowance'],
                'days_worked' => (int) $emp->days_worked,
                'gross_hours' => round((float) $emp->total_gross, 2),
                'net_hours' => round((float) $emp->total_net, 2),
                'regular_hours' => round((float) $emp->total_regular, 2),
                'night_hours' => round((float) $emp->total_night, 2),
                'dominical_hours' => round((float) $emp->total_dominical, 2),
                'night_dominical_hours' => round((float) $emp->total_night_dominical, 2),
                'holiday_hours' => round((float) $emp->total_holiday, 2),
                'night_holiday_hours' => round((float) $emp->total_night_holiday, 2),
                'overtime_day_hours' => round((float) $emp->total_overtime_day, 2),
                'overtime_night_hours' => round((float) $emp->total_overtime_night, 2),
                'overtime_day_dominical_hours' => round((float) $emp->total_overtime_day_dominical, 2),
                'overtime_night_dominical_hours' => round((float) $emp->total_overtime_night_dominical, 2),
                'overtime_day_holiday_hours' => round((float) $emp->total_overtime_day_holiday, 2),
                'overtime_night_holiday_hours' => round((float) $emp->total_overtime_night_holiday, 2),
                'dominical_worked_days' => (int) ($emp->dominical_worked_days ?? 0),
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
            ->groupBy('employees.id', 'users.name', 'employees.hourly_rate', 'employees.salary_type', 'employees.monthly_base_salary', 'employees.receives_transport_allowance', 'employees.dominical_payment_mode', 'employees.dominical_day_value', 'departments.name')
            ->selectRaw('
                employees.id as employee_id,
                users.name as employee_name,
                departments.name as department_name,
                employees.hourly_rate,
                employees.salary_type,
                employees.monthly_base_salary,
                employees.receives_transport_allowance,
                employees.dominical_payment_mode,
                employees.dominical_day_value,
                COUNT(*) as days_worked,
                COALESCE(SUM(time_entries.gross_hours), 0) as total_gross,
                COALESCE(SUM(time_entries.break_hours), 0) as total_breaks,
                COALESCE(SUM(time_entries.net_hours), 0) as total_net,
                COALESCE(SUM(time_entries.regular_hours), 0) as total_regular,
                COALESCE(SUM(time_entries.night_hours), 0) as total_night,
                COALESCE(SUM(time_entries.dominical_hours), 0) as total_dominical,
                COALESCE(SUM(time_entries.night_dominical_hours), 0) as total_night_dominical,
                COALESCE(SUM(time_entries.holiday_hours), 0) as total_holiday,
                COALESCE(SUM(time_entries.night_holiday_hours), 0) as total_night_holiday,
                COALESCE(SUM(time_entries.overtime_day_hours), 0) as total_overtime_day,
                COALESCE(SUM(time_entries.overtime_night_hours), 0) as total_overtime_night,
                COALESCE(SUM(time_entries.overtime_day_dominical_hours), 0) as total_overtime_day_dominical,
                COALESCE(SUM(time_entries.overtime_night_dominical_hours), 0) as total_overtime_night_dominical,
                COALESCE(SUM(time_entries.overtime_day_holiday_hours), 0) as total_overtime_day_holiday,
                COALESCE(SUM(time_entries.overtime_night_holiday_hours), 0) as total_overtime_night_holiday,
                COUNT(DISTINCT CASE WHEN (time_entries.dominical_hours > 0 OR time_entries.night_dominical_hours > 0 OR time_entries.overtime_day_dominical_hours > 0 OR time_entries.overtime_night_dominical_hours > 0) THEN time_entries.date END) as dominical_worked_days
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
                employees.monthly_base_salary,
                employees.receives_transport_allowance,
                employees.dominical_payment_mode,
                employees.dominical_day_value
            ')
            ->get()
            ->map(fn ($e) => (object) [
                'employee_id' => $e->employee_id,
                'employee_name' => $e->employee_name,
                'department_name' => $e->department_name,
                'hourly_rate' => $e->hourly_rate,
                'salary_type' => $e->salary_type,
                'monthly_base_salary' => $e->monthly_base_salary,
                'receives_transport_allowance' => $e->receives_transport_allowance,
                'dominical_payment_mode' => $e->dominical_payment_mode,
                'dominical_day_value' => $e->dominical_day_value,
                'days_worked' => 0,
                'total_gross' => 0,
                'total_breaks' => 0,
                'total_net' => 0,
                'total_regular' => 0,
                'total_night' => 0,
                'total_dominical' => 0,
                'total_night_dominical' => 0,
                'total_holiday' => 0,
                'total_night_holiday' => 0,
                'total_overtime_day' => 0,
                'total_overtime_night' => 0,
                'total_overtime_day_dominical' => 0,
                'total_overtime_night_dominical' => 0,
                'total_overtime_day_holiday' => 0,
                'total_overtime_night_holiday' => 0,
                'dominical_worked_days' => 0,
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
            'dominical_hours' => round((float) $employees->sum('total_dominical'), 2),
            'night_dominical_hours' => round((float) $employees->sum('total_night_dominical'), 2),
            'holiday_hours' => round((float) $employees->sum('total_holiday'), 2),
            'night_holiday_hours' => round((float) $employees->sum('total_night_holiday'), 2),
            'overtime_day_hours' => round((float) $employees->sum('total_overtime_day'), 2),
            'overtime_night_hours' => round((float) $employees->sum('total_overtime_night'), 2),
            'overtime_day_dominical_hours' => round((float) $employees->sum('total_overtime_day_dominical'), 2),
            'overtime_night_dominical_hours' => round((float) $employees->sum('total_overtime_night_dominical'), 2),
            'overtime_day_holiday_hours' => round((float) $employees->sum('total_overtime_day_holiday'), 2),
            'overtime_night_holiday_hours' => round((float) $employees->sum('total_overtime_night_holiday'), 2),
        ];
    }

    /**
     * Carga las decisiones dominicales guardadas del periodo, mapeadas por employee_id → payable_count.
     * Una sola query (sin N+1); el reporte de empresa respeta la decisión de cada empleado.
     *
     * @return array<int, int|null>
     */
    private function loadDominicalDecisions(int $companyId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return \App\Domain\Company\Models\DominicalPaymentDecision::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('start_date', $startDate->toDateString())
            ->where('end_date', $endDate->toDateString())
            ->pluck('payable_count', 'employee_id')
            ->all();
    }
}
