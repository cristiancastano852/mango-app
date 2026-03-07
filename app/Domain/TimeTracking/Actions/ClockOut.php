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

        // Finalizar pausa activa si existe
        $activeBreak = $timeEntry->breaks()->whereNull('ended_at')->first();
        if ($activeBreak) {
            $activeBreak->update([
                'ended_at' => now(),
                'duration_minutes' => (int) now()->diffInMinutes($activeBreak->started_at),
            ]);
        }

        $clockIn = $timeEntry->clock_in;
        $clockOut = now();
        $grossHours = round($clockIn->diffInMinutes($clockOut) / 60, 2);

        $breakHours = round(
            $timeEntry->breaks()
                ->whereNotNull('ended_at')
                ->whereHas('breakType', fn ($q) => $q->where('is_paid', false))
                ->sum('duration_minutes') / 60,
            2
        );

        $netHours = round(max(0, $grossHours - $breakHours), 2);

        $timeEntry->update([
            'clock_out' => $clockOut,
            'gross_hours' => $grossHours,
            'break_hours' => $breakHours,
            'net_hours' => $netHours,
        ]);

        $this->calculateWorkHours->execute($timeEntry->fresh());

        return $timeEntry->fresh(['breaks.breakType']);
    }
}
