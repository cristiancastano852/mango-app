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
        // Este contador sube con cada minuto del loop y determina cuándo se activa el overtime.
        $accumulatedNetMinutes = $priorNetMinutes;

        // Puntero de tiempo que avanza minuto a minuto desde clock_in hasta clock_out.
        $current = $clockIn->copy();

        // --- Loop principal: clasificar cada minuto del turno ---
        while ($current < $clockOut) {

            // ¿El minuto actual es nocturno?
            // Franja nocturna en Colombia: 21:00 a 06:00 del día siguiente.
            $isNight = $current->hour >= 21 || $current->hour < 6;

            // ¿Es domingo o festivo?
            // dayOfWeek === 0 en Carbon es domingo. Los festivos se comparan por fecha string.
            $isSundayOrHoliday = $current->dayOfWeek === Carbon::SUNDAY
                || in_array($current->toDateString(), $holidayDates);

            // ¿Ya se superó el límite semanal de horas ordinarias?
            // Si los minutos netos acumulados llegan al límite, todo lo que sigue es extra.
            $isOvertime = $accumulatedNetMinutes >= $weeklyLimitMinutes;

            // Contribución neta de este minuto (fracción del minuto bruto que es tiempo real).
            $netContrib = 1.0 * $netRatio;

            // Prioridad de clasificación: overtime > dominical/festivo > nocturno > regular.
            // El overtime tiene prioridad absoluta: si ya se superó la semana, no importa
            // si es de noche o domingo, todo va a overtime.
            if ($isOvertime) {
                $buckets['overtime'] += $netContrib;
            } elseif ($isSundayOrHoliday) {
                $buckets['sunday_holiday'] += $netContrib;
            } elseif ($isNight) {
                $buckets['night'] += $netContrib;
            } else {
                $buckets['regular'] += $netContrib;
            }

            // Actualizar el acumulado semanal con la contribución de este minuto.
            $accumulatedNetMinutes += $netContrib;

            // Avanzar al siguiente minuto.
            $current->addMinute();
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
