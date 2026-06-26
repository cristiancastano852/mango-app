<?php

namespace Tests\Feature\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PremiumSurchargeToggleMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Réplica del paso de datos de la migración add_premium_surcharge_toggles_to_surcharge_rules:
     * siembra pay_night_dominical/pay_overtime_dominical desde pay_dominical_by_default.
     */
    private function seedFromDominicalDefault(): void
    {
        DB::table('surcharge_rules')->update([
            'pay_night_dominical' => DB::raw('pay_dominical_by_default'),
            'pay_overtime_dominical' => DB::raw('pay_dominical_by_default'),
        ]);
    }

    private function ruleFor(Company $company): SurchargeRule
    {
        return SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->firstOrFail();
    }

    public function test_company_that_did_not_pay_dominicals_keeps_collapsed_premiums(): void
    {
        // El observer crea el surcharge_rule con defaults (todo true); simulamos el estado previo
        // al sembrado: la empresa NO pagaba dominicales y las columnas nuevas nacieron en true.
        $company = Company::create(['name' => 'No Dominical Co', 'slug' => 'no-dominical-co']);
        $this->ruleFor($company)->update([
            'pay_dominical_by_default' => false,
            'pay_night_dominical' => true,
            'pay_overtime_dominical' => true,
            'pay_night_holiday' => true,
            'pay_overtime_holiday' => true,
        ]);

        $this->seedFromDominicalDefault();
        $rule = $this->ruleFor($company);

        // Dominical apagado → noche y extra dominical también, preservando lo que pagaba antes.
        $this->assertFalse($rule->pay_night_dominical);
        $this->assertFalse($rule->pay_overtime_dominical);
        // Los festivos siempre se pagaron → quedan en true.
        $this->assertTrue($rule->pay_night_holiday);
        $this->assertTrue($rule->pay_overtime_holiday);
    }

    public function test_company_that_paid_dominicals_keeps_premiums_on(): void
    {
        $company = Company::create(['name' => 'Pays Dominical Co', 'slug' => 'pays-dominical-co']);
        $this->ruleFor($company)->update([
            'pay_dominical_by_default' => true,
            'pay_night_dominical' => true,
            'pay_overtime_dominical' => true,
        ]);

        $this->seedFromDominicalDefault();
        $rule = $this->ruleFor($company);

        $this->assertTrue($rule->pay_night_dominical);
        $this->assertTrue($rule->pay_overtime_dominical);
        $this->assertTrue($rule->pay_night_holiday);
        $this->assertTrue($rule->pay_overtime_holiday);
    }

    public function test_new_columns_default_to_true_when_company_created(): void
    {
        $company = Company::create(['name' => 'Fresh Co', 'slug' => 'fresh-co']);
        $rule = $this->ruleFor($company);

        $this->assertTrue($rule->pay_night_dominical);
        $this->assertTrue($rule->pay_night_holiday);
        $this->assertTrue($rule->pay_overtime_dominical);
        $this->assertTrue($rule->pay_overtime_holiday);
    }
}
