<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\TimeTracking\Models\BreakEntry;
use Illuminate\Validation\ValidationException;

class EndBreak
{
    public function execute(BreakEntry $breakEntry): BreakEntry
    {
        if ($breakEntry->ended_at) {
            throw ValidationException::withMessages([
                'break' => 'Esta pausa ya fue finalizada.',
            ]);
        }

        $duration = (int) now()->diffInMinutes($breakEntry->started_at);

        $breakEntry->update([
            'ended_at' => now(),
            'duration_minutes' => $duration,
        ]);

        return $breakEntry->fresh('breakType');
    }
}
