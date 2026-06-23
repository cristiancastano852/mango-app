<?php

namespace Database\Seeders;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\CalculateWorkHours;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class ReportDemoSeeder extends Seeder
{
    /**
     * Genera datos de asistencia del último mes para los 5 empleados demo.
     *
     * Perfiles y tipos de hora que ejercitan:
     *   - María García  (Chef, turno mañana)    → ordinaria
     *   - Ana López     (Mesera, turno tarde)    → ordinaria + nocturna
     *   - Pedro Martínez(Mesero, turno tarde)    → ordinaria + nocturna + dominical diurna
     *   - Juan Pérez    (Cocinero, jornadas largas) → ordinaria + extra_diurna + extra_nocturna
     *   - Laura Rodríguez(Cajera, domingos extendidos) → dominical + noc_dominical + extra_dom_diurna + extra_dom_nocturna
     */
    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()
            ->with('user', 'schedule')
            ->whereHas('user', fn ($q) => $q->whereIn('email', [
                'maria@elmango.co',
                'ana@elmango.co',
                'pedro@elmango.co',
                'juan@elmango.co',
                'laura@elmango.co',
            ]))
            ->get()
            ->keyBy(fn ($e) => $e->user->email);

        if ($employees->count() < 5) {
            $this->command->error('Ejecuta DemoSeeder primero: php artisan db:seed --class=DemoSeeder');

            return;
        }

        $employees['maria@elmango.co']->update(['hourly_rate' => 15000]);
        $employees['ana@elmango.co']->update(['hourly_rate' => 10000]);
        $employees['pedro@elmango.co']->update(['hourly_rate' => 10000]);
        $employees['juan@elmango.co']->update(['hourly_rate' => 12000]);
        $employees['laura@elmango.co']->update(['hourly_rate' => 11000]);

        $companyId = $employees['maria@elmango.co']->company_id;

        $breakTypes = BreakType::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('slug');

        $calculator = app(CalculateWorkHours::class);

        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->subDay()->endOfDay();

        $this->command->info("Generando entries desde {$startDate->toDateString()} hasta {$endDate->toDateString()}...");

        $profiles = [
            // Tipo de hora principal que ejercita cada perfil
            'maria@elmango.co' => 'morning_regular',     // ordinaria
            'ana@elmango.co' => 'afternoon_night',      // ordinaria + nocturna
            'pedro@elmango.co' => 'weekend_overtime',     // ordinaria + nocturna + dominical_diurna
            'juan@elmango.co' => 'weekday_overtime',     // ordinaria + extra_diurna + extra_nocturna
            'laura@elmango.co' => 'sunday_extended',      // dominical + noc_dominical + extra_dom_diurna + extra_dom_nocturna
        ];

        foreach ($profiles as $email => $profile) {
            $this->generateEntries(
                employee: $employees[$email],
                companyId: $companyId,
                startDate: $startDate->copy(),
                endDate: $endDate->copy(),
                profile: $profile,
                breakTypes: $breakTypes,
                calculator: $calculator,
            );
        }

        $totalEntries = TimeEntry::withoutGlobalScopes()->where('company_id', $companyId)->count();
        $this->command->info("Listo! {$totalEntries} time entries creados.");
    }

    private function generateEntries(
        Employee $employee,
        int $companyId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        string $profile,
        $breakTypes,
        CalculateWorkHours $calculator,
    ): void {
        $date = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $count = 0;

        while ($date <= $end) {
            if ($this->shouldWorkThisDay($date, $profile)) {
                $entry = $this->createDayEntry($employee, $companyId, $date, $profile, $breakTypes);
                if ($entry) {
                    $calculator->execute($entry);
                    $count++;
                }
            }

            $date->addDay();
        }

        $this->command->info("  {$employee->user->name} [{$profile}]: {$count} días trabajados");
    }

    private function shouldWorkThisDay(Carbon $date, string $profile): bool
    {
        $dow = $date->dayOfWeek; // 0=dom, 1=lun … 6=sáb

        return match ($profile) {
            // María: lun-sáb, descansa domingos, falta ~7% de días
            'morning_regular' => $dow !== Carbon::SUNDAY && rand(1, 100) > 7,
            // Ana: lun-sáb, descansa domingos
            'afternoon_night' => $dow !== Carbon::SUNDAY && rand(1, 100) > 5,
            // Pedro: lun-dom excepto miércoles (trabaja domingos)
            'weekend_overtime' => $dow !== Carbon::WEDNESDAY && rand(1, 100) > 5,
            // Juan: lun-vie, jornadas largas que generan extras
            'weekday_overtime' => $dow >= Carbon::MONDAY && $dow <= Carbon::FRIDAY && rand(1, 100) > 5,
            // Laura: lun-jue + domingos (4 días semana para no agotar límite semanal antes del domingo)
            'sunday_extended' => ($dow >= Carbon::MONDAY && $dow <= Carbon::THURSDAY) || $dow === Carbon::SUNDAY,
        };
    }

    private function createDayEntry(
        Employee $employee,
        int $companyId,
        Carbon $date,
        string $profile,
        $breakTypes,
    ): ?TimeEntry {
        $exists = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->exists();

        if ($exists) {
            return null;
        }

        [$clockInTime, $clockOutTime, $breakMinutes] = $this->getScheduleForDay($date, $profile);

        $tz = 'America/Bogota';
        $clockIn = Carbon::parse($date->toDateString().' '.$clockInTime, $tz)->setTimezone('UTC');
        $clockOut = Carbon::parse($date->toDateString().' '.$clockOutTime, $tz)->setTimezone('UTC');

        if ($clockOut <= $clockIn) {
            $clockOut->addDay();
        }

        $grossMinutes = $clockIn->diffInMinutes($clockOut);
        $grossHours = round($grossMinutes / 60, 4);
        $breakHours = round($breakMinutes / 60, 4);
        $netHours = round($grossHours - $breakHours, 4);

        $entry = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'company_id' => $companyId,
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'gross_hours' => $grossHours,
            'break_hours' => $breakHours,
            'net_hours' => $netHours,
            'regular_hours' => 0,
            'night_hours' => 0,
            'dominical_hours' => 0,
            'night_dominical_hours' => 0,
            'holiday_hours' => 0,
            'night_holiday_hours' => 0,
            'overtime_day_hours' => 0,
            'overtime_night_hours' => 0,
            'overtime_day_dominical_hours' => 0,
            'overtime_night_dominical_hours' => 0,
            'overtime_day_holiday_hours' => 0,
            'overtime_night_holiday_hours' => 0,
            'status' => 'pending',
            'pin_verified' => true,
        ]);

        $this->createBreaks($entry, $employee, $companyId, $clockIn, $breakMinutes, $breakTypes, $profile);

        return $entry;
    }

    /**
     * Retorna [clock_in_str, clock_out_str, break_minutes] según perfil y día.
     *
     * Tipos de hora que genera cada perfil:
     *   morning_regular  → ordinaria (sin extras)
     *   afternoon_night  → ordinaria + nocturna (22:00-23:00)
     *   weekend_overtime → ordinaria + nocturna + dominical_diurna (domingos 10:00-18:00)
     *   weekday_overtime → ordinaria + [extra_diurna] + [extra_nocturna]
     *   sunday_extended  → dominical_diurna + [noc_dominical|extra_dom_diurna + extra_dom_nocturna]
     *
     * @return array{string, string, int}
     */
    private function getScheduleForDay(Carbon $date, string $profile): array
    {
        $dow = $date->dayOfWeek;
        $jitter = rand(-10, 15);

        return match ($profile) {

            // María: 07:00–15:00, rara vez se queda hasta 15:30
            'morning_regular' => [
                $this->shiftTime('07:00', $jitter),
                $this->shiftTime('15:00', rand(0, 100) > 80 ? rand(15, 45) : rand(0, 10)),
                60,
            ],

            // Ana: 14:00–22:00, 40% de días se queda hasta 22:30-23:00 (nocturnas)
            'afternoon_night' => [
                $this->shiftTime('14:00', $jitter),
                $this->shiftTime('22:00', rand(0, 100) > 60 ? rand(20, 60) : rand(0, 10)),
                60,
            ],

            // Pedro: semana 14:00-22:30 (nocturnas), domingo 10:00-18:00 (dominical)
            'weekend_overtime' => $dow === Carbon::SUNDAY
                ? [$this->shiftTime('10:00', $jitter), $this->shiftTime('18:00', rand(0, 30)), 45]
                : [$this->shiftTime('14:00', $jitter), $this->shiftTime('22:00', rand(20, 60)), 60],

            // Juan: jornadas largas que superan el límite diario de 8h netas
            //   60% → 06:00-15:00 (8h netas, sin extras)
            //   30% → 06:00-18:00 (11h netas = 8h ordinaria + 3h extra_diurna)
            //   10% → 06:00-22:30 (15.5h netas = 8h ordinaria + 7h extra_diurna + 0.5h extra_nocturna)
            'weekday_overtime' => (function () use ($jitter): array {
                $roll = rand(1, 100);
                if ($roll <= 60) {
                    // Turno normal: ordinaria pura
                    return [$this->shiftTime('06:00', $jitter), $this->shiftTime('15:00', rand(0, 15)), 60];
                } elseif ($roll <= 90) {
                    // Jornada larga: extra_diurna
                    return [$this->shiftTime('06:00', $jitter), $this->shiftTime('18:00', rand(0, 20)), 60];
                } else {
                    // Jornada muy larga: extra_diurna + extra_nocturna (pasa las 21:00 con límite ya agotado)
                    return [$this->shiftTime('06:00', $jitter), $this->shiftTime('22:30', rand(0, 30)), 60];
                }
            })(),

            // Laura:
            //   Lun-Jue: 08:00-17:00 (8h netas, ordinaria) — acumula 4×8=32h semanales
            //   Domingo impar: 09:00-23:00 (13h netas = dominical + extra_dom_diurna + extra_dom_nocturna)
            //                   Aquí el semanal (32+13=45h > 42h) pero el diario (8h) dispara antes.
            //   Domingo par: 19:00-23:30 (4.5h netas, sin break = noc_dominical pura)
            //                 Semanal = 32+4.5=36.5h < 42h → sin extras → night_sunday
            'sunday_extended' => $dow !== Carbon::SUNDAY
                ? [$this->shiftTime('08:00', $jitter), $this->shiftTime('17:00', rand(0, 20)), 60]
                : ($date->weekOfYear % 2 === 0
                    // Domingo par → nocturna dominical pura (19:00-23:30, sin pausa)
                    ? ['19:00', '23:30', 0]
                    // Domingo impar → turno largo (dominical + extra dom diurna + extra dom nocturna)
                    : ['09:00', '23:00', 60]
                ),
        };
    }

    private function shiftTime(string $base, int $minutes): string
    {
        return Carbon::parse($base)->addMinutes($minutes)->format('H:i');
    }

    private function createBreaks(
        TimeEntry $entry,
        Employee $employee,
        int $companyId,
        Carbon $clockInUtc,
        int $totalBreakMinutes,
        $breakTypes,
        string $profile,
    ): void {
        if ($totalBreakMinutes === 0) {
            return;
        }

        // Almuerzo (siempre que haya pausa programada)
        $lunchType = $breakTypes->get('almuerzo');
        if ($lunchType) {
            $hoursUntilLunch = match ($profile) {
                'morning_regular', 'weekday_overtime', 'sunday_extended' => 4,
                default => 4,
            };
            $lunchStart = $clockInUtc->copy()->addHours($hoursUntilLunch)->addMinutes(rand(0, 15));
            $lunchDuration = min($totalBreakMinutes, rand(45, 60));

            BreakEntry::withoutGlobalScopes()->create([
                'time_entry_id' => $entry->id,
                'employee_id' => $employee->id,
                'company_id' => $companyId,
                'break_type_id' => $lunchType->id,
                'started_at' => $lunchStart,
                'ended_at' => $lunchStart->copy()->addMinutes($lunchDuration),
                'duration_minutes' => $lunchDuration,
            ]);
        }

        // Descanso corto (50% de las veces)
        $breakType = $breakTypes->get('descanso');
        if ($breakType && rand(1, 100) > 50) {
            $breakStart = $clockInUtc->copy()->addHours(rand(2, 3));
            $breakDuration = rand(8, 15);
            BreakEntry::withoutGlobalScopes()->create([
                'time_entry_id' => $entry->id,
                'employee_id' => $employee->id,
                'company_id' => $companyId,
                'break_type_id' => $breakType->id,
                'started_at' => $breakStart,
                'ended_at' => $breakStart->copy()->addMinutes($breakDuration),
                'duration_minutes' => $breakDuration,
            ]);
        }

        // Baño (30% de las veces)
        $banoType = $breakTypes->get('bano');
        if ($banoType && rand(1, 100) > 70) {
            $banoStart = $clockInUtc->copy()->addHours(rand(5, 6))->addMinutes(rand(0, 30));
            $banoDuration = rand(3, 8);
            BreakEntry::withoutGlobalScopes()->create([
                'time_entry_id' => $entry->id,
                'employee_id' => $employee->id,
                'company_id' => $companyId,
                'break_type_id' => $banoType->id,
                'started_at' => $banoStart,
                'ended_at' => $banoStart->copy()->addMinutes($banoDuration),
                'duration_minutes' => $banoDuration,
            ]);
        }
    }
}
