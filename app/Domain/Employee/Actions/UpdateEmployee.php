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

            $salaryType = $data['salary_type'] ?? 'hourly';

            // El auxilio solo aplica en modo monthly: se apaga al pasar a hourly; en monthly
            // se respeta el valor enviado o se preserva el actual.
            $receivesTransportAllowance = $salaryType === 'monthly'
                ? (array_key_exists('receives_transport_allowance', $data)
                    ? $data['receives_transport_allowance']
                    : $employee->receives_transport_allowance)
                : false;

            $employee->update([
                // Only update when explicitly present in payload; otherwise preserve existing assignment.
                'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $employee->department_id,
                'position_id' => array_key_exists('position_id', $data) ? $data['position_id'] : $employee->position_id,
                'document_number' => $data['document_number'],
                'hire_date' => $data['hire_date'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? null,
                'salary_type' => $salaryType,
                'monthly_base_salary' => array_key_exists('monthly_base_salary', $data) ? $data['monthly_base_salary'] : $employee->monthly_base_salary,
                'receives_transport_allowance' => $receivesTransportAllowance,
                'dominical_payment_mode' => array_key_exists('dominical_payment_mode', $data) ? $data['dominical_payment_mode'] : $employee->dominical_payment_mode,
                'dominical_day_value' => array_key_exists('dominical_day_value', $data) ? $data['dominical_day_value'] : $employee->dominical_day_value,
                'schedule_id' => array_key_exists('schedule_id', $data) ? $data['schedule_id'] : $employee->schedule_id,
                'location_id' => array_key_exists('location_id', $data) ? $data['location_id'] : $employee->location_id,
            ]);

            return $employee->fresh(['user', 'department', 'position', 'schedule', 'location']);
        });
    }
}
