<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;

class RecalculateTimeEntry
{
    public function __construct(
        private readonly CalculateWorkHours $calculateWorkHours,
    ) {}

    /**
     * Recomputa las horas derivadas de un registro tras editar sus horas o pausas:
     * gross_hours → break_hours (solo pausas no pagadas finalizadas) → net_hours,
     * reclasifica los 8 buckets con CalculateWorkHours y marca el registro como editado.
     */
    public function execute(
        TimeEntry $timeEntry,
        ?User $editedBy = null,
        ?string $editReason = null,
    ): TimeEntry {
        $grossHours = $timeEntry->clock_out
            ? round($timeEntry->clock_in->diffInMinutes($timeEntry->clock_out) / 60, 2)
            : 0.0;

        $breakHours = round(
            $timeEntry->breaks()
                ->whereNotNull('ended_at')
                ->whereHas('breakType', fn ($query) => $query->where('is_paid', false))
                ->sum('duration_minutes') / 60,
            2,
        );

        $netHours = round(max(0, $grossHours - $breakHours), 2);

        $timeEntry->update([
            'gross_hours' => $grossHours,
            'break_hours' => $breakHours,
            'net_hours' => $netHours,
            'edited_by' => $editedBy?->id ?? $timeEntry->edited_by,
            'edit_reason' => $editReason ?? $timeEntry->edit_reason,
        ]);

        $this->calculateWorkHours->execute($timeEntry->fresh());

        $timeEntry->refresh();
        $timeEntry->update(['status' => 'edited']);

        return $timeEntry->fresh();
    }
}
