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
            ->withQueryString();

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
            'entry' => $timeEntry,
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
            'status' => 'edited',
        ]);

        $calculator->execute($timeEntry->fresh());

        return redirect()->route('admin.time-entries.index')
            ->with('success', __('messages.time_entry_updated'));
    }
}
