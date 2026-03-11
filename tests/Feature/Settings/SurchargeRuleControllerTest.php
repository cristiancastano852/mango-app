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
            'night_start_time' => '21:00',
            'night_end_time' => '06:00',
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

    public function test_admin_can_update_night_schedule_times(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('surcharge-rules.update'), [
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'max_weekly_hours' => 42,
            'night_start_time' => '22:00',
            'night_end_time' => '05:00',
        ]);

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

        $response = $this->actingAs($superAdmin)->put(route('surcharge-rules.update'), [
            'company_id' => $this->company->id,
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'max_weekly_hours' => 42,
            'night_start_time' => '23:00',
            'night_end_time' => '07:00',
        ]);

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

        $response = $this->actingAs($this->adminUser)->put(route('surcharge-rules.update'), [
            'company_id' => $otherCompany->id,
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'max_weekly_hours' => 42,
            'night_start_time' => '21:00',
            'night_end_time' => '06:00',
        ]);

        $response->assertSessionHasErrors('company_id');
    }

    public function test_night_start_time_must_be_valid_time_format(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('surcharge-rules.update'), [
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'max_weekly_hours' => 42,
            'night_start_time' => '25:00',
            'night_end_time' => '06:00',
        ]);

        $response->assertSessionHasErrors('night_start_time');
    }

    public function test_night_start_time_rejects_invalid_string(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('surcharge-rules.update'), [
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'max_weekly_hours' => 42,
            'night_start_time' => 'abc',
            'night_end_time' => '06:00',
        ]);

        $response->assertSessionHasErrors('night_start_time');
    }
}
