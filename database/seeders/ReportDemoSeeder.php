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
     * Genera datos de asistencia del último mes para 3 empleados.
     *
     * Perfiles:
     *   - María García (Chef, turno mañana 07:00-15:00): horario regular, pocas extras
     *   - Ana López (Mesera, turno tarde 14:00-22:00): tiene horas nocturnas (21:00-22:00)
     *   - Pedro Martínez (Mesero, turno tarde): extras los fines de semana, horas dominicales
     */
    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()
            ->with('user', 'schedule')
            ->whereHas('user', fn ($q) => $q->whereIn('email', [
                'maria@elmango.co',
                'ana@elmango.co',
                'pedro@elmango.co',
            ]))
            ->get()
            ->keyBy(fn ($e) => $e->user->email);

        if ($employees->count() < 3) {
            $this->command->error('Ejecuta DemoSeeder primero: php artisan db:seed --class=DemoSeeder');

            return;
        }

        // Asignar tarifas por hora
        $employees['maria@elmango.co']->update(['hourly_rate' => 15000]);
        $employees['ana@elmango.co']->update(['hourly_rate' => 10000]);
        $employees['pedro@elmango.co']->update(['hourly_rate' => 10000]);

        $companyId = $employees['maria@elmango.co']->company_id;

        // Obtener break types
        $breakTypes = BreakType::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('slug');

        $calculator = app(CalculateWorkHours::class);

        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->subDay()->endOfDay();

        $this->command->info("Generando entries desde {$startDate->toDateString()} hasta {$endDate->toDateString()}...");

        // --- María: turno mañana regular, lun-sáb ---
        $maria = $employees['maria@elmango.co'];
        $this->generateEntries(
            employee: $maria,
            companyId: $companyId,
            startDate: $startDate->copy(),
            endDate: $endDate->copy(),
            profile: 'morning_regular',
            breakTypes: $breakTypes,
            calculator: $calculator,
        );

        // --- Ana: turno tarde con horas nocturnas ---
        $ana = $employees['ana@elmango.co'];
        $this->generateEntries(
            employee: $ana,
            companyId: $companyId,
            startDate: $startDate->copy(),
            endDate: $endDate->copy(),
            profile: 'afternoon_night',
            breakTypes: $breakTypes,
            calculator: $calculator,
        );

        // --- Pedro: turno tarde + extras fines de semana ---
        $pedro = $employees['pedro@elmango.co'];
        $this->generateEntries(
            employee: $pedro,
            companyId: $companyId,
            startDate: $startDate->copy(),
            endDate: $endDate->copy(),
            profile: 'weekend_overtime',
            breakTypes: $breakTypes,
            calculator: $calculator,
        );

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
            $shouldWork = $this->shouldWorkThisDay($date, $profile, $employee);

            if ($shouldWork) {
                $entry = $this->createDayEntry($employee, $companyId, $date, $profile, $breakTypes);
                if ($entry) {
                    $calculator->execute($entry);
                    $count++;
                }
            }

            $date->addDay();
        }

        $this->command->info("  {$employee->user->name}: {$count} días trabajados");
    }

    private function shouldWorkThisDay(Carbon $date, string $profile, Employee $employee): bool
    {
        $dow = $date->dayOfWeek; // 0=dom, 6=sáb

        return match ($profile) {
            // María: lun-sáb, descansa domingos, falta aleatoriamente 2 días/mes
            'morning_regular' => $dow !== Carbon::SUNDAY && rand(1, 100) > 7,
            // Ana: lun-sáb, descansa domingos
            'afternoon_night' => $dow !== Carbon::SUNDAY && rand(1, 100) > 5,
            // Pedro: lun-dom (trabaja domingos!), descansa miércoles
            'weekend_overtime' => $dow !== Carbon::WEDNESDAY && rand(1, 100) > 5,
        };
    }

    private function createDayEntry(
        Employee $employee,
        int $companyId,
        Carbon $date,
        string $profile,
        $breakTypes,
    ): ?TimeEntry {
        // Evitar duplicados
        $exists = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->exists();

        if ($exists) {
            return null;
        }

        [$clockInTime, $clockOutTime, $breakMinutes] = $this->getScheduleForDay($date, $profile);

        // Crear tiempos en zona horaria Colombia y convertir a UTC para storage
        $tz = 'America/Bogota';
        $clockIn = Carbon::parse($date->toDateString().' '.$clockInTime, $tz)->setTimezone('UTC');
        $clockOut = Carbon::parse($date->toDateString().' '.$clockOutTime, $tz)->setTimezone('UTC');

        // Si clock_out cruza medianoche (ej: sale a las 23:30 COT)
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
            'overtime_hours' => 0,
            'sunday_holiday_hours' => 0,
            'status' => 'pending',
            'pin_verified' => true,
        ]);

        // Crear registros de breaks
        $this->createBreaks($entry, $employee, $companyId, $clockIn, $breakMinutes, $breakTypes, $profile);

        return $entry;
    }

    /**
     * Retorna [clock_in, clock_out, break_minutes] según el perfil y día.
     * Agrega variación realista: llegar un poco antes/después, quedarse extra.
     *
     * @return array{string, string, int}
     */
    private function getScheduleForDay(Carbon $date, string $profile): array
    {
        $dow = $date->dayOfWeek;
        $variance = rand(-10, 15); // Minutos de variación en llegada

        return match ($profile) {
            // María: 07:00-15:00, a veces se queda hasta 15:30-16:00
            'morning_regular' => [
                $this->addMinutesToTime('07:00', $variance),
                $this->addMinutesToTime('15:00', rand(0, 30) > 20 ? rand(15, 60) : rand(0, 10)),
                60, // 1h almuerzo
            ],

            // Ana: 14:00-22:00, a veces hasta 22:30-23:00 (más horas nocturnas)
            'afternoon_night' => [
                $this->addMinutesToTime('14:00', $variance),
                $this->addMinutesToTime('22:00', rand(0, 100) > 40 ? rand(15, 60) : rand(0, 10)),
                60,
            ],

            // Pedro: 14:00-22:00 entre semana, domingos 10:00-18:00
            'weekend_overtime' => $dow === Carbon::SUNDAY
                ? [
                    $this->addMinutesToTime('10:00', $variance),
                    $this->addMinutesToTime('18:00', rand(0, 30)),
                    45,
                ]
                : [
                    $this->addMinutesToTime('14:00', $variance),
                    $this->addMinutesToTime('22:00', rand(0, 100) > 50 ? rand(20, 90) : rand(0, 15)),
                    60,
                ],
        };
    }

    private function addMinutesToTime(string $time, int $minutes): string
    {
        return Carbon::parse($time)->addMinutes($minutes)->format('H:i');
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
        // clockInUtc ya está en UTC, los offsets relativos funcionan igual
        // Almuerzo (siempre)
        $lunchType = $breakTypes->get('almuerzo');
        if ($lunchType) {
            $lunchStart = match ($profile) {
                'morning_regular' => $clockInUtc->copy()->addHours(5)->addMinutes(rand(0, 15)),
                default => $clockInUtc->copy()->addHours(4)->addMinutes(rand(0, 15)),
            };
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
