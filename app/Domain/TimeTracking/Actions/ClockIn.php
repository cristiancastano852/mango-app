<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Validation\ValidationException;

class ClockIn
{
    public function execute(Employee $employee): TimeEntry
    {
        $today = now()->toDateString();

        $existing = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        if ($existing && $existing->clock_in && ! $existing->clock_out) {
            throw ValidationException::withMessages([
                'clock_in' => 'Ya tienes un check-in activo.',
            ]);
        }

        if ($existing && $existing->clock_out) {
            throw ValidationException::withMessages([
                'clock_in' => 'Ya completaste tu jornada de hoy.',
            ]);
        }

        return TimeEntry::create([
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'date' => $today,
            'clock_in' => now(),
            'status' => 'pending',
        ]);
    }
}
