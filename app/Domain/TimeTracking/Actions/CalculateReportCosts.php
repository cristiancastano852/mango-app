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
     *  - `*_holiday` (festivo): SIEMPRE se pagan (no editables); configurables por hora o por día vía
     *    $holiday, igual que el dominical pero pagando todos los del periodo. Usan el mismo % dominical
     *    (sunday_holiday/night_sunday/overtime_*_sunday).
     *  - `*_dominical`: configurables vía $dominical.
     *
     * Recargo extra nocturno (`pay_overtime_night`): cuando está apagado, TODA hora extra nocturna
     * (semana/dominical/festiva) se paga como su extra diurna correspondiente. Su renglón nocturno
     * queda en 0h/$0 y las horas se funden en la extra diurna de su misma familia (o en la de semana
     * si ya colapsó allí por su flag dominical/festivo). Solo cambia el % nocturno→diurno; no afecta
     * la compensación de overtime ($payOvertime manda).
     *
     * Config dominical ($dominical):
     *  - `pay` (bool): si no se paga, las horas dominicales se tratan como día normal:
     *    diurnas → regular, nocturnas → night (conservan recargo nocturno), overtime → overtime
     *    de semana. Solo se pierde el recargo dominical.
     *  - `mode` (`hour`|`day`): en `hour` el recargo dominical es por hora (sunday_holiday/night_sunday).
     *    En `day` la base de las horas se paga como ordinaria/nocturna y se suma un plus plano por día
     *    pagado = `min(payable_count, worked_days) × day_value × (sunday_holiday% / 100)`. El overtime
     *    dominical se paga por hora con su recargo dominical en ambos modos.
     *  - `day_value` (float): valor del día normal (input del usuario); el recargo aplica el % sobre él.
     *  - `payable_count` (?int, null = todos), `worked_days` (int N).
     *
     * Modos de salario: `hourly` cobra base+recargo por hora; `monthly` solo el % (la base ya está
     * en el salario). El overtime siempre se paga completo (1+recargo%); cuando $payOvertime es false
     * las 6 categorías de overtime se cobran en 0 y se marcan compensated.
     *
     * @param  array{regular_hours: float, night_hours: float, dominical_hours: float, night_dominical_hours: float, holiday_hours: float, night_holiday_hours: float, overtime_day_hours: float, overtime_night_hours: float, overtime_day_dominical_hours: float, overtime_night_dominical_hours: float, overtime_day_holiday_hours: float, overtime_night_holiday_hours: float}  $hourTotals
     * @param  array{pay?: bool, mode?: string, day_value?: float, payable_count?: int|null, worked_days?: int}  $dominical
     * @param  array{mode?: string, day_value?: float, worked_days?: int}  $holiday  Festivo: `mode` (`hour`|`day`),
     *                                                                               `worked_days` (N festivos trabajados), `day_value` (valor del día normal)
     * @param  array{health?: float, pension?: float}  $socialSecurity  Tasas (%) del aporte de seguridad social a cargo del
     *                                                                  empleado: salud y pensión. Se aplican sobre el IBC
     *                                                                  (`total − auxilio de transporte`).
     * @param  array{bonus_total?: float, deduction_total?: float}  $adjustments  Ajustes de nómina del periodo aplicados
     *                                                                            DESPUÉS del neto: `final_pay = net_pay +
     *                                                                            bonus_total − deduction_total`. No afectan el IBC.
     */
    public function execute(float $hourlyRate, array $hourTotals, SurchargeRule $rules, bool $payOvertime = true, string $salaryType = 'hourly', float $baseSalary = 0.0, float $transportAllowance = 0.0, array $dominical = [], array $holiday = [], array $socialSecurity = [], array $adjustments = [], ?float $overtimePayableHours = null, ?array $nightWindowHours = null): array
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

        // Valor del día normal (compartido por dominical y festivo en modo por día).
        $normalDayValue = (float) ($dominical['day_value'] ?? $holiday['day_value'] ?? 0);

        // Modos por hora/día (compartidos por dominical y festivo).
        $dominicalMode = $dominical['mode'] ?? 'hour';
        $holidayMode = $holiday['mode'] ?? 'hour';

        // Flags de colapso de recargos premium hacia su base (cost-time).
        // En modo por día la noche premium ya se paga a tarifa nocturna normal y el recargo del día
        // Cada recargo premium se funde en su base cuando su flag está apagado, de forma uniforme
        // (ambos modos hora/día y aun con overtime compensado). En por-día la noche ya se paga a
        // tarifa nocturna normal, así que fundirla no cambia el costo (35% en ambos casos); solo
        // unifica la presentación. El overtime compensado ya va a $0 vía otCost, por eso fundirlo
        // tampoco "resucita" pago: solo mueve las horas al renglón base.
        $payNightDominical = $rules->pay_night_dominical ?? true;
        $payNightHoliday = $rules->pay_night_holiday ?? true;
        $payOvertimeDominical = $rules->pay_overtime_dominical ?? true;
        $payOvertimeHoliday = $rules->pay_overtime_holiday ?? true;
        $payOvertimeNight = $rules->pay_overtime_night ?? true;

        $collapseNightDominical = ! $payNightDominical;
        $collapseNightHoliday = ! $payNightHoliday;
        $collapseOvertimeDominical = ! $payOvertimeDominical;
        $collapseOvertimeHoliday = ! $payOvertimeHoliday;
        // Extra nocturna sin recargo nocturno: cada hora extra nocturna se paga como su extra
        // diurna correspondiente (semana → diurna, dominical → diurna dominical, festiva → diurna
        // festiva). Se aplica DESPUÉS del colapso dominical/festivo, así una hora ya fundida en la
        // semana usa el % diurno de semana; la que conserva su familia usa su % diurno premium.
        $collapseOvertimeNight = ! $payOvertimeNight;

        // --- Semana / nocturno (las horas nocturnas premium colapsadas se funden en el nocturno base) ---
        $regularCost = $regularHours * $regularRate;
        $effectiveNightHours = $nightHours
            + ($collapseNightDominical ? $nightDominicalHours : 0.0)
            + ($collapseNightHoliday ? $nightHolidayHours : 0.0);
        $nightCost = $effectiveNightHours * $hourlyRate * $premiumFactor($nightPct);

        // Overtime base: absorbe las horas extra dominicales/festivas colapsadas (solo cuando se paga).
        $effectiveOvertimeDayHours = $overtimeDayHours
            + ($collapseOvertimeDominical ? $overtimeDayDominicalHours : 0.0)
            + ($collapseOvertimeHoliday ? $overtimeDayHolidayHours : 0.0);
        $effectiveOvertimeNightHours = $overtimeNightHours
            + ($collapseOvertimeDominical ? $overtimeNightDominicalHours : 0.0)
            + ($collapseOvertimeHoliday ? $overtimeNightHolidayHours : 0.0);
        if ($collapseOvertimeNight) {
            $effectiveOvertimeDayHours += $effectiveOvertimeNightHours;
            $effectiveOvertimeNightHours = 0.0;
        }

        // Horas extra pagables: cuando el overtime está unificado en una sola bolsa diurna
        // (los 3 flags premium en off: dominical, festivo y nocturno), el admin puede limitar
        // cuántas horas se pagan. null = todas, 0 = ninguna, puede superar lo trabajado.
        $overtimeUnified = $collapseOvertimeDominical && $collapseOvertimeHoliday && $collapseOvertimeNight;
        $payableOvertimeDayHours = $effectiveOvertimeDayHours;
        if ($overtimeUnified && $overtimePayableHours !== null) {
            $payableOvertimeDayHours = max(0.0, $overtimePayableHours);
        }

        $overtimeDayCost = $otCost($payableOvertimeDayHours, $otDayPct);
        $overtimeNightCost = $otCost($effectiveOvertimeNightHours, $otNightPct);

        // --- Festivo (SIEMPRE paga el diurno; la noche/extra festiva colapsa según su flag) ---
        $workedHolidayDays = (int) ($holiday['worked_days'] ?? 0);
        if ($holidayMode === 'day') {
            // Base por horas como ordinario + recargo plano por cada día festivo trabajado.
            $holidayFlatPremium = $workedHolidayDays * $normalDayValue * ($dominicalPct / 100);
            $holidayCost = $holidayHours * $regularRate + $holidayFlatPremium;
            $holidaySurcharge = 0.0;
        } else {
            $holidayCost = $holidayHours * $hourlyRate * $premiumFactor($dominicalPct);
            $holidaySurcharge = $dominicalPct;
        }
        if ($collapseNightHoliday) {
            $nightHolidayCost = 0.0;
            $nightHolidaySurcharge = 0.0;
        } elseif ($holidayMode === 'day') {
            $nightHolidayCost = $nightHolidayHours * $hourlyRate * $premiumFactor($nightPct);
            $nightHolidaySurcharge = $nightPct;
        } else {
            $nightHolidayCost = $nightHolidayHours * $hourlyRate * $premiumFactor($nightDominicalPct);
            $nightHolidaySurcharge = $nightDominicalPct;
        }
        // La extra nocturna festiva que conserva su familia (festivo ON) se funde en la extra diurna
        // festiva cuando el recargo extra nocturno está apagado; si el festivo ya colapsó a semana,
        // estas horas viajaron arriba y aquí quedan en 0.
        $effectiveOvertimeDayHolidayHours = $overtimeDayHolidayHours
            + ($collapseOvertimeNight ? $overtimeNightHolidayHours : 0.0);
        $effectiveOvertimeNightHolidayHours = $collapseOvertimeNight ? 0.0 : $overtimeNightHolidayHours;
        $overtimeDayHolidayCost = $collapseOvertimeHoliday ? 0.0 : $otCost($effectiveOvertimeDayHolidayHours, $otDayDominicalPct);
        $overtimeDayHolidaySurcharge = $collapseOvertimeHoliday ? 0.0 : $otDayDominicalPct;
        $overtimeNightHolidayCost = $collapseOvertimeHoliday ? 0.0 : $otCost($effectiveOvertimeNightHolidayHours, $otNightDominicalPct);
        $overtimeNightHolidaySurcharge = $collapseOvertimeHoliday ? 0.0 : $otNightDominicalPct;

        // --- Dominical (diurno configurable; noche y extra según sus flags premium) ---
        $payDominical = (bool) ($dominical['pay'] ?? true);
        $workedDominicalDays = (int) ($dominical['worked_days'] ?? 0);
        $payableCount = $dominical['payable_count'] ?? null;

        // Días dominicales que reciben recargo (modo día). El conteo K manda y puede SUPERAR los
        // trabajados en el periodo (p. ej. saldar un dominical pendiente de otra quincena). Cuando no
        // hay decisión explícita, el default depende del switch de la empresa: todos (ON) o ninguno (OFF).
        $paidDominicalDays = $payableCount !== null
            ? max(0, (int) $payableCount)
            : ($payDominical ? $workedDominicalDays : 0);

        // pay_dominical_by_default solo decide el recargo dominical DIURNO.
        if ($dominicalMode === 'day') {
            $flatPremium = $paidDominicalDays * $normalDayValue * ($dominicalPct / 100);
            $dominicalCost = $dominicalHours * $regularRate + $flatPremium;
            $dominicalSurcharge = 0.0;
        } elseif (! $payDominical) {
            $dominicalCost = $dominicalHours * $regularRate;
            $dominicalSurcharge = 0.0;
        } else {
            $dominicalCost = $dominicalHours * $hourlyRate * $premiumFactor($dominicalPct);
            $dominicalSurcharge = $dominicalPct;
        }
        if ($collapseNightDominical) {
            $nightDominicalCost = 0.0;
            $nightDominicalSurcharge = 0.0;
        } elseif ($dominicalMode === 'day') {
            $nightDominicalCost = $nightDominicalHours * $hourlyRate * $premiumFactor($nightPct);
            $nightDominicalSurcharge = $nightPct;
        } else {
            $nightDominicalCost = $nightDominicalHours * $hourlyRate * $premiumFactor($nightDominicalPct);
            $nightDominicalSurcharge = $nightDominicalPct;
        }
        // Igual que el festivo: la extra nocturna dominical que conserva su familia (dominical ON) se
        // funde en la extra diurna dominical cuando el recargo extra nocturno está apagado.
        $effectiveOvertimeDayDominicalHours = $overtimeDayDominicalHours
            + ($collapseOvertimeNight ? $overtimeNightDominicalHours : 0.0);
        $effectiveOvertimeNightDominicalHours = $collapseOvertimeNight ? 0.0 : $overtimeNightDominicalHours;
        $overtimeDayDominicalCost = $collapseOvertimeDominical ? 0.0 : $otCost($effectiveOvertimeDayDominicalHours, $otDayDominicalPct);
        $overtimeDayDominicalSurcharge = $collapseOvertimeDominical ? 0.0 : $otDayDominicalPct;
        $overtimeNightDominicalCost = $collapseOvertimeDominical ? 0.0 : $otCost($effectiveOvertimeNightDominicalHours, $otNightDominicalPct);
        $overtimeNightDominicalSurcharge = $collapseOvertimeDominical ? 0.0 : $otNightDominicalPct;

        // --- Diferimiento del recargo nocturno (modo deferred) ---
        // El componente nocturno (`night_surcharge`%, = $nightPct) de las horas del día de corte se
        // difiere al periodo siguiente; la base y el recargo dominical/festivo se quedan por fecha.
        // Equivale a un ajuste por la diferencia entre las horas nocturnas de la ventana corrida
        // (`$nightWindowHours`) y las del rango del periodo, valorada solo al % nocturno. En modo
        // immediate `$nightWindowHours` es null (o iguala el periodo) → ajuste 0, sin cambio.
        if ($nightWindowHours !== null) {
            $nightSurchargeShift = fn (float $periodHours, string $key): float => (((float) ($nightWindowHours[$key] ?? $periodHours)) - $periodHours) * $hourlyRate * ($nightPct / 100);

            // El ajuste sigue el mismo colapso que las horas: si el bucket premium se fundió en el
            // nocturno base, su diferimiento viaja con él.
            $nightCost += $nightSurchargeShift($nightHours, 'night_hours')
                + ($collapseNightDominical ? $nightSurchargeShift($nightDominicalHours, 'night_dominical_hours') : 0.0)
                + ($collapseNightHoliday ? $nightSurchargeShift($nightHolidayHours, 'night_holiday_hours') : 0.0);

            if (! $collapseNightDominical) {
                $nightDominicalCost += $nightSurchargeShift($nightDominicalHours, 'night_dominical_hours');
            }

            if (! $collapseNightHoliday) {
                $nightHolidayCost += $nightSurchargeShift($nightHolidayHours, 'night_holiday_hours');
            }
        }

        // Horas a mostrar: el premium colapsado se funde en su base y su renglón queda en 0h.
        $nightDisplayHours = $effectiveNightHours;
        $nightDominicalDisplayHours = $collapseNightDominical ? 0.0 : $nightDominicalHours;
        $nightHolidayDisplayHours = $collapseNightHoliday ? 0.0 : $nightHolidayHours;
        $overtimeDayDisplayHours = $effectiveOvertimeDayHours;
        $overtimeNightDisplayHours = $effectiveOvertimeNightHours;
        $overtimeDayDominicalDisplayHours = $collapseOvertimeDominical ? 0.0 : $effectiveOvertimeDayDominicalHours;
        $overtimeNightDominicalDisplayHours = $collapseOvertimeDominical ? 0.0 : $effectiveOvertimeNightDominicalHours;
        $overtimeDayHolidayDisplayHours = $collapseOvertimeHoliday ? 0.0 : $effectiveOvertimeDayHolidayHours;
        $overtimeNightHolidayDisplayHours = $collapseOvertimeHoliday ? 0.0 : $effectiveOvertimeNightHolidayHours;

        $totalCost = $baseSalary + $transportAllowance
            + $regularCost + $nightCost
            + $dominicalCost + $nightDominicalCost
            + $holidayCost + $nightHolidayCost
            + $overtimeDayCost + $overtimeNightCost
            + $overtimeDayDominicalCost + $overtimeNightDominicalCost
            + $overtimeDayHolidayCost + $overtimeNightHolidayCost;

        // --- Seguridad social a cargo del empleado (salud + pensión sobre el IBC) ---
        // El IBC es el total devengado menos el auxilio de transporte (que no integra el IBC).
        // En hourly $transportAllowance ya es 0, así que el IBC equivale al total.
        $socialSecurityBase = max(0.0, $totalCost - $transportAllowance);
        $healthRate = (float) ($socialSecurity['health'] ?? 0);
        $pensionRate = (float) ($socialSecurity['pension'] ?? 0);
        $healthDeduction = round($socialSecurityBase * $healthRate / 100, 2);
        $pensionDeduction = round($socialSecurityBase * $pensionRate / 100, 2);
        $netPay = round($totalCost - $healthDeduction - $pensionDeduction, 2);

        // --- Ajustes de nómina (bonos/deducciones) aplicados después del neto, sin tocar el IBC ---
        $bonusTotal = round((float) ($adjustments['bonus_total'] ?? 0), 2);
        $deductionTotal = round((float) ($adjustments['deduction_total'] ?? 0), 2);
        $finalPay = round($netPay + $bonusTotal - $deductionTotal, 2);

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
            'social_security_base' => round($socialSecurityBase, 2),
            'health_rate' => $healthRate,
            'health_deduction' => $healthDeduction,
            'pension_rate' => $pensionRate,
            'pension_deduction' => $pensionDeduction,
            'net_pay' => $netPay,
            'bonus_total' => $bonusTotal,
            'deduction_total' => $deductionTotal,
            'final_pay' => $finalPay,
            'salary_type' => $salaryType,
            'pay_overtime' => $payOvertime,
            'pay_dominical' => $payDominical,
            'pay_night_dominical' => $payNightDominical,
            'pay_night_holiday' => $payNightHoliday,
            'pay_overtime_dominical' => $payOvertimeDominical,
            'pay_overtime_holiday' => $payOvertimeHoliday,
            'pay_overtime_night' => $payOvertimeNight,
            'dominical_mode' => $dominicalMode,
            'normal_day_value' => round($normalDayValue, 2),
            'dominical_worked_days' => $workedDominicalDays,
            'dominical_paid_days' => $paidDominicalDays,
            'holiday_mode' => $holidayMode,
            'holiday_worked_days' => $workedHolidayDays,
            'overtime_unified' => $overtimeUnified,
            'overtime_worked_hours' => round($effectiveOvertimeDayHours, 2),
            'overtime_payable_hours' => $overtimePayableHours !== null ? round($payableOvertimeDayHours, 2) : null,
            'details' => [
                $detail('regular', $regularHours, 0, $regularCost),
                $detail('night', $nightDisplayHours, $nightPct, $nightCost),
                $detail('dominical', $dominicalHours, $dominicalSurcharge, $dominicalCost),
                $detail('night_dominical', $nightDominicalDisplayHours, $nightDominicalSurcharge, $nightDominicalCost),
                $detail('holiday', $holidayHours, $holidaySurcharge, $holidayCost),
                $detail('night_holiday', $nightHolidayDisplayHours, $nightHolidaySurcharge, $nightHolidayCost),
                $detail('overtime_day', $overtimeDayDisplayHours, $otDayPct, $overtimeDayCost, $otCompensated),
                $detail('overtime_night', $overtimeNightDisplayHours, $otNightPct, $overtimeNightCost, $otCompensated),
                $detail('overtime_day_dominical', $overtimeDayDominicalDisplayHours, $overtimeDayDominicalSurcharge, $overtimeDayDominicalCost, $otCompensated),
                $detail('overtime_night_dominical', $overtimeNightDominicalDisplayHours, $overtimeNightDominicalSurcharge, $overtimeNightDominicalCost, $otCompensated),
                $detail('overtime_day_holiday', $overtimeDayHolidayDisplayHours, $overtimeDayHolidaySurcharge, $overtimeDayHolidayCost, $otCompensated),
                $detail('overtime_night_holiday', $overtimeNightHolidayDisplayHours, $overtimeNightHolidaySurcharge, $overtimeNightHolidayCost, $otCompensated),
            ],
        ];
    }
}
