<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateEmployee
{
    public function execute(array $data, int $companyId): Employee
    {
        return DB::transaction(function () use ($data, $companyId) {
            $user = User::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make(Str::random(16)),
                'is_active' => true,
            ]);

            $user->assignRole('employee');

            return Employee::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'department_id' => $data['department_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'employee_code' => $data['employee_code'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? null,
                'salary_type' => $data['salary_type'] ?? 'hourly',
                'schedule_id' => $data['schedule_id'] ?? $this->getDefaultScheduleId($companyId),
                'location_id' => $data['location_id'] ?? null,
            ]);
        });
    }

    private function getDefaultScheduleId(int $companyId): ?int
    {
        $company = Company::find($companyId);

        return $company?->settings['default_schedule_id'] ?? null;
    }
}
