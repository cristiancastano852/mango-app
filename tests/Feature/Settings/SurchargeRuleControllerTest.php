<?php

namespace Tests\Feature\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurchargeRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
        ]);

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->adminUser->assignRole('admin');

        $this->employeeUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->employeeUser->assignRole('employee');

        Employee::create([
            'user_id' => $this->employeeUser->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_can_view_surcharge_rules(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('surcharge-rules.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('settings/SurchargeRules'));
    }

    public function test_employee_cannot_view_surcharge_rules(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('surcharge-rules.edit'));

        $response->assertForbidden();
    }

    public function test_admin_can_update_surcharge_rules(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('surcharge-rules.update'), [
            'night_surcharge' => 40,
            'overtime_day' => 30,
            'overtime_night' => 80,
            'sunday_holiday' => 80,
            'overtime_day_sunday' => 110,
            'overtime_night_sunday' => 160,
            'night_sunday' => 120,
            'max_weekly_hours' => 40,
        ]);

        $response->assertRedirect(route('surcharge-rules.edit'));

        $rule = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->first();

        $this->assertEquals(40, (float) $rule->night_surcharge);
        $this->assertEquals(40, $rule->max_weekly_hours);
    }

    public function test_employee_cannot_update_surcharge_rules(): void
    {
        $response = $this->actingAs($this->employeeUser)->put(route('surcharge-rules.update'), [
            'night_surcharge' => 40,
            'overtime_day' => 30,
            'overtime_night' => 80,
            'sunday_holiday' => 80,
            'overtime_day_sunday' => 110,
            'overtime_night_sunday' => 160,
            'night_sunday' => 120,
            'max_weekly_hours' => 40,
        ]);

        $response->assertForbidden();
    }

    public function test_update_requires_all_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('surcharge-rules.update'), []);

        $response->assertSessionHasErrors([
            'night_surcharge',
            'overtime_day',
            'overtime_night',
            'sunday_holiday',
            'overtime_day_sunday',
            'overtime_night_sunday',
            'night_sunday',
            'max_weekly_hours',
        ]);
    }
}
