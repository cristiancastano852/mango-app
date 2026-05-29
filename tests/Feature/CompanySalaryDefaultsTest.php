<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySalaryDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_company_seeds_salary_defaults_from_smlv(): void
    {
        config()->set('payroll.smlv_monthly', 1750905);
        config()->set('payroll.hourly_divisor', 220);

        $company = Company::create([
            'name' => 'Acme SAS',
            'slug' => 'acme-sas',
        ]);

        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->firstOrFail();

        $this->assertEquals(1750905.0, (float) $rules->default_monthly_salary);
        // 1.750.905 / 220 = 7958.66 (redondeado a 2 decimales)
        $this->assertEquals(7958.66, (float) $rules->default_hourly_rate);
    }

    public function test_salary_defaults_follow_the_configured_smlv(): void
    {
        config()->set('payroll.smlv_monthly', 2000000);
        config()->set('payroll.hourly_divisor', 220);

        $company = Company::create([
            'name' => 'Beta SAS',
            'slug' => 'beta-sas',
        ]);

        $rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->firstOrFail();

        $this->assertEquals(2000000.0, (float) $rules->default_monthly_salary);
        // 2.000.000 / 220 = 9090.91
        $this->assertEquals(9090.91, (float) $rules->default_hourly_rate);
    }
}
