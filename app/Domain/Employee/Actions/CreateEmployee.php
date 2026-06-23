<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
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

            $defaults = $this->companyDefaults($companyId);
            $salaryType = $data['salary_type'] ?? 'hourly';

            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'department_id' => $data['department_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? $defaults?->default_hourly_rate,
                'monthly_base_salary' => $salaryType === 'monthly'
                    ? ($data['monthly_base_salary'] ?? $defaults?->default_monthly_salary)
                    : ($data['monthly_base_salary'] ?? null),
                'salary_type' => $salaryType,
                // Solo aplica en modo monthly; default ON cuando no se especifica.
                'receives_transport_allowance' => $salaryType === 'monthly'
                    ? ($data['receives_transport_allowance'] ?? true)
                    : false,
                'dominical_payment_mode' => $data['dominical_payment_mode'] ?? $defaults?->default_dominical_payment_mode ?? 'hour',
                'dominical_day_value' => $data['dominical_day_value'] ?? $defaults?->default_dominical_day_value ?? 0,
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

    private function companyDefaults(int $companyId): ?SurchargeRule
    {
        return SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();
    }
}
