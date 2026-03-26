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
    /**
     * @return array{employee: Employee, plain_password: string}
     */
    public function execute(array $data, int $companyId): array
    {
        return DB::transaction(function () use ($data, $companyId) {
            $plainPassword = $data['password'] ?? Str::random(16);

            $user = User::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'is_active' => true,
            ]);

            $user->assignRole('employee');

            $employee = Employee::create([
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

            return ['employee' => $employee, 'plain_password' => $plainPassword];
        });
    }

    private function getDefaultScheduleId(int $companyId): ?int
    {
        $company = Company::find($companyId);

        return $company?->settings['default_schedule_id'] ?? null;
    }
}
