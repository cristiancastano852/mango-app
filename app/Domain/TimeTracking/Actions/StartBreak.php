<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Validation\ValidationException;

class StartBreak
{
    public function execute(TimeEntry $timeEntry, int $breakTypeId): BreakEntry
    {
        if ($timeEntry->clock_out) {
            throw ValidationException::withMessages([
                'break' => 'No puedes iniciar una pausa después del check-out.',
            ]);
        }

        $activeBreak = $timeEntry->breaks()->whereNull('ended_at')->first();
        if ($activeBreak) {
            throw ValidationException::withMessages([
                'break' => 'Ya tienes una pausa activa.',
            ]);
        }

        $breakType = BreakType::findOrFail($breakTypeId);

        // Validar límite por día
        if ($breakType->max_per_day) {
            $todayCount = $timeEntry->breaks()
                ->where('break_type_id', $breakTypeId)
                ->count();

            if ($todayCount >= $breakType->max_per_day) {
                throw ValidationException::withMessages([
                    'break' => "Límite de {$breakType->name} alcanzado ({$breakType->max_per_day}/día).",
                ]);
            }
        }

        return BreakEntry::create([
            'time_entry_id' => $timeEntry->id,
            'employee_id' => $timeEntry->employee_id,
            'company_id' => $timeEntry->company_id,
            'break_type_id' => $breakTypeId,
            'started_at' => now(),
        ]);
    }
}
