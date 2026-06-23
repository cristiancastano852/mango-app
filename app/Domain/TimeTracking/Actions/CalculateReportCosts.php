<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\SurchargeRule;

class CalculateReportCosts
{
    /**
     * Calcula el costo laboral desglosado por los 12 tipos de hora usando las reglas
     * de recargo de la empresa y la tarifa por hora del empleado.
     *
     * Familias premium:
     *  - `*_holiday` (festivo): SIEMPRE se pagan, con el mismo % que el dominical (sunday_holiday/
     *    night_sunday/overtime_*_sunday). No se ven afectadas por la config dominical.
     *  - `*_dominical`: configurables vía $dominical.
     *
     * Config dominical ($dominical):
     *  - `pay` (bool): si no se paga, las horas dominicales se tratan como día normal:
     *    diurnas → regular, nocturnas → night (conservan recargo nocturno), overtime → overtime
     *    de semana. Solo se pierde el recargo dominical.
     *  - `mode` (`hour`|`day`): en `hour` el recargo dominical es por hora (sunday_holiday/night_sunday).
     *    En `day` la base de las horas se paga como ordinaria/nocturna y se suma un plus plano
     *    `min(payable_count, worked_days) × day_value` (solo el recargo). El overtime dominical se
     *    paga por hora con su recargo dominical en ambos modos.
     *  - `day_value` (float), `payable_count` (?int, null = todos), `worked_days` (int N).
     *
     * Modos de salario: `hourly` cobra base+recargo por hora; `monthly` solo el % (la base ya está
     * en el salario). El overtime siempre se paga completo (1+recargo%); cuando $payOvertime es false
     * las 6 categorías de overtime se cobran en 0 y se marcan compensated.
     *
     * @param  array{regular_hours: float, night_hours: float, dominical_hours: float, night_dominical_hours: float, holiday_hours: float, night_holiday_hours: float, overtime_day_hours: float, overtime_night_hours: float, overtime_day_dominical_hours: float, overtime_night_dominical_hours: float, overtime_day_holiday_hours: float, overtime_night_holiday_hours: float}  $hourTotals
     * @param  array{pay?: bool, mode?: string, day_value?: float, payable_count?: int|null, worked_days?: int}  $dominical
     */
    public function execute(float $hourlyRate, array $hourTotals, SurchargeRule $rules, bool $payOvertime = true, string $salaryType = 'hourly', float $baseSalary = 0.0, float $transportAllowance = 0.0, array $dominical = []): array
    {
        $h = fn (string $key): float => (float) ($hourTotals[$key] ?? 0);

        $regularHours = $h('regular_hours');
        $nightHours = $h('night_hours');
        $dominicalHours = $h('dominical_hours');
        $nightDominicalHours = $h('night_dominical_hours');
        $holidayHours = $h('holiday_hours');
        $nightHolidayHours = $h('night_holiday_hours');
        $overtimeDayHours = $h('overtime_day_hours');
        $overtimeNightHours = $h('overtime_night_hours');
        $overtimeDayDominicalHours = $h('overtime_day_dominical_hours');
        $overtimeNightDominicalHours = $h('overtime_night_dominical_hours');
        $overtimeDayHolidayHours = $h('overtime_day_holiday_hours');
        $overtimeNightHolidayHours = $h('overtime_night_holiday_hours');

        $isMonthly = $salaryType === 'monthly';
        $baseSalary = $isMonthly ? $baseSalary : 0.0;
        $transportAllowance = $isMonthly ? $transportAllowance : 0.0;

        // Tarifa de una hora "ordinaria": en monthly ya está en el salario base.
        $regularRate = $isMonthly ? 0.0 : $hourlyRate;
        // Factor para recargos por hora: monthly suma solo el %, hourly suma base + %.
        $premiumFactor = fn (float $percent): float => $isMonthly ? ($percent / 100) : (1 + $percent / 100);
        // Overtime siempre completo (fuera de la jornada base), gated por $payOvertime.
        $otCost = fn (float $hours, float $percent): float => $payOvertime ? $hours * $hourlyRate * (1 + $percent / 100) : 0.0;

        $nightPct = (float) $rules->night_surcharge;
        $dominicalPct = (float) $rules->sunday_holiday;
        $nightDominicalPct = (float) $rules->night_sunday;
        $otDayPct = (float) $rules->overtime_day;
        $otNightPct = (float) $rules->overtime_night;
        $otDayDominicalPct = (float) $rules->overtime_day_sunday;
        $otNightDominicalPct = (float) $rules->overtime_night_sunday;

        // --- Semana ---
        $regularCost = $regularHours * $regularRate;
        $nightCost = $nightHours * $hourlyRate * $premiumFactor($nightPct);
        $overtimeDayCost = $otCost($overtimeDayHours, $otDayPct);
        $overtimeNightCost = $otCost($overtimeNightHours, $otNightPct);

        // --- Festivo (siempre paga, mismo % que dominical) ---
        $holidayCost = $holidayHours * $hourlyRate * $premiumFactor($dominicalPct);
        $nightHolidayCost = $nightHolidayHours * $hourlyRate * $premiumFactor($nightDominicalPct);
        $overtimeDayHolidayCost = $otCost($overtimeDayHolidayHours, $otDayDominicalPct);
        $overtimeNightHolidayCost = $otCost($overtimeNightHolidayHours, $otNightDominicalPct);

        // --- Dominical (configurable) ---
        $payDominical = (bool) ($dominical['pay'] ?? true);
        $dominicalMode = $dominical['mode'] ?? 'hour';
        $dominicalDayValue = (float) ($dominical['day_value'] ?? 0);
        $workedDominicalDays = (int) ($dominical['worked_days'] ?? 0);
        $payableCount = $dominical['payable_count'] ?? null;
        $paidDominicalDays = $payableCount === null
            ? $workedDominicalDays
            : max(0, min((int) $payableCount, $workedDominicalDays));

        if (! $payDominical) {
            // Día normal: base ordinaria/nocturna, overtime de semana. Solo se pierde el recargo dominical.
            $dominicalCost = $dominicalHours * $regularRate;
            $dominicalSurcharge = 0.0;
            $nightDominicalCost = $nightDominicalHours * $hourlyRate * $premiumFactor($nightPct);
            $nightDominicalSurcharge = $nightPct;
            $overtimeDayDominicalCost = $otCost($overtimeDayDominicalHours, $otDayPct);
            $overtimeDayDominicalSurcharge = $otDayPct;
            $overtimeNightDominicalCost = $otCost($overtimeNightDominicalHours, $otNightPct);
            $overtimeNightDominicalSurcharge = $otNightPct;
        } else {
            // Overtime dominical: por hora con recargo dominical en ambos modos.
            $overtimeDayDominicalCost = $otCost($overtimeDayDominicalHours, $otDayDominicalPct);
            $overtimeDayDominicalSurcharge = $otDayDominicalPct;
            $overtimeNightDominicalCost = $otCost($overtimeNightDominicalHours, $otNightDominicalPct);
            $overtimeNightDominicalSurcharge = $otNightDominicalPct;

            if ($dominicalMode === 'day') {
                // Base como ordinario/nocturno + plus plano por dominical pagado (solo el recargo).
                $flatPremium = $paidDominicalDays * $dominicalDayValue;
                $dominicalCost = $dominicalHours * $regularRate + $flatPremium;
                $dominicalSurcharge = 0.0;
                $nightDominicalCost = $nightDominicalHours * $hourlyRate * $premiumFactor($nightPct);
                $nightDominicalSurcharge = $nightPct;
            } else {
                $dominicalCost = $dominicalHours * $hourlyRate * $premiumFactor($dominicalPct);
                $dominicalSurcharge = $dominicalPct;
                $nightDominicalCost = $nightDominicalHours * $hourlyRate * $premiumFactor($nightDominicalPct);
                $nightDominicalSurcharge = $nightDominicalPct;
            }
        }

        $totalCost = $baseSalary + $transportAllowance
            + $regularCost + $nightCost
            + $dominicalCost + $nightDominicalCost
            + $holidayCost + $nightHolidayCost
            + $overtimeDayCost + $overtimeNightCost
            + $overtimeDayDominicalCost + $overtimeNightDominicalCost
            + $overtimeDayHolidayCost + $overtimeNightHolidayCost;

        $otCompensated = ! $payOvertime;

        $detail = fn (string $type, float $hours, float $surcharge, float $subtotal, bool $compensated = false): array => [
            'type' => $type,
            'hours' => $hours,
            'rate' => $hourlyRate,
            'surcharge' => $surcharge,
            'subtotal' => round($subtotal, 2),
            'compensated' => $compensated,
        ];

        return [
            'regular' => round($regularCost, 2),
            'night' => round($nightCost, 2),
            'dominical' => round($dominicalCost, 2),
            'night_dominical' => round($nightDominicalCost, 2),
            'holiday' => round($holidayCost, 2),
            'night_holiday' => round($nightHolidayCost, 2),
            'overtime_day' => round($overtimeDayCost, 2),
            'overtime_night' => round($overtimeNightCost, 2),
            'overtime_day_dominical' => round($overtimeDayDominicalCost, 2),
            'overtime_night_dominical' => round($overtimeNightDominicalCost, 2),
            'overtime_day_holiday' => round($overtimeDayHolidayCost, 2),
            'overtime_night_holiday' => round($overtimeNightHolidayCost, 2),
            'base' => round($baseSalary, 2),
            'transport_allowance' => round($transportAllowance, 2),
            'total' => round($totalCost, 2),
            'salary_type' => $salaryType,
            'pay_overtime' => $payOvertime,
            'pay_dominical' => $payDominical,
            'dominical_mode' => $dominicalMode,
            'dominical_day_value' => round($dominicalDayValue, 2),
            'dominical_worked_days' => $workedDominicalDays,
            'dominical_paid_days' => $paidDominicalDays,
            'details' => [
                $detail('regular', $regularHours, 0, $regularCost),
                $detail('night', $nightHours, $nightPct, $nightCost),
                $detail('dominical', $dominicalHours, $dominicalSurcharge, $dominicalCost),
                $detail('night_dominical', $nightDominicalHours, $nightDominicalSurcharge, $nightDominicalCost),
                $detail('holiday', $holidayHours, $dominicalPct, $holidayCost),
                $detail('night_holiday', $nightHolidayHours, $nightDominicalPct, $nightHolidayCost),
                $detail('overtime_day', $overtimeDayHours, $otDayPct, $overtimeDayCost, $otCompensated),
                $detail('overtime_night', $overtimeNightHours, $otNightPct, $overtimeNightCost, $otCompensated),
                $detail('overtime_day_dominical', $overtimeDayDominicalHours, $overtimeDayDominicalSurcharge, $overtimeDayDominicalCost, $otCompensated),
                $detail('overtime_night_dominical', $overtimeNightDominicalHours, $overtimeNightDominicalSurcharge, $overtimeNightDominicalCost, $otCompensated),
                $detail('overtime_day_holiday', $overtimeDayHolidayHours, $otDayDominicalPct, $overtimeDayHolidayCost, $otCompensated),
                $detail('overtime_night_holiday', $overtimeNightHolidayHours, $otNightDominicalPct, $overtimeNightHolidayCost, $otCompensated),
            ],
        ];
    }
}
