<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Scopes\CompanyScope;
use App\Domain\TimeTracking\Models\BreakEntry;
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
    public function execute(int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate, bool $payOvertime = true, bool $includeDailyBreakdown = true, bool $includeBreaksByType = true): array
    {
        $employee = Employee::withoutGlobalScopes()
            ->with('user', 'department', 'position')
            ->findOrFail($employeeId);

        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $employee->company_id)
            ->firstOrFail();

        $totals = $this->aggregateTotals($employeeId, $startDate, $endDate);
        $breaksByType = $includeBreaksByType
            ? $this->aggregateBreaksByType($employeeId, $startDate, $endDate)
            : [];
        $dailyBreakdown = $includeDailyBreakdown
            ? $this->getDailyBreakdown($employeeId, $startDate, $endDate)
            : [];

        $salaryType = $employee->salary_type ?? 'hourly';
        $baseSalary = $salaryType === 'monthly'
            ? $this->baseSalaryCalculator->execute((float) $employee->monthly_base_salary, $startDate, $endDate)
            : 0.0;

        // El auxilio se prorratea con el mismo mes comercial que el salario base, solo en
        // modo monthly y cuando el empleado lo recibe.
        $transportAllowance = $salaryType === 'monthly' && $employee->receives_transport_allowance
            ? $this->baseSalaryCalculator->execute((float) $rules->transport_allowance, $startDate, $endDate)
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
            $transportAllowance,
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
     * Obtiene el desglose diario con horario y pausas. Gracias al unique(employee_id, date)
     * cada entry equivale a un día; los turnos en curso (sin clock_out) se incluyen con
     * status 'in_progress' y horas en null — los totales del período no los consideran.
     *
     * @return array<array{date: string, clock_in: ?string, clock_out: ?string, status: string, gross_hours: ?float, break_hours: ?float, paid_break_hours: ?float, net_hours: ?float, regular_hours: ?float, night_hours: ?float, sunday_holiday_hours: ?float, night_sunday_hours: ?float, overtime_day_hours: ?float, overtime_night_hours: ?float, overtime_day_sunday_hours: ?float, overtime_night_sunday_hours: ?float, breaks: array}>
     */
    private function getDailyBreakdown(int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return TimeEntry::withoutGlobalScopes([CompanyScope::class])
            ->with([
                'breaks' => fn ($query) => $query->withoutGlobalScopes()->orderBy('started_at'),
                'breaks.breakType' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (TimeEntry $entry) => $this->mapDay($entry))
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDay(TimeEntry $entry): array
    {
        $inProgress = $entry->clock_out === null;

        $hours = [
            'gross_hours', 'break_hours', 'net_hours', 'regular_hours', 'night_hours',
            'sunday_holiday_hours', 'night_sunday_hours', 'overtime_day_hours',
            'overtime_night_hours', 'overtime_day_sunday_hours', 'overtime_night_sunday_hours',
        ];

        $day = [
            'date' => substr((string) $entry->date, 0, 10),
            'clock_in' => $entry->clock_in?->toIso8601String(),
            'clock_out' => $entry->clock_out?->toIso8601String(),
            'status' => $inProgress ? 'in_progress' : $entry->status,
            'paid_break_hours' => $inProgress ? null : $entry->paidBreakHours(),
            'breaks' => $entry->breaks->map(fn (BreakEntry $break) => $break->toDisplayArray())->all(),
        ];

        foreach ($hours as $field) {
            $day[$field] = $inProgress ? null : round((float) $entry->{$field}, 2);
        }

        return $day;
    }
}
