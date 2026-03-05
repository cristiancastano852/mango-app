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
                'employee_code' => $data['employee_code'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? null,
                'salary_type' => $data['salary_type'] ?? 'hourly',
                'schedule_id' => $data['schedule_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
            ]);

            return $employee->fresh(['user', 'department', 'position', 'schedule', 'location']);
        });
    }
}
