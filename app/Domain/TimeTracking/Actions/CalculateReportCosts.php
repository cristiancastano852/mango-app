<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\SurchargeRule;

class CalculateReportCosts
{
    /**
     * Calcula el costo laboral desglosado por tipo de hora usando las reglas
     * de recargo de la empresa y la tarifa por hora del empleado.
     *
     * @param  float  $hourlyRate  Tarifa base por hora del empleado (COP)
     * @param  array{regular_hours: float, night_hours: float, overtime_hours: float, sunday_holiday_hours: float}  $hourTotals
     * @return array{regular: float, night: float, overtime: float, sunday_holiday: float, total: float, details: array}
     */
    public function execute(float $hourlyRate, array $hourTotals, SurchargeRule $rules): array
    {
        $regularHours = (float) ($hourTotals['regular_hours'] ?? 0);
        $nightHours = (float) ($hourTotals['night_hours'] ?? 0);
        $overtimeHours = (float) ($hourTotals['overtime_hours'] ?? 0);
        $sundayHolidayHours = (float) ($hourTotals['sunday_holiday_hours'] ?? 0);

        $regularCost = $regularHours * $hourlyRate;
        $nightCost = $nightHours * $hourlyRate * (1 + (float) $rules->night_surcharge / 100);
        $overtimeCost = $overtimeHours * $hourlyRate * (1 + (float) $rules->overtime_day / 100);
        $sundayHolidayCost = $sundayHolidayHours * $hourlyRate * (1 + (float) $rules->sunday_holiday / 100);

        $totalCost = $regularCost + $nightCost + $overtimeCost + $sundayHolidayCost;

        return [
            'regular' => round($regularCost, 2),
            'night' => round($nightCost, 2),
            'overtime' => round($overtimeCost, 2),
            'sunday_holiday' => round($sundayHolidayCost, 2),
            'total' => round($totalCost, 2),
            'details' => [
                [
                    'type' => 'regular',
                    'hours' => $regularHours,
                    'rate' => $hourlyRate,
                    'surcharge' => 0,
                    'subtotal' => round($regularCost, 2),
                ],
                [
                    'type' => 'night',
                    'hours' => $nightHours,
                    'rate' => $hourlyRate,
                    'surcharge' => (float) $rules->night_surcharge,
                    'subtotal' => round($nightCost, 2),
                ],
                [
                    'type' => 'overtime',
                    'hours' => $overtimeHours,
                    'rate' => $hourlyRate,
                    'surcharge' => (float) $rules->overtime_day,
                    'subtotal' => round($overtimeCost, 2),
                ],
                [
                    'type' => 'sunday_holiday',
                    'hours' => $sundayHolidayHours,
                    'rate' => $hourlyRate,
                    'surcharge' => (float) $rules->sunday_holiday,
                    'subtotal' => round($sundayHolidayCost, 2),
                ],
            ],
        ];
    }
}
