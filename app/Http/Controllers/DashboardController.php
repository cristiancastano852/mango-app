<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Scopes\CompanyScope;
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

        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin.companies.index');
        }

        if (! $user->isCompanyAdmin()) {
            return redirect()->route('time-clock.index');
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        // TODO: Schedules feature temporarily disabled — restore $dayOfWeek when resuming
        // $dayOfWeek = (int) now()->dayOfWeek;

        $presentCount = TimeEntry::whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->count();

        $yesterdayPresentCount = TimeEntry::withoutGlobalScopes([CompanyScope::class])
            ->where('company_id', $user->company_id)
            ->whereDate('date', $yesterday)
            ->whereNotNull('clock_in')
            ->count();

        $onBreakCount = BreakEntry::whereHas('timeEntry', fn ($q) => $q->whereDate('date', $today))
            ->whereNull('ended_at')
            ->count();

        // TODO: Schedules feature temporarily disabled — restore scheduledEmployeeIds, presentEmployeeIds, absentCount when resuming

        $netHoursToday = (float) TimeEntry::whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->sum('net_hours');

        $avgNetHours = $presentCount > 0 ? round($netHoursToday / $presentCount, 2) : 0;

        // TODO: Schedules feature temporarily disabled — restore lateArrivals logic when resuming
        $lateArrivals = collect();

        $employees = Employee::with([
            'user',
            'timeEntries' => fn ($q) => $q->whereDate('date', $today),
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
            ];
        });

        $company = $user->company;
        $showTour = $company
            && $company->onboarding_completed
            && ! $request->session()->get('tour_dismissed', false);

        return Inertia::render('Dashboard', [
            'kpis' => [
                'present' => $presentCount,
                'present_delta' => $presentCount - $yesterdayPresentCount,
                'on_break' => $onBreakCount,
                // TODO: Schedules feature temporarily disabled — restore absent from $absentCount when resuming
                'absent' => 0,
                'net_hours_today' => round($netHoursToday, 2),
                'avg_net_hours' => $avgNetHours,
            ],
            'employeeStatus' => $employeeStatus,
            'lateArrivals' => $lateArrivals,
            'showTour' => $showTour,
        ]);
    }
}
