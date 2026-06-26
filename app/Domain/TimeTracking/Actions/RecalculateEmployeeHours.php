<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Shared\Scopes\CompanyScope;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecalculateEmployeeHours
{
    public function __construct(
        private readonly RecalculateTimeEntry $recalculateTimeEntry,
        private readonly CalculateWorkHours $calculateWorkHours,
    ) {}

    /**
     * Recalcula las horas derivadas y la clasificación de los registros de un empleado en un rango,
     * aplicando la configuración vigente (franja nocturna, límites diario/semanal, día dominical).
     *
     * Es seguro y repetible: solo recomputa columnas derivadas a partir de los fichajes y pausas
     * reales; no toca clock_in/out ni pausas. Procesa en orden cronológico para que el acumulado
     * semanal/diario (que lee net_hours de los turnos previos) quede consistente si la config de
     * pausas hubiera cambiado el net. Preserva el estado `edited` de los registros editados a mano.
     *
     * @return int Cantidad de registros recalculados.
     */
    public function execute(int $employeeId, CarbonInterface $startDate, CarbonInterface $endDate): int
    {
        $entries = TimeEntry::withoutGlobalScopes([CompanyScope::class])
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('clock_out')
            ->orderBy('date')
            ->orderBy('clock_in')
            ->get();

        $recalculated = 0;

        DB::transaction(function () use ($entries, &$recalculated) {
            foreach ($entries as $entry) {
                $wasEdited = $entry->status === 'edited';

                $this->recalculateTimeEntry->recomputeDerivedHours($entry);
                $this->calculateWorkHours->execute($entry->fresh());

                // CalculateWorkHours deja el estado en `calculated`; conservamos la marca de
                // edición manual para no borrar el rastro de que ese registro fue corregido.
                if ($wasEdited) {
                    $entry->refresh()->update(['status' => 'edited']);
                }

                $recalculated++;
            }
        });

        return $recalculated;
    }
}
