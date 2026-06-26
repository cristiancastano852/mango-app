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
     * gross_hours → break_hours (solo pausas no pagadas finalizadas)
     * → paid_break_overage_hours (exceso de pausas pagadas sobre su límite) → net_hours,
     * reclasifica los 8 buckets con CalculateWorkHours y marca el registro como editado.
     */
    public function execute(
        TimeEntry $timeEntry,
        ?User $editedBy = null,
        ?string $editReason = null,
    ): TimeEntry {
        // Un turno abierto (sin clock_out) no tiene horas que recomputar; salir sin tocar
        // los totales evita poner gross/net en 0 al gestionar pausas de un turno en curso.
        if (! $timeEntry->clock_out) {
            return $timeEntry;
        }

        $this->recomputeDerivedHours($timeEntry);

        $timeEntry->update([
            'edited_by' => $editedBy?->id ?? $timeEntry->edited_by,
            'edit_reason' => $editReason ?? $timeEntry->edit_reason,
        ]);

        $this->calculateWorkHours->execute($timeEntry->fresh());

        $timeEntry->refresh();
        $timeEntry->update(['status' => 'edited']);

        return $timeEntry->fresh();
    }

    /**
     * Recomputa gross_hours → break_hours (pausas no pagadas finalizadas) →
     * paid_break_overage_hours → net_hours a partir de los fichajes y pausas reales.
     * No toca el estado ni la reclasificación de buckets (eso lo hace CalculateWorkHours aparte).
     */
    public function recomputeDerivedHours(TimeEntry $timeEntry): void
    {
        $grossHours = round($timeEntry->clock_in->diffInMinutes($timeEntry->clock_out) / 60, 2);

        $breakHours = round(
            $timeEntry->breaks()
                ->whereNotNull('ended_at')
                ->whereHas('breakType', fn ($query) => $query->where('is_paid', false))
                ->sum('duration_minutes') / 60,
            2,
        );

        $paidBreakOverageHours = $timeEntry->paidBreakOverageHours();

        $netHours = round(max(0, $grossHours - $breakHours - $paidBreakOverageHours), 2);

        $timeEntry->update([
            'gross_hours' => $grossHours,
            'break_hours' => $breakHours,
            'paid_break_overage_hours' => $paidBreakOverageHours,
            'net_hours' => $netHours,
        ]);
    }
}
