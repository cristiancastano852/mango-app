<?php

namespace App\Domain\Company\Observers;

use App\Domain\Company\Actions\SeedDefaultBreakTypes;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use Database\Seeders\ColombianHolidaysSeeder;

class CompanyObserver
{
    public function created(Company $company): void
    {
        $smlv = (float) config('payroll.smlv_monthly');
        $divisor = max((int) config('payroll.hourly_divisor'), 1);

        SurchargeRule::create([
            'company_id' => $company->id,
            'default_monthly_salary' => $smlv,
            'default_hourly_rate' => round($smlv / $divisor, 2),
            'transport_allowance' => (float) config('payroll.transport_allowance_monthly'),
        ]);
        (new ColombianHolidaysSeeder)->seedForCompany($company->id);
        (new SeedDefaultBreakTypes)->execute($company);
    }
}
