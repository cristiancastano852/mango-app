<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Validation\ValidationException;

class ClockOut
{
    public function __construct(
        private readonly CalculateWorkHours $calculateWorkHours
    ) {}

    public function execute(TimeEntry $timeEntry): TimeEntry
    {
        if ($timeEntry->clock_out) {
            throw ValidationException::withMessages([
                'clock_out' => 'Ya hiciste check-out.',
            ]);
        }

        $clockOut = now();

        // Finalizar pausa activa si existe
        $activeBreak = $timeEntry->breaks()->whereNull('ended_at')->first();
        if ($activeBreak) {
            $activeBreak->update([
                'ended_at' => $clockOut,
                'duration_minutes' => (int) $activeBreak->started_at->diffInMinutes($clockOut),
            ]);
        }

        $clockIn = $timeEntry->clock_in;
        $grossHours = round($clockIn->diffInMinutes($clockOut) / 60, 2);

        $breakHours = round(
            $timeEntry->breaks()
                ->whereNotNull('ended_at')
                ->whereHas('breakType', fn ($q) => $q->where('is_paid', false))
                ->sum('duration_minutes') / 60,
            2
        );

        $paidBreakOverageHours = $timeEntry->paidBreakOverageHours();

        $netHours = round(max(0, $grossHours - $breakHours - $paidBreakOverageHours), 2);

        $timeEntry->update([
            'clock_out' => $clockOut,
            'gross_hours' => $grossHours,
            'break_hours' => $breakHours,
            'paid_break_overage_hours' => $paidBreakOverageHours,
            'net_hours' => $netHours,
        ]);

        $this->calculateWorkHours->execute($timeEntry->fresh());

        return $timeEntry->fresh(['breaks.breakType']);
    }
}
