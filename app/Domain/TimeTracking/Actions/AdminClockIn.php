<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AdminClockIn
{
    public function execute(Employee $employee, ?Carbon $clockInTime = null): TimeEntry
    {
        $today = now()->toDateString();

        $existing = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        if ($existing && $existing->clock_in && ! $existing->clock_out) {
            throw ValidationException::withMessages([
                'employee_id' => __('messages.already_clocked_in'),
            ]);
        }

        if ($existing && $existing->clock_out) {
            throw ValidationException::withMessages([
                'employee_id' => __('messages.shift_already_completed'),
            ]);
        }

        return TimeEntry::create([
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'date' => $today,
            'clock_in' => $clockInTime ?? now(),
            'status' => 'pending',
            'pin_verified' => false,
        ]);
    }
}
