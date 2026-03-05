<?php

namespace App\Http\Controllers;

use App\Domain\TimeTracking\Actions\ClockIn;
use App\Domain\TimeTracking\Actions\ClockOut;
use App\Domain\TimeTracking\Actions\EndBreak;
use App\Domain\TimeTracking\Actions\StartBreak;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeClockController extends Controller
{
    public function index(Request $request): Response
    {
        $employee = $request->user()->employee;

        $todayEntry = $employee
            ? TimeEntry::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('date', now()->toDateString())
                ->with(['breaks.breakType'])
                ->first()
            : null;

        $breakTypes = BreakType::where('is_active', true)->get();

        $recentEntries = $employee
            ? TimeEntry::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->with(['breaks.breakType'])
                ->orderByDesc('date')
                ->limit(7)
                ->get()
            : collect();

        return Inertia::render('TimeClock/Index', [
            'employee' => $employee?->load('user'),
            'todayEntry' => $todayEntry,
            'breakTypes' => $breakTypes,
            'recentEntries' => $recentEntries,
        ]);
    }

    public function clockIn(Request $request, ClockIn $action): RedirectResponse
    {
        $employee = $request->user()->employee;
        $action->execute($employee);

        return back()->with('success', 'Check-in registrado.');
    }

    public function clockOut(Request $request, ClockOut $action): RedirectResponse
    {
        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $request->user()->employee->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        $action->execute($entry);

        return back()->with('success', 'Check-out registrado.');
    }

    public function startBreak(Request $request, StartBreak $action): RedirectResponse
    {
        $request->validate(['break_type_id' => ['required', 'exists:break_types,id']]);

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $request->user()->employee->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        $action->execute($entry, $request->input('break_type_id'));

        return back()->with('success', 'Pausa iniciada.');
    }

    public function endBreak(Request $request, EndBreak $action): RedirectResponse
    {
        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $request->user()->employee->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        $activeBreak = $entry->breaks()->whereNull('ended_at')->firstOrFail();
        $action->execute($activeBreak);

        return back()->with('success', 'Pausa finalizada.');
    }
}
