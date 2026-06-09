<?php

namespace App\Domain\TimeTracking\Actions;

4use App\Domain\TimeTracking\Models\BreakEntry;
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
        // Un turno abierto (sin clock_out) no tiene horas que recomputar; salir sin tocar
        // los totales evita poner gross/net en 0 al gestionar pausas de un turno en curso.
        if (! $timeEntry->clock_out) {
            return $timeEntry;
        }

        $grossHours = round($timeEntry->clock_in->diffInMinutes($timeEntry->clock_out) / 60, 2);

        $breakMinutes = $timeEntry->breaks()
            ->whereNotNull('ended_at')
            ->with('breakType')
            ->get()
            ->sum(fn (BreakEntry $break): int => $this->deductibleBreakMinutes($break));

        $breakHours = round($breakMinutes / 60, 2);

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

    /**
     * Minutos de una pausa finalizada que descuentan del tiempo trabajado:
     * - No pagada: su duración completa.
     * - Pagada con tope: solo el exceso sobre max_duration_minutes.
     * - Pagada sin tope: nada.
     */
    private function deductibleBreakMinutes(BreakEntry $break): int
    {
        $duration = (int) $break->duration_minutes;

        if (! $break->breakType->is_paid) {
            return $duration;
        }

        $cap = $break->breakType->max_duration_minutes;

        if ($cap === null) {
            return 0;
        }

        return max(0, $duration - (int) $cap);
    }
}
