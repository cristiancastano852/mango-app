<?php

namespace App\Http\Controllers\Admin;

use App\Domain\TimeTracking\Actions\RecalculateTimeEntry;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBreakRequest;
use App\Http\Requests\Admin\UpdateBreakRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TimeEntryBreakController extends Controller
{
    public function store(
        StoreBreakRequest $request,
        TimeEntry $timeEntry,
        RecalculateTimeEntry $recalculate,
    ): RedirectResponse {
        $validated = $request->validated();

        $timeEntry->breaks()->create([
            'employee_id' => $timeEntry->employee_id,
            'break_type_id' => $validated['break_type_id'],
            'started_at' => $validated['started_at'],
            'ended_at' => $validated['ended_at'],
            'duration_minutes' => (int) $validated['started_at']->diffInMinutes($validated['ended_at']),
        ]);

        $recalculate->execute($timeEntry->fresh(), $request->user());

        return back()->with('success', __('messages.break_added'));
    }

    public function update(
        UpdateBreakRequest $request,
        TimeEntry $timeEntry,
        BreakEntry $break,
        RecalculateTimeEntry $recalculate,
    ): RedirectResponse {
        $this->ensureBreakBelongsToEntry($timeEntry, $break);

        $validated = $request->validated();

        $break->update([
            'break_type_id' => $validated['break_type_id'],
            'started_at' => $validated['started_at'],
            'ended_at' => $validated['ended_at'],
            'duration_minutes' => (int) $validated['started_at']->diffInMinutes($validated['ended_at']),
        ]);

        $recalculate->execute($timeEntry->fresh(), $request->user());

        return back()->with('success', __('messages.break_updated'));
    }

    public function destroy(
        TimeEntry $timeEntry,
        BreakEntry $break,
        RecalculateTimeEntry $recalculate,
    ): RedirectResponse {
        $this->ensureBreakBelongsToEntry($timeEntry, $break);

        $break->delete();

        $recalculate->execute($timeEntry->fresh(), request()->user());

        return back()->with('success', __('messages.break_deleted'));
    }

    private function ensureBreakBelongsToEntry(TimeEntry $timeEntry, BreakEntry $break): void
    {
        if ($break->time_entry_id !== $timeEntry->id) {
            throw new NotFoundHttpException;
        }
    }
}
