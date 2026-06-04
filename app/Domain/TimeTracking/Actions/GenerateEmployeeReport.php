<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Scopes\CompanyScope;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateEmployeeReport
{
    public function __construct(
        private CalculateReportCosts $costCalculator,
        private CalculatePeriodBaseSalary $baseSalaryCalculator,
    ) {}

    /**
     * Genera el reporte individual de un empleado para un rango de fechas.
     *
     * Todas las agregaciones se hacen a nivel de BD para eficiencia.
     * No se itera en PHP sobre registros individuales para sumar.
     *
     * @return array{
     *     employee: array{id: int, name: string, department: ?string, position: ?string, hourly_rate: float, salary_type: string, monthly_base_salary: ?float},
     *     totals: array{days_worked: int, gross_hours: float, break_hours: float, net_hours: float, regular_hours: float, night_hours: float, sunday_holiday_hours: float, night_sunday_hours: float, overtime_day_hours: float, overtime_night_hours: float, overtime_day_sunday_hours: float, overtime_night_sunday_hours: float},
     *     breaks_by_type: array<int, array{name: string, is_paid: bool, icon: string, color: string, total_minutes: float, count: int}>,
     *     daily_breakdown: array,
     *     cost_summary: array,
     *     period: array{start: string, end: string}
     * }
     */
    public function execute(int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate, bool $payOvertime = true): array
    {
        $employee = Employee::withoutGlobalScopes()
            ->with('user', 'department', 'position')
            ->findOrFail($employeeId);

        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $employee->company_id)
            ->firstOrFail();

        $totals = $this->aggregateTotals($employeeId, $startDate, $endDate);
        $breaksByType = $this->aggregateBreaksByType($employeeId, $startDate, $endDate);
        $dailyBreakdown = $this->getDailyBreakdown($employeeId, $startDate, $endDate);

        $salaryType = $employee->salary_type ?? 'hourly';
        $baseSalary = $salaryType === 'monthly'
            ? $this->baseSalaryCalculator->execute((float) $employee->monthly_base_salary, $startDate, $endDate)
            : 0.0;

        $costSummary = $this->costCalculator->execute(
            (float) $employee->hourly_rate,
            [
                'regular_hours' => $totals->total_regular ?? 0,
                'night_hours' => $totals->total_night ?? 0,
                'sunday_holiday_hours' => $totals->total_sunday_holiday ?? 0,
                'night_sunday_hours' => $totals->total_night_sunday ?? 0,
                'overtime_day_hours' => $totals->total_overtime_day ?? 0,
                'overtime_night_hours' => $totals->total_overtime_night ?? 0,
                'overtime_day_sunday_hours' => $totals->total_overtime_day_sunday ?? 0,
                'overtime_night_sunday_hours' => $totals->total_overtime_night_sunday ?? 0,
            ],
            $rules,
            $payOvertime,
            $salaryType,
            $baseSalary,
        );

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'hourly_rate' => (float) $employee->hourly_rate,
                'salary_type' => $salaryType,
                'monthly_base_salary' => $employee->monthly_base_salary !== null ? (float) $employee->monthly_base_salary : null,
            ],
            'totals' => [
                'days_worked' => (int) ($totals->days_worked ?? 0),
                'gross_hours' => round((float) ($totals->total_gross ?? 0), 2),
                'break_hours' => round((float) ($totals->total_breaks ?? 0), 2),
                'net_hours' => round((float) ($totals->total_net ?? 0), 2),
                'regular_hours' => round((float) ($totals->total_regular ?? 0), 2),
                'night_hours' => round((float) ($totals->total_night ?? 0), 2),
                'sunday_holiday_hours' => round((float) ($totals->total_sunday_holiday ?? 0), 2),
                'night_sunday_hours' => round((float) ($totals->total_night_sunday ?? 0), 2),
                'overtime_day_hours' => round((float) ($totals->total_overtime_day ?? 0), 2),
                'overtime_night_hours' => round((float) ($totals->total_overtime_night ?? 0), 2),
                'overtime_day_sunday_hours' => round((float) ($totals->total_overtime_day_sunday ?? 0), 2),
                'overtime_night_sunday_hours' => round((float) ($totals->total_overtime_night_sunday ?? 0), 2),
            ],
            'breaks_by_type' => $breaksByType,
            'daily_breakdown' => $dailyBreakdown,
            'cost_summary' => $costSummary,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Agrega totales de horas a nivel de BD con una sola query.
     */
    private function aggregateTotals(int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate): object
    {
        return TimeEntry::withoutGlobalScopes([CompanyScope::class])
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('clock_out')
            ->selectRaw('
                COUNT(*) as days_worked,
                COALESCE(SUM(gross_hours), 0) as total_gross,
                COALESCE(SUM(break_hours), 0) as total_breaks,
                COALESCE(SUM(net_hours), 0) as total_net,
                COALESCE(SUM(regular_hours), 0) as total_regular,
                COALESCE(SUM(night_hours), 0) as total_night,
                COALESCE(SUM(sunday_holiday_hours), 0) as total_sunday_holiday,
                COALESCE(SUM(night_sunday_hours), 0) as total_night_sunday,
                COALESCE(SUM(overtime_day_hours), 0) as total_overtime_day,
                COALESCE(SUM(overtime_night_hours), 0) as total_overtime_night,
                COALESCE(SUM(overtime_day_sunday_hours), 0) as total_overtime_day_sunday,
                COALESCE(SUM(overtime_night_sunday_hours), 0) as total_overtime_night_sunday
            ')
            ->first();
    }

    /**
     * Agrega pausas por tipo con join a break_types a nivel de BD.
     *
     * @return array<array{name: string, is_paid: bool, total_minutes: float, count: int}>
     */
    private function aggregateBreaksByType(int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return DB::table('breaks')
            ->join('break_types', 'breaks.break_type_id', '=', 'break_types.id')
            ->join('time_entries', 'breaks.time_entry_id', '=', 'time_entries.id')
            ->where('breaks.employee_id', $employeeId)
            ->whereBetween('time_entries.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNull('time_entries.deleted_at')
            ->whereNotNull('breaks.ended_at')
            ->groupBy('break_types.id', 'break_types.name', 'break_types.is_paid', 'break_types.icon', 'break_types.color')
            ->selectRaw('
                break_types.name,
                break_types.is_paid,
                break_types.icon,
                break_types.color,
                COALESCE(SUM(breaks.duration_minutes), 0) as total_minutes,
                COUNT(*) as count
            ')
            ->orderByDesc('total_minutes')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'is_paid' => (bool) $row->is_paid,
                'icon' => $row->icon,
                'color' => $row->color,
                'total_minutes' => round((float) $row->total_minutes, 0),
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Obtiene el desglose diario para gráficas de tendencia.
     * Una query con GROUP BY date, ordenada cronológicamente.
     *
     * @return array<array{date: string, gross_hours: float, net_hours: float, regular_hours: float, night_hours: float, sunday_holiday_hours: float, night_sunday_hours: float, overtime_day_hours: float, overtime_night_hours: float, overtime_day_sunday_hours: float, overtime_night_sunday_hours: float}>
     */
    private function getDailyBreakdown(int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return TimeEntry::withoutGlobalScopes([CompanyScope::class])
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('clock_out')
            ->groupBy('date')
            ->selectRaw('
                date,
                SUM(gross_hours) as gross_hours,
                SUM(break_hours) as break_hours,
                SUM(net_hours) as net_hours,
                SUM(regular_hours) as regular_hours,
                SUM(night_hours) as night_hours,
                SUM(sunday_holiday_hours) as sunday_holiday_hours,
                SUM(night_sunday_hours) as night_sunday_hours,
                SUM(overtime_day_hours) as overtime_day_hours,
                SUM(overtime_night_hours) as overtime_night_hours,
                SUM(overtime_day_sunday_hours) as overtime_day_sunday_hours,
                SUM(overtime_night_sunday_hours) as overtime_night_sunday_hours
            ')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date instanceof Carbon ? $row->date->toDateString() : (string) $row->date,
                'gross_hours' => round((float) $row->gross_hours, 2),
                'break_hours' => round((float) $row->break_hours, 2),
                'net_hours' => round((float) $row->net_hours, 2),
                'regular_hours' => round((float) $row->regular_hours, 2),
                'night_hours' => round((float) $row->night_hours, 2),
                'sunday_holiday_hours' => round((float) $row->sunday_holiday_hours, 2),
                'night_sunday_hours' => round((float) $row->night_sunday_hours, 2),
                'overtime_day_hours' => round((float) $row->overtime_day_hours, 2),
                'overtime_night_hours' => round((float) $row->overtime_night_hours, 2),
                'overtime_day_sunday_hours' => round((float) $row->overtime_day_sunday_hours, 2),
                'overtime_night_sunday_hours' => round((float) $row->overtime_night_sunday_hours, 2),
            ])
            ->toArray();
    }
}
