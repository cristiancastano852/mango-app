<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\RecalculateTimeEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTimeEntryRequest;
use App\Http\Requests\Admin\UpdateTimeEntryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = TimeEntry::with([
            'employee.user',
            'editedBy',
            'breaks' => fn ($q) => $q->orderBy('started_at'),
            'breaks.breakType',
        ])
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('clock_in')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'date' => $entry->date,
                'clock_in' => $entry->clock_in?->toIso8601String(),
                'clock_out' => $entry->clock_out?->toIso8601String(),
                'gross_hours' => $entry->gross_hours,
                'break_hours' => $entry->break_hours,
                'paid_break_hours' => $entry->paidBreakHours(),
                'net_hours' => $entry->net_hours,
                'status' => $entry->status,
                'edit_reason' => $entry->edit_reason,
                'employee' => $entry->employee,
                'edited_by' => $entry->editedBy,
                'breaks' => $entry->breaks->map(fn ($break) => $break->toDisplayArray()),
            ]);

        return Inertia::render('Admin/TimeEntries/Index', [
            'entries' => $entries,
            'employees' => Employee::with('user')->get()->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->user->name,
            ]),
            'filters' => $request->only(['employee_id', 'date_from', 'date_to']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TimeEntries/Create', [
            'employees' => Employee::with('user')->get()->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->user->name,
            ]),
        ]);
    }

    public function store(StoreTimeEntryRequest $request, RecalculateTimeEntry $recalculate): RedirectResponse
    {
        $validated = $request->validated();

        $timeEntry = TimeEntry::create([
            'employee_id' => $validated['employee_id'],
            'date' => $validated['date'],
            'clock_in' => $validated['clock_in'],
            'clock_out' => $validated['clock_out'],
            'status' => 'pending',
        ]);

        $recalculate->execute($timeEntry, $request->user());

        return redirect()->route('admin.time-entries.index')
            ->with('success', __('messages.time_entry_created'));
    }

    public function edit(TimeEntry $timeEntry): Response
    {
        $timeEntry->load(['employee.user', 'editedBy', 'breaks.breakType']);

        return Inertia::render('Admin/TimeEntries/Edit', [
            'entry' => [
                'id' => $timeEntry->id,
                'date' => $timeEntry->date,
                'clock_in' => $timeEntry->clock_in?->format('Y-m-d\TH:i'),
                'clock_out' => $timeEntry->clock_out?->format('Y-m-d\TH:i'),
                'net_hours' => $timeEntry->net_hours,
                'regular_hours' => $timeEntry->regular_hours,
                'night_hours' => $timeEntry->night_hours,
                'sunday_holiday_hours' => $timeEntry->sunday_holiday_hours,
                'night_sunday_hours' => $timeEntry->night_sunday_hours,
                'overtime_day_hours' => $timeEntry->overtime_day_hours,
                'overtime_night_hours' => $timeEntry->overtime_night_hours,
                'overtime_day_sunday_hours' => $timeEntry->overtime_day_sunday_hours,
                'overtime_night_sunday_hours' => $timeEntry->overtime_night_sunday_hours,
                'status' => $timeEntry->status,
                'edit_reason' => $timeEntry->edit_reason,
                'employee' => $timeEntry->employee,
                'breaks' => $timeEntry->breaks->map(fn ($break) => [
                    'id' => $break->id,
                    'break_type_id' => $break->break_type_id,
                    'break_type_name' => $break->breakType?->name,
                    'is_paid' => (bool) $break->breakType?->is_paid,
                    'started_at' => $break->started_at?->format('Y-m-d\TH:i'),
                    'ended_at' => $break->ended_at?->format('Y-m-d\TH:i'),
                    'duration_minutes' => $break->duration_minutes,
                ]),
            ],
            'breakTypes' => BreakType::where('is_active', true)->get()->map(fn (BreakType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'is_paid' => (bool) $type->is_paid,
            ]),
        ]);
    }

    public function update(
        UpdateTimeEntryRequest $request,
        TimeEntry $timeEntry,
        RecalculateTimeEntry $recalculate,
    ): RedirectResponse {
        $validated = $request->validated();

        $timeEntry->update([
            'clock_in' => $validated['clock_in'],
            'clock_out' => $validated['clock_out'],
        ]);

        $recalculate->execute($timeEntry, $request->user(), $validated['edit_reason']);

        return redirect()->route('admin.time-entries.index')
            ->with('success', __('messages.time_entry_updated'));
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        $timeEntry->delete();

        return redirect()->route('admin.time-entries.index')
            ->with('success', __('messages.time_entry_deleted'));
    }
}
