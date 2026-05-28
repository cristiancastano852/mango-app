<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\TimeTracking\Models\BreakType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDefaultBreakTypesTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $slug = 'empresa-test'): Company
    {
        return Company::create(['name' => 'Test', 'slug' => $slug]);
    }

    public function test_creates_five_break_types_when_company_is_created(): void
    {
        $company = $this->createCompany();

        $this->assertCount(5, BreakType::where('company_id', $company->id)->get());
    }

    public function test_almuerzo_is_the_only_default_break_type(): void
    {
        $company = $this->createCompany();

        $defaults = BreakType::where('company_id', $company->id)
            ->where('is_default', true)
            ->get();

        $this->assertCount(1, $defaults);
        $this->assertSame('Almuerzo', $defaults->first()->name);
    }

    public function test_all_seeded_break_types_are_active(): void
    {
        $company = $this->createCompany();

        $inactive = BreakType::where('company_id', $company->id)
            ->where('is_active', false)
            ->count();

        $this->assertSame(0, $inactive);
    }

    public function test_break_types_are_isolated_per_company(): void
    {
        $companyA = $this->createCompany('empresa-a');
        $companyB = $this->createCompany('empresa-b');

        $this->assertCount(5, BreakType::where('company_id', $companyA->id)->get());
        $this->assertCount(5, BreakType::where('company_id', $companyB->id)->get());

        $this->assertSame(0,
            BreakType::where('company_id', $companyA->id)
                ->whereIn('id', BreakType::where('company_id', $companyB->id)->pluck('id'))
                ->count()
        );
    }

    public function test_seeded_break_types_have_expected_names(): void
    {
        $company = $this->createCompany();

        $names = BreakType::where('company_id', $company->id)->pluck('name')->sort()->values()->all();

        $this->assertSame(['Almuerzo', 'Baño', 'Descanso', 'Médica', 'Personal'], $names);
    }
}
