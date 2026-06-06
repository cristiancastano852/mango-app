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

        $endedAt = now();

        $breakEntry->update([
            'ended_at' => $endedAt,
            'duration_minutes' => (int) $breakEntry->started_at->diffInMinutes($endedAt),
        ]);

        return $breakEntry->fresh('breakType');
    }
}
