<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\Holiday;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Shared\Scopes\CompanyScope;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;

class CalculateWorkHours
{
    /**
     * Clasifica y distribuye los minutos trabajados en un TimeEntry en 12 tipos de hora
     * mutuamente excluyentes (semana/dominical/festivo × diurno/nocturno × dentro-límite/extra).
     * El día dominical es configurable (surcharge_rules.dominical_weekday) y festivo gana sobre
     * dominical. Luego actualiza el registro en BD.
     *
     * Flujo general:
     *   1. Validar que el turno esté completo (tiene clock_out y horas > 0).
     *   2. Cargar las reglas de recargo de la empresa y convertir tiempos a la zona horaria local.
     *   3. Calcular cuántos minutos netos ya acumuló el empleado en la semana y en el día actual
     *      (antes de este turno).
     *   4. Iterar segmento a segmento entre breakpoints, clasificando cada segmento.
     *   5. Guardar los totales clasificados en el TimeEntry.
     */
    public function execute(TimeEntry $entry): TimeEntry
    {
        if (! $entry->clock_out || (float) $entry->gross_hours === 0.0) {
            return $entry;
        }

        $company = $entry->company;

        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $entry->company_id)
            ->first();

        $tz = $company->timezone ?: 'America/Bogota';

        $clockIn = Carbon::parse($entry->clock_in)->setTimezone($tz);
        $clockOut = Carbon::parse($entry->clock_out)->setTimezone($tz);

        $weekStart = $clockIn->copy()->startOfWeek(Carbon::MONDAY);

        // Minutos netos acumulados en la semana antes de este turno.
        $priorWeeklyNetMinutes = TimeEntry::withoutGlobalScopes([CompanyScope::class])
            ->where('employee_id', $entry->employee_id)
            ->where('id', '!=', $entry->id)
            ->whereBetween('date', [$weekStart->toDateString(), $clockIn->toDateString()])
            ->whereNotNull('clock_out')
            ->sum('net_hours') * 60;

        // Minutos netos acumulados en el día del clock_in antes de este turno.
        $priorDailyNetMinutes = TimeEntry::withoutGlobalScopes([CompanyScope::class])
            ->where('employee_id', $entry->employee_id)
            ->where('id', '!=', $entry->id)
            ->where('date', $clockIn->toDateString())
            ->whereNotNull('clock_out')
            ->sum('net_hours') * 60;

        $weeklyLimitMinutes = $rules?->max_weekly_minutes ?? 2520;
        $dailyLimitMinutes = $rules?->max_daily_minutes ?? 480;
        // En modo semanal el tope diario queda inerte: solo el tope semanal dispara overtime.
        $weeklyAccrual = ($rules?->overtime_accrual_mode ?? 'daily') === 'weekly';

        $nightStartTime = $rules?->night_start_time ?? '21:00';
        $nightEndTime = $rules?->night_end_time ?? '06:00';
        [$nightStartHour, $nightStartMin] = array_map('intval', explode(':', $nightStartTime));
        [$nightEndHour, $nightEndMin] = array_map('intval', explode(':', $nightEndTime));
        $nightStartMinutes = $nightStartHour * 60 + $nightStartMin;
        $nightEndMinutes = $nightEndHour * 60 + $nightEndMin;

        $grossHours = (float) $entry->gross_hours;
        $netHours = (float) $entry->net_hours;
        $netRatio = $grossHours > 0 ? $netHours / $grossHours : 1.0;

        $holidayDates = $this->loadHolidayDates($entry->company_id, $clockIn);
        $dominicalWeekday = (int) ($rules?->dominical_weekday ?? 0);

        $buckets = [
            'regular' => 0.0,
            'night' => 0.0,
            'dominical' => 0.0,
            'night_dominical' => 0.0,
            'holiday' => 0.0,
            'night_holiday' => 0.0,
            'overtime_day' => 0.0,
            'overtime_night' => 0.0,
            'overtime_day_dominical' => 0.0,
            'overtime_night_dominical' => 0.0,
            'overtime_day_holiday' => 0.0,
            'overtime_night_holiday' => 0.0,
        ];

        // Dos acumuladores: semanal (corre toda la semana) y diario (se reinicia en medianoche).
        $accumulatedWeeklyNetMinutes = $priorWeeklyNetMinutes;
        $accumulatedDailyNetMinutes = $priorDailyNetMinutes;

        $breakpoints = $this->buildBreakpoints(
            $clockIn,
            $clockOut,
            $priorWeeklyNetMinutes,
            $weeklyLimitMinutes,
            $priorDailyNetMinutes,
            $dailyLimitMinutes,
            $netRatio,
            $nightStartTime,
            $nightEndTime,
            $weeklyAccrual,
        );

        // Precalcular qué breakpoints son medianoche para resetear el acumulador diario.
        $midnightBreakpoints = $this->buildMidnightSet($clockIn, $clockOut);

        for ($i = 0; $i < count($breakpoints) - 1; $i++) {
            $segStart = $breakpoints[$i];
            $segEnd = $breakpoints[$i + 1];

            // Si el inicio del segmento es una medianoche (y no es el clock_in original),
            // el acumulador diario se reinicia con las horas previas del nuevo día.
            if (isset($midnightBreakpoints[$segStart->toDateTimeString()])) {
                $accumulatedDailyNetMinutes = TimeEntry::withoutGlobalScopes([CompanyScope::class])
                    ->where('employee_id', $entry->employee_id)
                    ->where('id', '!=', $entry->id)
                    ->where('date', $segStart->toDateString())
                    ->whereNotNull('clock_out')
                    ->sum('net_hours') * 60;
            }

            $segGrossMinutes = $segStart->diffInSeconds($segEnd) / 60.0;
            $netContrib = $segGrossMinutes * $netRatio;

            $segMinuteOfDay = $segStart->hour * 60 + $segStart->minute;
            $isNight = $nightStartMinutes > $nightEndMinutes
                ? ($segMinuteOfDay >= $nightStartMinutes || $segMinuteOfDay < $nightEndMinutes)
                : ($segMinuteOfDay >= $nightStartMinutes && $segMinuteOfDay < $nightEndMinutes);
            // Festivo gana sobre dominical: un día que es ambos se cobra como festivo (siempre paga).
            $isHoliday = in_array($segStart->toDateString(), $holidayDates);
            $isDominical = ! $isHoliday && $segStart->dayOfWeek === $dominicalWeekday;

            // Modo semanal: solo el tope semanal dispara overtime (el diario queda inerte).
            // Modo diario: overtime si supera el límite diario o el semanal (lo que llegue primero).
            $isOvertime = $weeklyAccrual
                ? $accumulatedWeeklyNetMinutes >= $weeklyLimitMinutes
                : ($accumulatedDailyNetMinutes >= $dailyLimitMinutes
                    || $accumulatedWeeklyNetMinutes >= $weeklyLimitMinutes);

            match (true) {
                $isOvertime && $isHoliday && $isNight => $buckets['overtime_night_holiday'] += $netContrib,
                $isOvertime && $isHoliday => $buckets['overtime_day_holiday'] += $netContrib,
                $isOvertime && $isDominical && $isNight => $buckets['overtime_night_dominical'] += $netContrib,
                $isOvertime && $isDominical => $buckets['overtime_day_dominical'] += $netContrib,
                $isOvertime && $isNight => $buckets['overtime_night'] += $netContrib,
                $isOvertime => $buckets['overtime_day'] += $netContrib,
                $isHoliday && $isNight => $buckets['night_holiday'] += $netContrib,
                $isHoliday => $buckets['holiday'] += $netContrib,
                $isDominical && $isNight => $buckets['night_dominical'] += $netContrib,
                $isDominical => $buckets['dominical'] += $netContrib,
                $isNight => $buckets['night'] += $netContrib,
                default => $buckets['regular'] += $netContrib,
            };

            $accumulatedDailyNetMinutes += $netContrib;
            $accumulatedWeeklyNetMinutes += $netContrib;
        }

        $entry->update([
            'regular_hours' => round($buckets['regular'] / 60, 2),
            'night_hours' => round($buckets['night'] / 60, 2),
            'dominical_hours' => round($buckets['dominical'] / 60, 2),
            'night_dominical_hours' => round($buckets['night_dominical'] / 60, 2),
            'holiday_hours' => round($buckets['holiday'] / 60, 2),
            'night_holiday_hours' => round($buckets['night_holiday'] / 60, 2),
            'overtime_day_hours' => round($buckets['overtime_day'] / 60, 2),
            'overtime_night_hours' => round($buckets['overtime_night'] / 60, 2),
            'overtime_day_dominical_hours' => round($buckets['overtime_day_dominical'] / 60, 2),
            'overtime_night_dominical_hours' => round($buckets['overtime_night_dominical'] / 60, 2),
            'overtime_day_holiday_hours' => round($buckets['overtime_day_holiday'] / 60, 2),
            'overtime_night_holiday_hours' => round($buckets['overtime_night_holiday'] / 60, 2),
            'status' => 'calculated',
        ]);

        return $entry->fresh();
    }

    /**
     * Construye los puntos de corte donde la clasificación puede cambiar dentro del turno.
     *
     * Breakpoints incluidos:
     *   - clock_in y clock_out
     *   - 06:00 y 21:00 de cada día (límites nocturno/diurno)
     *   - 00:00 de cada día siguiente (cambio de día: domingo/festivo y reset del acumulador diario)
     *   - El momento exacto donde se agota el cupo semanal ordinario
     *   - El momento exacto donde se agota el cupo diario ordinario (uno por día calendario)
     *
     * @return Carbon[]
     */
    private function buildBreakpoints(
        Carbon $clockIn,
        Carbon $clockOut,
        float $priorWeeklyNetMinutes,
        float $weeklyLimitMinutes,
        float $priorDailyNetMinutes,
        float $dailyLimitMinutes,
        float $netRatio,
        string $nightStartTime = '21:00',
        string $nightEndTime = '06:00',
        bool $weeklyAccrual = false,
    ): array {
        $breakpoints = [$clockIn->copy(), $clockOut->copy()];

        $day = $clockIn->copy()->startOfDay();
        while ($day <= $clockOut) {
            foreach ([$nightEndTime, $nightStartTime] as $time) {
                [$h, $m] = explode(':', $time);
                $candidate = $day->copy()->setTime((int) $h, (int) $m);
                if ($candidate > $clockIn && $candidate < $clockOut) {
                    $breakpoints[] = $candidate;
                }
            }

            $midnight = $day->copy()->addDay()->startOfDay();
            if ($midnight > $clockIn && $midnight < $clockOut) {
                $breakpoints[] = $midnight;
            }

            $day->addDay();
        }

        // Breakpoint semanal: cuando se agota el cupo ordinario semanal.
        $remainingWeeklyToOvertime = $weeklyLimitMinutes - $priorWeeklyNetMinutes;
        if ($remainingWeeklyToOvertime > 0 && $netRatio > 0) {
            $grossSecondsToWeeklyOvertime = ($remainingWeeklyToOvertime / $netRatio) * 60;
            $weeklyOvertimeBp = $clockIn->copy()->addSeconds((int) round($grossSecondsToWeeklyOvertime));
            if ($weeklyOvertimeBp > $clockIn && $weeklyOvertimeBp < $clockOut) {
                $breakpoints[] = $weeklyOvertimeBp;
            }
        }

        // Breakpoints diarios: uno por cada día calendario dentro del turno.
        // El cupo diario se reinicia en cada medianoche. En modo semanal el tope diario
        // no clasifica overtime, así que estos breakpoints no se agregan.
        $currentDayPriorMinutes = $priorDailyNetMinutes;
        $day = $clockIn->copy()->startOfDay();

        while (! $weeklyAccrual && $day <= $clockOut) {
            $dayEnd = $day->copy()->addDay()->startOfDay();
            $segStart = $day <= $clockIn ? $clockIn->copy() : $day->copy();
            $segEnd = $dayEnd < $clockOut ? $dayEnd->copy() : $clockOut->copy();

            $remainingDailyToOvertime = $dailyLimitMinutes - $currentDayPriorMinutes;
            if ($remainingDailyToOvertime > 0 && $netRatio > 0) {
                $grossSecondsFromSegStart = ($remainingDailyToOvertime / $netRatio) * 60;
                $dailyOvertimeBp = $segStart->copy()->addSeconds((int) round($grossSecondsFromSegStart));
                if ($dailyOvertimeBp > $clockIn && $dailyOvertimeBp < $clockOut && $dailyOvertimeBp <= $segEnd) {
                    $breakpoints[] = $dailyOvertimeBp;
                }
            }

            // Para el siguiente día el acumulador diario arranca en 0
            // (horas previas del segundo día en otros turnos se cargan en el loop principal).
            $currentDayPriorMinutes = 0.0;
            $day->addDay();
        }

        usort($breakpoints, fn (Carbon $a, Carbon $b) => $a <=> $b);

        return array_values(array_unique($breakpoints));
    }

    /**
     * Construye un set de marcas de tiempo de medianoche que caen dentro del turno.
     * Usado en el loop para detectar cuándo reiniciar el acumulador diario.
     *
     * @return array<string, bool>
     */
    private function buildMidnightSet(Carbon $clockIn, Carbon $clockOut): array
    {
        $midnights = [];
        $day = $clockIn->copy()->startOfDay();
        while ($day <= $clockOut) {
            $midnight = $day->copy()->addDay()->startOfDay();
            if ($midnight > $clockIn && $midnight < $clockOut) {
                $midnights[$midnight->toDateTimeString()] = true;
            }
            $day->addDay();
        }

        return $midnights;
    }

    /**
     * Retorna un array con las fechas festivas de la empresa para el año del turno.
     *
     * @return array<string>
     */
    private function loadHolidayDates(int $companyId, Carbon $clockIn): array
    {
        return Holiday::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->map(fn (Holiday $holiday) => $holiday->is_recurring
                ? $clockIn->year.'-'.$holiday->date->format('m-d')
                : $holiday->date->toDateString()
            )
            ->toArray();
    }
}
