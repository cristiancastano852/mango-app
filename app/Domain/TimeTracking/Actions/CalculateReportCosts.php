<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\SurchargeRule;

class CalculateReportCosts
{
    /**
     * Calcula el costo laboral desglosado por los 8 tipos de hora usando las reglas
     * de recargo de la empresa y la tarifa por hora del empleado.
     *
     * Modos de salario:
     *  - `hourly`  → cada bucket suma `horas × tarifa × (1 + recargo%)`, con `regular` al 0%.
     *                No se suma salario base.
     *  - `monthly` → la hora ordinaria ya está incluida en el salario base, por lo que:
     *                · `regular` no suma costo por hora (subtotal 0; las horas quedan informativas).
     *                · los recargos (`night`, `sunday_holiday`, `night_sunday`) suman SOLO el
     *                  porcentaje: `horas × tarifa × (recargo% / 100)`.
     *                · las 4 horas extra suman el valor COMPLETO: `horas × tarifa × (1 + recargo%)`,
     *                  porque están fuera de la jornada que cubre el salario base.
     *                · el `total` incluye además el salario base prorrateado del periodo (`$baseSalary`).
     *
     * Cuando $payOvertime es false, las 4 categorías de hora extra se cobran en 0,
     * se excluyen del total y se marcan como compensated (las horas no se modifican). Aplica en ambos modos.
     *
     * @param  float  $hourlyRate  Tarifa/valor por hora del empleado (COP)
     * @param  array{regular_hours: float, night_hours: float, sunday_holiday_hours: float, night_sunday_hours: float, overtime_day_hours: float, overtime_night_hours: float, overtime_day_sunday_hours: float, overtime_night_sunday_hours: float}  $hourTotals
     * @param  string  $salaryType  `hourly` | `monthly`
     * @param  float  $baseSalary  Salario base prorrateado del periodo (solo se suma en modo `monthly`)
     * @return array{regular: float, night: float, sunday_holiday: float, night_sunday: float, overtime_day: float, overtime_night: float, overtime_day_sunday: float, overtime_night_sunday: float, base: float, total: float, salary_type: string, pay_overtime: bool, details: list<array{type: string, hours: float, rate: float, surcharge: float, subtotal: float, compensated: bool}>}
     */
    public function execute(float $hourlyRate, array $hourTotals, SurchargeRule $rules, bool $payOvertime = true, string $salaryType = 'hourly', float $baseSalary = 0.0): array
    {
        $regularHours = (float) ($hourTotals['regular_hours'] ?? 0);
        $nightHours = (float) ($hourTotals['night_hours'] ?? 0);
        $sundayHolidayHours = (float) ($hourTotals['sunday_holiday_hours'] ?? 0);
        $nightSundayHours = (float) ($hourTotals['night_sunday_hours'] ?? 0);
        $overtimeDayHours = (float) ($hourTotals['overtime_day_hours'] ?? 0);
        $overtimeNightHours = (float) ($hourTotals['overtime_night_hours'] ?? 0);
        $overtimeDaySundayHours = (float) ($hourTotals['overtime_day_sunday_hours'] ?? 0);
        $overtimeNightSundayHours = (float) ($hourTotals['overtime_night_sunday_hours'] ?? 0);

        $isMonthly = $salaryType === 'monthly';
        $baseSalary = $isMonthly ? $baseSalary : 0.0;

        // En modo monthly los recargos suman solo el porcentaje (la hora base ya está en el salario);
        // en modo hourly suman el valor completo (1 + recargo%).
        $surchargeFactor = fn (float $percent): float => $isMonthly ? ($percent / 100) : (1 + $percent / 100);

        // La hora ordinaria no se cobra por hora en modo monthly (está dentro del salario base).
        $regularCost = $isMonthly ? 0.0 : $regularHours * $hourlyRate;
        $nightCost = $nightHours * $hourlyRate * $surchargeFactor((float) $rules->night_surcharge);
        $sundayHolidayCost = $sundayHolidayHours * $hourlyRate * $surchargeFactor((float) $rules->sunday_holiday);
        $nightSundayCost = $nightSundayHours * $hourlyRate * $surchargeFactor((float) $rules->night_sunday);

        // Las horas extra siempre se pagan completas (fuera de la jornada base). Cuando no se pagan,
        // se cobran en 0 pero las horas siguen visibles.
        $overtimeDayCost = $payOvertime ? $overtimeDayHours * $hourlyRate * (1 + (float) $rules->overtime_day / 100) : 0.0;
        $overtimeNightCost = $payOvertime ? $overtimeNightHours * $hourlyRate * (1 + (float) $rules->overtime_night / 100) : 0.0;
        $overtimeDaySundayCost = $payOvertime ? $overtimeDaySundayHours * $hourlyRate * (1 + (float) $rules->overtime_day_sunday / 100) : 0.0;
        $overtimeNightSundayCost = $payOvertime ? $overtimeNightSundayHours * $hourlyRate * (1 + (float) $rules->overtime_night_sunday / 100) : 0.0;

        $totalCost = $baseSalary + $regularCost + $nightCost + $sundayHolidayCost + $nightSundayCost
            + $overtimeDayCost + $overtimeNightCost + $overtimeDaySundayCost + $overtimeNightSundayCost;

        $overtimeCompensated = ! $payOvertime;

        return [
            'regular' => round($regularCost, 2),
            'night' => round($nightCost, 2),
            'sunday_holiday' => round($sundayHolidayCost, 2),
            'night_sunday' => round($nightSundayCost, 2),
            'overtime_day' => round($overtimeDayCost, 2),
            'overtime_night' => round($overtimeNightCost, 2),
            'overtime_day_sunday' => round($overtimeDaySundayCost, 2),
            'overtime_night_sunday' => round($overtimeNightSundayCost, 2),
            'base' => round($baseSalary, 2),
            'total' => round($totalCost, 2),
            'salary_type' => $salaryType,
            'pay_overtime' => $payOvertime,
            'details' => [
                ['type' => 'regular', 'hours' => $regularHours, 'rate' => $hourlyRate, 'surcharge' => 0, 'subtotal' => round($regularCost, 2), 'compensated' => false],
                ['type' => 'night', 'hours' => $nightHours, 'rate' => $hourlyRate, 'surcharge' => (float) $rules->night_surcharge, 'subtotal' => round($nightCost, 2), 'compensated' => false],
                ['type' => 'sunday_holiday', 'hours' => $sundayHolidayHours, 'rate' => $hourlyRate, 'surcharge' => (float) $rules->sunday_holiday, 'subtotal' => round($sundayHolidayCost, 2), 'compensated' => false],
                ['type' => 'night_sunday', 'hours' => $nightSundayHours, 'rate' => $hourlyRate, 'surcharge' => (float) $rules->night_sunday, 'subtotal' => round($nightSundayCost, 2), 'compensated' => false],
                ['type' => 'overtime_day', 'hours' => $overtimeDayHours, 'rate' => $hourlyRate, 'surcharge' => (float) $rules->overtime_day, 'subtotal' => round($overtimeDayCost, 2), 'compensated' => $overtimeCompensated],
                ['type' => 'overtime_night', 'hours' => $overtimeNightHours, 'rate' => $hourlyRate, 'surcharge' => (float) $rules->overtime_night, 'subtotal' => round($overtimeNightCost, 2), 'compensated' => $overtimeCompensated],
                ['type' => 'overtime_day_sunday', 'hours' => $overtimeDaySundayHours, 'rate' => $hourlyRate, 'surcharge' => (float) $rules->overtime_day_sunday, 'subtotal' => round($overtimeDaySundayCost, 2), 'compensated' => $overtimeCompensated],
                ['type' => 'overtime_night_sunday', 'hours' => $overtimeNightSundayHours, 'rate' => $hourlyRate, 'surcharge' => (float) $rules->overtime_night_sunday, 'subtotal' => round($overtimeNightSundayCost, 2), 'compensated' => $overtimeCompensated],
            ],
        ];
    }
}
