<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCompanyAdmin() && ! $user->isSuperAdmin()) {
            return redirect()->route('time-clock.index');
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $dayOfWeek = (int) now()->dayOfWeek;

        $presentCount = TimeEntry::whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->count();

        $yesterdayPresentCount = TimeEntry::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereDate('date', $yesterday)
            ->whereNotNull('clock_in')
            ->count();

        $onBreakCount = BreakEntry::whereHas('timeEntry', fn ($q) => $q->whereDate('date', $today))
            ->whereNull('ended_at')
            ->count();

        $scheduledEmployeeIds = Employee::whereHas('schedule', function ($q) use ($dayOfWeek) {
            $q->whereJsonContains('days_of_week', $dayOfWeek);
        })->pluck('id');

        $presentEmployeeIds = TimeEntry::whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->pluck('employee_id');

        $absentCount = $scheduledEmployeeIds->diff($presentEmployeeIds)->count();

        $netHoursToday = (float) TimeEntry::whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->sum('net_hours');

        $avgNetHours = $presentCount > 0 ? round($netHoursToday / $presentCount, 2) : 0;

        $now = now();
        $lateThreshold = $now->copy()->subMinutes(15)->format('H:i:s');

        $lateArrivals = Employee::with(['user', 'schedule'])
            ->whereIn('id', $scheduledEmployeeIds)
            ->whereNotIn('id', $presentEmployeeIds)
            ->whereHas('schedule', fn ($q) => $q->where('start_time', '<=', $lateThreshold))
            ->get()
            ->map(function (Employee $employee) use ($now) {
                $scheduledAt = $now->copy()->setTimeFromTimeString(
                    $employee->schedule->getRawOriginal('start_time')
                );

                return [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->user->name,
                    ],
                    'scheduled_at' => $scheduledAt->format('H:i'),
                    'minutes_late' => (int) $scheduledAt->diffInMinutes($now),
                ];
            });

        $employees = Employee::with([
            'user',
            'timeEntries' => fn ($q) => $q->whereDate('date', $today)->limit(1),
            'breaks' => fn ($q) => $q->whereNull('ended_at')
                ->whereHas('timeEntry', fn ($q2) => $q2->whereDate('date', $today)),
        ])->get();

        $employeeStatus = $employees->map(function (Employee $employee) {
            $entry = $employee->timeEntries->first();
            $activeBreak = $employee->breaks->first();

            $status = 'absent';
            if ($entry) {
                if ($entry->clock_out) {
                    $status = 'done';
                } elseif ($activeBreak) {
                    $status = 'on_break';
                } else {
                    $status = 'working';
                }
            }

            return [
                'id' => $employee->id,
                'name' => $employee->user->name,
                'avatar' => $employee->user->avatar,
                'status' => $status,
                'clock_in' => $entry?->clock_in?->format('H:i'),
                'clock_out' => $entry?->clock_out?->format('H:i'),
                'net_hours_today' => $entry ? (float) $entry->net_hours : 0,
                'time_entry_id' => $entry?->id,
            ];
        });

        return Inertia::render('Dashboard', [
            'kpis' => [
                'present' => $presentCount,
                'present_delta' => $presentCount - $yesterdayPresentCount,
                'on_break' => $onBreakCount,
                'absent' => $absentCount,
                'net_hours_today' => round($netHoursToday, 2),
                'avg_net_hours' => $avgNetHours,
            ],
            'employeeStatus' => $employeeStatus,
            'lateArrivals' => $lateArrivals,
        ]);
    }
}
