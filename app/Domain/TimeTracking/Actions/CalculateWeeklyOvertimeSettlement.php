<?php

namespace App\Domain\TimeTracking\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalculateWeeklyOvertimeSettlement
{
    private const OVERTIME_FIELDS = [
        'overtime_day_hours',
        'overtime_night_hours',
        'overtime_day_dominical_hours',
        'overtime_night_dominical_hours',
        'overtime_day_holiday_hours',
        'overtime_night_holiday_hours',
    ];

    /**
     * @param  array<int, int>  $employeeIds
     * @param  array{start: ?string, end: ?string, deferred: bool}  $window
     * @return array<int, array{
     *     worked_overtime_minutes: int,
     *     balance_minutes: int,
     *     offset_minutes: int,
     *     payable_overtime_minutes: int,
     *     deficit_minutes: int,
     *     payable_factor: float,
     *     weeks: array<int, array{start: string, end: string, worked_minutes: int, balance_minutes: int}>
     * }>
     */
    public function execute(int $companyId, array $employeeIds, array $window, int $maxWeeklyMinutes): array
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($employeeIds === []) {
            return [];
        }

        if ($window['start'] === null || $window['end'] === null) {
            return collect($employeeIds)
                ->mapWithKeys(fn (int $employeeId): array => [$employeeId => $this->emptySettlement()])
                ->all();
        }

        $weekTemplate = $this->buildWeekTemplate($window['start'], $window['end']);
        $workedHoursByEmployee = [];
        $overtimeHoursByEmployee = array_fill_keys($employeeIds, 0.0);

        foreach ($employeeIds as $employeeId) {
            $workedHoursByEmployee[$employeeId] = array_fill_keys(array_keys($weekTemplate), 0.0);
        }

        $rows = DB::table('time_entries')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$window['start'], $window['end']])
            ->whereNull('deleted_at')
            ->whereNotNull('clock_out')
            ->get(['employee_id', 'date', 'net_hours', ...self::OVERTIME_FIELDS]);

        foreach ($rows as $row) {
            $employeeId = (int) $row->employee_id;
            $weekStart = Carbon::parse((string) $row->date)->startOfWeek(Carbon::MONDAY)->toDateString();

            if (isset($workedHoursByEmployee[$employeeId][$weekStart])) {
                $workedHoursByEmployee[$employeeId][$weekStart] += (float) $row->net_hours;
            }

            foreach (self::OVERTIME_FIELDS as $field) {
                $overtimeHoursByEmployee[$employeeId] += (float) $row->{$field};
            }
        }

        $settlements = [];

        foreach ($employeeIds as $employeeId) {
            $weeks = [];
            $balanceMinutes = 0;

            foreach ($weekTemplate as $weekStart => $week) {
                $workedMinutes = (int) round($workedHoursByEmployee[$employeeId][$weekStart] * 60);
                $weekBalanceMinutes = $workedMinutes - $maxWeeklyMinutes;
                $balanceMinutes += $weekBalanceMinutes;
                $weeks[] = [
                    'start' => $week['start'],
                    'end' => $week['end'],
                    'worked_minutes' => $workedMinutes,
                    'balance_minutes' => $weekBalanceMinutes,
                ];
            }

            $workedOvertimeMinutes = max(0, (int) round($overtimeHoursByEmployee[$employeeId] * 60));
            $payableOvertimeMinutes = min($workedOvertimeMinutes, max(0, $balanceMinutes));

            $settlements[$employeeId] = [
                'worked_overtime_minutes' => $workedOvertimeMinutes,
                'balance_minutes' => $balanceMinutes,
                'offset_minutes' => $workedOvertimeMinutes - $payableOvertimeMinutes,
                'payable_overtime_minutes' => $payableOvertimeMinutes,
                'deficit_minutes' => max(0, -$balanceMinutes),
                'payable_factor' => $workedOvertimeMinutes > 0
                    ? (float) ($payableOvertimeMinutes / $workedOvertimeMinutes)
                    : 0.0,
                'weeks' => $weeks,
            ];
        }

        return $settlements;
    }

    /**
     * @return array<string, array{start: string, end: string}>
     */
    private function buildWeekTemplate(string $startDate, string $endDate): array
    {
        $weeks = [];
        $weekStart = Carbon::parse($startDate)->startOfDay();
        $windowEnd = Carbon::parse($endDate)->endOfDay();

        while ($weekStart->lessThanOrEqualTo($windowEnd)) {
            $start = $weekStart->toDateString();
            $weeks[$start] = [
                'start' => $start,
                'end' => $weekStart->copy()->addDays(6)->toDateString(),
            ];
            $weekStart->addWeek();
        }

        return $weeks;
    }

    /**
     * @return array{worked_overtime_minutes: int, balance_minutes: int, offset_minutes: int, payable_overtime_minutes: int, deficit_minutes: int, payable_factor: float, weeks: array}
     */
    private function emptySettlement(): array
    {
        return [
            'worked_overtime_minutes' => 0,
            'balance_minutes' => 0,
            'offset_minutes' => 0,
            'payable_overtime_minutes' => 0,
            'deficit_minutes' => 0,
            'payable_factor' => 0.0,
            'weeks' => [],
        ];
    }
}
