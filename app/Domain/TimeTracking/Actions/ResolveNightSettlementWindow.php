<?php

namespace App\Domain\TimeTracking\Actions;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class ResolveNightSettlementWindow
{
    /**
     * Resuelve la ventana de fechas sobre la que se liquida el COMPONENTE de recargo nocturno
     * (`night_surcharge`%) de un reporte, según el modo de liquidación de la empresa.
     *
     * Regla de diferimiento (modo `deferred`): la empresa paga en la mañana del día de corte, así
     * que el recargo nocturno de ese día aún no se conoce y se difiere al periodo siguiente. La
     * ventana se corre un día hacia atrás: `[inicio − 1, fin − 1]`. Esto excluye el día de corte del
     * periodo actual e incluye el día de corte del periodo anterior (cuyo recargo se difirió hacia
     * acá). Con periodos contiguos no hay solapamiento ni doble conteo.
     *
     * En modo `immediate` la ventana coincide con el rango del periodo (comportamiento actual).
     *
     * `deferred` indica que el día de corte del periodo difiere su recargo nocturno al próximo periodo.
     *
     * @return array{start: string, end: string, deferred: bool}
     */
    public function execute(CarbonInterface $startDate, CarbonInterface $endDate, string $mode): array
    {
        if ($mode !== 'deferred') {
            return [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'deferred' => false,
            ];
        }

        return [
            'start' => Carbon::parse($startDate->toDateString())->subDay()->toDateString(),
            'end' => Carbon::parse($endDate->toDateString())->subDay()->toDateString(),
            'deferred' => true,
        ];
    }
}
