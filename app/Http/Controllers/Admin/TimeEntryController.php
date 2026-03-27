<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\CalculateWorkHours;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTimeEntryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = TimeEntry::with(['employee.user', 'editedBy'])
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->input('date'), fn ($q, $date) => $q->whereDate('date', $date))
            ->orderByDesc('date')
            ->orderByDesc('clock_in')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'date' => $entry->date,
                'clock_in' => $entry->clock_in?->format('H:i'),
                'clock_out' => $entry->clock_out?->format('H:i'),
                'net_hours' => $entry->net_hours,
                'status' => $entry->status,
                'edit_reason' => $entry->edit_reason,
                'employee' => $entry->employee,
                'edited_by' => $entry->editedBy,
            ]);

        return Inertia::render('Admin/TimeEntries/Index', [
            'entries' => $entries,
            'employees' => Employee::with('user')->get()->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->user->name,
            ]),
            'filters' => $request->only(['employee_id', 'date']),
        ]);
    }

    public function edit(TimeEntry $timeEntry): Response
    {
        $timeEntry->load(['employee.user', 'editedBy']);

        return Inertia::render('Admin/TimeEntries/Edit', [
            'entry' => [
                'id' => $timeEntry->id,
                'date' => $timeEntry->date,
                'clock_in' => $timeEntry->clock_in?->format('Y-m-d\TH:i'),
                'clock_out' => $timeEntry->clock_out?->format('Y-m-d\TH:i'),
                'net_hours' => $timeEntry->net_hours,
                'regular_hours' => $timeEntry->regular_hours,
                'overtime_hours' => $timeEntry->overtime_hours,
                'night_hours' => $timeEntry->night_hours,
                'sunday_holiday_hours' => $timeEntry->sunday_holiday_hours,
                'status' => $timeEntry->status,
                'edit_reason' => $timeEntry->edit_reason,
                'employee' => $timeEntry->employee,
            ],
        ]);
    }

    public function update(
        UpdateTimeEntryRequest $request,
        TimeEntry $timeEntry,
        CalculateWorkHours $calculator,
    ): RedirectResponse {
        $validated = $request->validated();

        $clockIn = $validated['clock_in'];
        $clockOut = $validated['clock_out'];
        $grossHours = round($clockIn->diffInMinutes($clockOut) / 60, 4);

        $timeEntry->update([
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'gross_hours' => $grossHours,
            'net_hours' => $grossHours - (float) $timeEntry->break_hours,
            'edit_reason' => $validated['edit_reason'],
            'edited_by' => $request->user()->id,
        ]);

        $calculator->execute($timeEntry->fresh());

        $timeEntry->update(['status' => 'edited']);

        return redirect()->route('admin.time-entries.index')
            ->with('success', __('messages.time_entry_updated'));
    }
}
