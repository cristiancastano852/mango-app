<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Company\Models\Holiday;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;

class CalculateWorkHours
{
    /**
     * Clasifica y distribuye los minutos trabajados en un TimeEntry en 4 tipos de hora:
     * regular, nocturna, dominical/festiva y extra. Luego actualiza el registro en BD.
     *
     * Flujo general:
     *   1. Validar que el turno esté completo (tiene clock_out y horas > 0).
     *   2. Cargar las reglas de recargo de la empresa y convertir tiempos a la zona horaria local.
     *   3. Calcular cuántos minutos netos ya acumuló el empleado en la semana (antes de este turno).
     *   4. Iterar minuto a minuto desde clock_in hasta clock_out, clasificando cada minuto.
     *   5. Guardar los totales clasificados en el TimeEntry.
     */
    public function execute(TimeEntry $entry): TimeEntry
    {
        // Si el turno no tiene hora de salida o no tiene horas brutas registradas,
        // no hay nada que calcular: se retorna el entry sin modificar.
        if (! $entry->clock_out || (float) $entry->gross_hours === 0.0) {
            return $entry;
        }

        $company = $entry->company;

        // Cargar las reglas de recargo de la empresa (porcentajes de hora nocturna,
        // dominical, extra, etc. y el límite semanal de horas ordinarias).
        // Se usa withoutGlobalScopes() para ignorar el scope de company_id global
        // y poder acceder a los datos sin restricciones del request actual.
        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $entry->company_id)
            ->firstOrFail();

        // Zona horaria de la empresa. Por defecto Colombia si no está configurada.
        // Es crítico convertir a zona horaria local para determinar correctamente
        // si un minuto es nocturno (21:00-06:00) o si cae en domingo/festivo.
        $tz = $company->timezone ?: 'America/Bogota';

        // Convertir clock_in y clock_out de UTC (como están en BD) a la zona horaria local.
        $clockIn = Carbon::parse($entry->clock_in)->setTimezone($tz);
        $clockOut = Carbon::parse($entry->clock_out)->setTimezone($tz);

        // Obtener el lunes de la semana en que ocurrió el clock_in.
        // Sirve como límite inferior para sumar horas previas de la semana.
        // LIMITACIÓN CONOCIDA: si un turno cruza el límite de semana (p.ej. domingo
        // 23:00 → lunes 01:00), todos los minutos del turno se contabilizan contra
        // la semana del clock_in (la semana anterior). Esto es intencional: el turno
        // se considera parte de la jornada que comenzó ese domingo.
        $weekStart = $clockIn->copy()->startOfWeek(Carbon::MONDAY);

        // Sumar todos los net_hours (horas netas = brutas - descansos) de los otros
        // turnos completados del mismo empleado en la misma semana (sin incluir este turno).
        // Multiplicar por 60 para trabajar en minutos en lugar de horas.
        // Esto es necesario para saber si el empleado ya superó su límite semanal
        // ANTES de que empiece este turno, lo que afecta cómo se clasifican sus minutos.
        $priorNetMinutes = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $entry->employee_id)
            ->where('id', '!=', $entry->id)                                          // excluir el turno actual
            ->whereBetween('date', [$weekStart->toDateString(), $clockIn->toDateString()])
            ->whereNotNull('clock_out')                                               // solo turnos completos
            ->sum('net_hours') * 60;

        // Límite semanal de horas ordinarias en minutos (ej: 47h → 2820 min).
        // Cuando el acumulado supere este valor, los minutos pasan a ser "extra".
        $weeklyLimitMinutes = $rules->max_weekly_hours * 60;

        // --- Cálculo del netRatio ---
        // gross_hours = tiempo total del turno (clock_out - clock_in), incluyendo pausas.
        // net_hours   = gross_hours - break_hours (solo tiempo efectivo trabajado).
        //
        // El loop itera minuto a minuto sobre el tiempo BRUTO (gross), pero cada minuto
        // bruto no equivale a un minuto neto completo si hubo pausas distribuidas.
        // netRatio escala la contribución de cada minuto bruto al acumulado neto.
        //
        // Ejemplo: turno de 9h brutas con 1h de pausa → net_hours=8, gross_hours=9
        //   netRatio = 8/9 ≈ 0.889
        //   Cada minuto del loop aporta 0.889 minutos netos al acumulado.
        $grossHours = (float) $entry->gross_hours;
        $netHours = (float) $entry->net_hours;
        $netRatio = $grossHours > 0 ? $netHours / $grossHours : 1.0;

        // Cargar las fechas festivas de la empresa para este año.
        // Se usan para identificar si un minuto cae en festivo (mismo tratamiento que domingo).
        $holidayDates = $this->loadHolidayDates($entry->company_id, $clockIn);

        // Acumuladores (en minutos netos) para cada tipo de hora.
        // Al final se dividen entre 60 para convertir a horas.
        $buckets = [
            'regular' => 0.0,  // Hora ordinaria diurna (lun-sáb, 06:00-21:00)
            'night' => 0.0,  // Hora ordinaria nocturna (21:00-06:00)
            'sunday_holiday' => 0.0,  // Hora en domingo o festivo
            'overtime' => 0.0,  // Hora extra (cuando se supera el límite semanal)
        ];

        // Minutos netos acumulados en la semana incluyendo los previos al turno actual.
        // Este contador sube con cada segmento del loop y determina cuándo se activa el overtime.
        $accumulatedNetMinutes = $priorNetMinutes;

        // Construir los puntos de corte donde puede cambiar la clasificación del segmento.
        $breakpoints = $this->buildBreakpoints($clockIn, $clockOut, $priorNetMinutes, $weeklyLimitMinutes, $netRatio);

        // --- Loop principal: clasificar cada segmento entre breakpoints consecutivos ---
        // Entre dos breakpoints, los tres flags (isNight, isSundayOrHoliday, isOvertime)
        // son constantes, por lo que el segmento completo va al mismo bucket.
        for ($i = 0; $i < count($breakpoints) - 1; $i++) {
            $segStart = $breakpoints[$i];
            $segEnd = $breakpoints[$i + 1];

            // Minutos brutos del segmento (puede ser fraccionario).
            $segGrossMinutes = $segStart->diffInSeconds($segEnd) / 60.0;

            // Contribución neta del segmento completo.
            $netContrib = $segGrossMinutes * $netRatio;

            // Clasificar usando el inicio del segmento (todos los minutos del segmento
            // comparten la misma clasificación por construcción de los breakpoints).
            $isNight = $segStart->hour >= 21 || $segStart->hour < 6;
            $isSundayOrHoliday = $segStart->dayOfWeek === Carbon::SUNDAY
                || in_array($segStart->toDateString(), $holidayDates);
            $isOvertime = $accumulatedNetMinutes >= $weeklyLimitMinutes;

            // Prioridad: overtime > dominical/festivo > nocturno > regular.
            match (true) {
                $isOvertime => $buckets['overtime'] += $netContrib,
                $isSundayOrHoliday => $buckets['sunday_holiday'] += $netContrib,
                $isNight => $buckets['night'] += $netContrib,
                default => $buckets['regular'] += $netContrib,
            };

            $accumulatedNetMinutes += $netContrib;
        }

        // Convertir los buckets de minutos a horas (÷ 60), redondear a 2 decimales
        // y guardar en la BD. También se marca el entry como 'calculated'.
        $entry->update([
            'regular_hours' => round($buckets['regular'] / 60, 2),
            'night_hours' => round($buckets['night'] / 60, 2),
            'sunday_holiday_hours' => round($buckets['sunday_holiday'] / 60, 2),
            'overtime_hours' => round($buckets['overtime'] / 60, 2),
            'status' => 'calculated',
        ]);

        // Retornar el entry recargado desde BD con los valores actualizados.
        return $entry->fresh();
    }

    /**
     * Construye los puntos de corte donde la clasificación puede cambiar dentro del turno.
     *
     * Los breakpoints son:
     *   - clock_in y clock_out (siempre incluidos)
     *   - 06:00 y 21:00 de cada día kalendario dentro del turno (límites nocturno/diurno)
     *   - 00:00 del día siguiente a cada día dentro del turno (cambio de día para domingo/festivo)
     *   - El momento exacto en que los minutos netos acumulados alcanzan el límite semanal
     *
     * @return Carbon[]
     */
    private function buildBreakpoints(
        Carbon $clockIn,
        Carbon $clockOut,
        float $priorNetMinutes,
        float $weeklyLimitMinutes,
        float $netRatio,
    ): array {
        $breakpoints = [$clockIn->copy(), $clockOut->copy()];

        // Iterar por cada día calendario cubierto por el turno.
        $day = $clockIn->copy()->startOfDay();
        while ($day <= $clockOut) {
            foreach (['06:00', '21:00'] as $time) {
                [$h, $m] = explode(':', $time);
                $candidate = $day->copy()->setTime((int) $h, (int) $m);
                if ($candidate > $clockIn && $candidate < $clockOut) {
                    $breakpoints[] = $candidate;
                }
            }

            // Medianoche del día siguiente: cambio de día para detección de domingo/festivo.
            $midnight = $day->copy()->addDay()->startOfDay();
            if ($midnight > $clockIn && $midnight < $clockOut) {
                $breakpoints[] = $midnight;
            }

            $day->addDay();
        }

        // Breakpoint de overtime: momento exacto en que se agota el cupo ordinario semanal.
        $remainingToOvertime = $weeklyLimitMinutes - $priorNetMinutes;
        if ($remainingToOvertime > 0 && $netRatio > 0) {
            $grossSecondsToOvertime = ($remainingToOvertime / $netRatio) * 60;
            $overtimeBp = $clockIn->copy()->addSeconds((int) round($grossSecondsToOvertime));
            if ($overtimeBp > $clockIn && $overtimeBp < $clockOut) {
                $breakpoints[] = $overtimeBp;
            }
        }

        // Ordenar cronológicamente y eliminar duplicados exactos.
        usort($breakpoints, fn (Carbon $a, Carbon $b) => $a <=> $b);

        return array_values(array_unique($breakpoints));
    }

    /**
     * Retorna un array con las fechas festivas de la empresa para el año del turno.
     *
     * Los festivos recurrentes (ej: 1 de enero) se ajustan al año del clock_in,
     * porque en BD pueden estar guardados con cualquier año de referencia.
     * Los festivos no recurrentes se usan con su fecha exacta tal como están en BD.
     *
     * @return array<string> Array de fechas en formato 'Y-m-d', ej: ['2026-01-01', '2026-05-01']
     */
    private function loadHolidayDates(int $companyId, Carbon $clockIn): array
    {
        return Holiday::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->map(fn (Holiday $holiday) => $holiday->is_recurring
                // Festivo recurrente: reemplazar el año por el del turno actual.
                // Ej: BD tiene '2020-12-25' → se convierte a '2026-12-25'
                ? $clockIn->year.'-'.$holiday->date->format('m-d')
                // Festivo no recurrente: usar la fecha exacta de la BD.
                : $holiday->date->toDateString()
            )
            ->toArray();
    }
}
