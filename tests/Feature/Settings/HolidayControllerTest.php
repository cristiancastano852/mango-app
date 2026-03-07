<?php

namespace Tests\Feature\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Holiday;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HolidayControllerTest extends TestCase
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

    public function test_admin_can_view_holidays(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('holidays.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('settings/Holidays'));
    }

    public function test_employee_cannot_view_holidays(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('holidays.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_holiday(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('holidays.store'), [
            'name' => 'Día de la Independencia',
            'date' => '2026-07-20',
            'is_recurring' => true,
        ]);

        $response->assertRedirect(route('holidays.index'));

        $this->assertDatabaseHas('holidays', [
            'company_id' => $this->company->id,
            'name' => 'Día de la Independencia',
            'is_recurring' => true,
        ]);
    }

    public function test_employee_cannot_create_holiday(): void
    {
        $response = $this->actingAs($this->employeeUser)->post(route('holidays.store'), [
            'name' => 'Test',
            'date' => '2026-07-20',
            'is_recurring' => false,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_holiday(): void
    {
        $holiday = Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Old Name',
            'date' => '2026-07-20',
            'is_recurring' => false,
            'country' => 'CO',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('holidays.update', $holiday), [
            'name' => 'New Name',
            'date' => '2026-07-21',
            'is_recurring' => true,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', [
            'id' => $holiday->id,
            'name' => 'New Name',
            'is_recurring' => true,
        ]);
    }

    public function test_admin_can_delete_holiday(): void
    {
        $holiday = Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Test Holiday',
            'date' => '2026-07-20',
            'is_recurring' => false,
            'country' => 'CO',
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('holidays.destroy', $holiday));

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_employee_cannot_delete_holiday(): void
    {
        $holiday = Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Test Holiday',
            'date' => '2026-07-20',
            'is_recurring' => false,
            'country' => 'CO',
        ]);

        $response = $this->actingAs($this->employeeUser)->delete(route('holidays.destroy', $holiday));

        $response->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('holidays.store'), []);

        $response->assertSessionHasErrors(['name', 'date', 'is_recurring']);
    }

    public function test_company_observer_seeds_holidays_on_company_creation(): void
    {
        $newCompany = Company::create([
            'name' => 'New Company',
            'slug' => 'new-company',
        ]);

        $this->assertDatabaseHas('holidays', ['company_id' => $newCompany->id]);
        $this->assertDatabaseHas('surcharge_rules', ['company_id' => $newCompany->id]);
    }
}
