<?php

namespace Tests\Feature\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanySettingsControllerTest extends TestCase
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
        Role::create(['name' => 'super-admin']);

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

    public function test_admin_can_view_company_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('company-settings.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/CompanySettings')
            ->where('hasCompany', true)
            ->has('workingDays')
            ->has('schedules')
        );
    }

    public function test_super_admin_without_company_sees_empty(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('company-settings.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('hasCompany', false)
        );
    }

    public function test_employee_cannot_view_company_settings(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('company-settings.edit'));

        $response->assertForbidden();
    }

    public function test_admin_can_update_working_days(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-settings.update'), [
            'working_days' => [1, 2, 3, 4, 5, 6],
        ]);

        $response->assertRedirect(route('company-settings.edit'));

        $this->company->refresh();
        $this->assertEquals([1, 2, 3, 4, 5, 6], $this->company->settings['working_days']);
    }

    public function test_admin_can_update_default_schedule(): void
    {
        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Default Schedule',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('company-settings.update'), [
            'working_days' => [1, 2, 3, 4, 5],
            'default_schedule_id' => $schedule->id,
        ]);

        $response->assertRedirect(route('company-settings.edit'));

        $this->company->refresh();
        $this->assertEquals($schedule->id, $this->company->settings['default_schedule_id']);
    }

    public function test_admin_can_clear_default_schedule(): void
    {
        $this->company->update([
            'settings' => ['default_schedule_id' => 999, 'working_days' => [1, 2, 3, 4, 5]],
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('company-settings.update'), [
            'working_days' => [1, 2, 3, 4, 5],
            'default_schedule_id' => null,
        ]);

        $response->assertRedirect(route('company-settings.edit'));

        $this->company->refresh();
        $this->assertNull($this->company->settings['default_schedule_id']);
    }

    public function test_validates_empty_working_days(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-settings.update'), [
            'working_days' => [],
        ]);

        $response->assertSessionHasErrors('working_days');
    }

    public function test_validates_out_of_range_day(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-settings.update'), [
            'working_days' => [1, 2, 7],
        ]);

        $response->assertSessionHasErrors('working_days.2');
    }

    public function test_deduplicates_working_days(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-settings.update'), [
            'working_days' => [1, 1, 2, 3],
        ]);

        $response->assertRedirect(route('company-settings.edit'));

        $this->company->refresh();
        $this->assertEquals([1, 2, 3], $this->company->settings['working_days']);
    }

    public function test_cross_company_schedule_rejected(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $otherSchedule = Schedule::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Schedule',
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('company-settings.update'), [
            'working_days' => [1, 2, 3, 4, 5],
            'default_schedule_id' => $otherSchedule->id,
        ]);

        $response->assertSessionHasErrors('default_schedule_id');
    }
}
