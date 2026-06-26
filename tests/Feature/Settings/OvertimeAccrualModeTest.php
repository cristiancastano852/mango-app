<?php

namespace Tests\Feature\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeAccrualModeTest extends TestCase
{
    use RefreshDatabase;

    private function ruleFor(Company $company): SurchargeRule
    {
        return SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->firstOrFail();
    }

    public function test_new_company_defaults_to_daily_accrual_mode(): void
    {
        $company = Company::create(['name' => 'Fresh Co', 'slug' => 'fresh-co']);

        $this->assertSame('daily', $this->ruleFor($company)->overtime_accrual_mode);
    }

    public function test_existing_company_without_value_falls_back_to_daily(): void
    {
        // El observer crea la regla; la columna nace con el default de la migración.
        $company = Company::create(['name' => 'Legacy Co', 'slug' => 'legacy-co']);

        $this->assertSame('daily', $this->ruleFor($company)->overtime_accrual_mode);
    }

    public function test_weekly_factory_state_sets_weekly_mode(): void
    {
        $rule = SurchargeRule::factory()->weekly()->make();

        $this->assertSame('weekly', $rule->overtime_accrual_mode);
    }
}
