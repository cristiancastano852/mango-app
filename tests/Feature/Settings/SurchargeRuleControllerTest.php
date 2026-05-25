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

        Role::create(['name' => 'super-admin']);
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'pay_overtime_by_default' => true,
            'max_weekly_hours' => 42,
            'max_daily_hours' => 8,
            'night_start_time' => '21:00',
            'night_end_time' => '06:00',
        ], $overrides);
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
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['night_surcharge' => 40, 'max_weekly_hours' => 40]),
        );

        $response->assertRedirect(route('surcharge-rules.edit'));

        $rule = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->first();

        $this->assertEquals(40, (float) $rule->night_surcharge);
        $this->assertEquals(40, $rule->max_weekly_hours);
    }

    public function test_employee_cannot_update_surcharge_rules(): void
    {
        $response = $this->actingAs($this->employeeUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(),
        );

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
            'pay_overtime_by_default',
            'max_weekly_hours',
            'max_daily_hours',
            'night_start_time',
            'night_end_time',
        ]);
    }

    public function test_admin_can_disable_pay_overtime_by_default(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['pay_overtime_by_default' => false]),
        );

        $response->assertRedirect(route('surcharge-rules.edit'));

        $this->assertDatabaseHas('surcharge_rules', [
            'company_id' => $this->company->id,
            'pay_overtime_by_default' => false,
        ]);
    }

    public function test_pay_overtime_by_default_must_be_boolean(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['pay_overtime_by_default' => 'maybe']),
        );

        $response->assertSessionHasErrors('pay_overtime_by_default');
    }

    public function test_admin_can_update_night_schedule_times(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['night_start_time' => '22:00', 'night_end_time' => '05:00']),
        );

        $response->assertRedirect(route('surcharge-rules.edit'));

        $this->assertDatabaseHas('surcharge_rules', [
            'company_id' => $this->company->id,
            'night_start_time' => '22:00',
            'night_end_time' => '05:00',
            'night_surcharge' => 35,
            'max_weekly_hours' => 42,
        ]);
    }

    public function test_super_admin_can_update_any_company_night_schedule(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->put(
            route('surcharge-rules.update'),
            $this->validPayload([
                'company_id' => $this->company->id,
                'night_start_time' => '23:00',
                'night_end_time' => '07:00',
            ]),
        );

        $response->assertRedirect(route('surcharge-rules.edit'));

        $this->assertDatabaseHas('surcharge_rules', [
            'company_id' => $this->company->id,
            'night_start_time' => '23:00',
            'night_end_time' => '07:00',
        ]);
    }

    public function test_admin_cannot_update_another_company_surcharge_rule(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);

        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['company_id' => $otherCompany->id]),
        );

        $response->assertSessionHasErrors('company_id');
    }

    public function test_night_start_time_must_be_valid_time_format(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['night_start_time' => '25:00']),
        );

        $response->assertSessionHasErrors('night_start_time');
    }

    public function test_night_start_time_rejects_invalid_string(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['night_start_time' => 'abc']),
        );

        $response->assertSessionHasErrors('night_start_time');
    }

    public function test_admin_can_update_max_daily_hours(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['max_daily_hours' => 10]),
        );

        $response->assertRedirect(route('surcharge-rules.edit'));

        $this->assertDatabaseHas('surcharge_rules', [
            'company_id' => $this->company->id,
            'max_daily_hours' => 10,
        ]);
    }

    public function test_max_daily_hours_rejects_zero(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['max_daily_hours' => 0]),
        );

        $response->assertSessionHasErrors('max_daily_hours');
    }

    public function test_max_daily_hours_rejects_value_above_24(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['max_daily_hours' => 25]),
        );

        $response->assertSessionHasErrors('max_daily_hours');
    }

    public function test_max_daily_hours_rejects_decimal(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['max_daily_hours' => 8.5]),
        );

        $response->assertSessionHasErrors('max_daily_hours');
    }

    public function test_max_daily_hours_accepts_boundary_values(): void
    {
        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['max_daily_hours' => 1]),
        );

        $response->assertRedirect(route('surcharge-rules.edit'));

        $response = $this->actingAs($this->adminUser)->put(
            route('surcharge-rules.update'),
            $this->validPayload(['max_daily_hours' => 24]),
        );

        $response->assertRedirect(route('surcharge-rules.edit'));
    }
}
