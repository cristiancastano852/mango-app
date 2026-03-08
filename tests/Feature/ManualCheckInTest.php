<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->adminUser->assignRole('admin');

        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_can_manually_check_in_an_employee(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.manual-check-in'), [
                'employee_id' => $this->employee->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'pin_verified' => false,
        ]);
    }

    public function test_non_admin_cannot_use_manual_check_in(): void
    {
        $regularUser = User::factory()->create(['company_id' => $this->company->id]);
        $regularUser->assignRole('employee');

        $response = $this->actingAs($regularUser)
            ->post(route('admin.manual-check-in'), [
                'employee_id' => $this->employee->id,
            ]);

        $response->assertForbidden();
    }

    public function test_manual_check_in_fails_if_employee_already_clocked_in(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.manual-check-in'), [
                'employee_id' => $this->employee->id,
            ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_manual_check_in_requires_employee_id(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.manual-check-in'), []);

        $response->assertSessionHasErrors('employee_id');
    }
}
