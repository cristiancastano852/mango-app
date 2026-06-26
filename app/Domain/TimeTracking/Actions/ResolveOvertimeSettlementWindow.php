<?php

namespace App\Domain\TimeTracking\Actions;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class ResolveOvertimeSettlementWindow
{
    /**
     * Resuelve la ventana de fechas sobre la que se suman las horas EXTRA de un reporte,
     * según el modo de acumulación de la empresa.
     *
     * Regla "dueño del domingo" (modo `weekly`): el recargo extra de una semana ISO se liquida
     * en el periodo que contiene su domingo. La ventana abarca desde el lunes de la primera
     * semana cuyo domingo cae en el periodo, hasta el último domingo que cae en el periodo.
     * Si el periodo no contiene ningún domingo, no se liquida overtime (`start`/`end` nulos).
     *
     * En modo `daily` la ventana coincide con el rango del periodo (comportamiento actual).
     *
     * `deferred` indica que el periodo termina a mitad de semana ISO y, por tanto, el extra de
     * esa semana en curso se liquidará en el próximo periodo.
     *
     * @return array{start: ?string, end: ?string, deferred: bool}
     */
    public function execute(CarbonInterface $startDate, CarbonInterface $endDate, string $mode): array
    {
        if ($mode !== 'weekly') {
            return [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'deferred' => false,
            ];
        }

        $start = Carbon::parse($startDate->toDateString());
        $end = Carbon::parse($endDate->toDateString());

        $deferred = ! $end->isSunday();

        $firstOwnedSunday = $start->isSunday() ? $start->copy() : $start->copy()->next(Carbon::SUNDAY);
        $lastOwnedSunday = $end->isSunday() ? $end->copy() : $end->copy()->previous(Carbon::SUNDAY);

        if ($firstOwnedSunday->greaterThan($lastOwnedSunday)) {
            return ['start' => null, 'end' => null, 'deferred' => $deferred];
        }

        return [
            'start' => $firstOwnedSunday->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'end' => $lastOwnedSunday->toDateString(),
            'deferred' => $deferred,
        ];
    }
}
