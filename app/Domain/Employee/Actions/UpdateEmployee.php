<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Employee\Models\Employee;
use Illuminate\Support\Facades\DB;

class UpdateEmployee
{
    public function execute(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $employee->update([
                'department_id' => $data['department_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'document_number' => $data['document_number'],
                'hire_date' => $data['hire_date'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? null,
                'salary_type' => $data['salary_type'] ?? 'hourly',
                // Only update schedule_id when explicitly present in payload; otherwise preserve existing assignment.
                'schedule_id' => array_key_exists('schedule_id', $data) ? $data['schedule_id'] : $employee->schedule_id,
                'location_id' => $data['location_id'] ?? null,
            ]);

            return $employee->fresh(['user', 'department', 'position', 'schedule', 'location']);
        });
    }
}
