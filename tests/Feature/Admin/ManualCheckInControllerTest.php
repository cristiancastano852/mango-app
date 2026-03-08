<?php

namespace Tests\Feature\Admin;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualCheckInControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_admin_can_manual_check_in_employee(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.manual-check-in'), [
                'employee_id' => $this->employee->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_non_admin_cannot_manual_check_in(): void
    {
        $regularUser = User::factory()->create(['company_id' => $this->company->id]);
        $regularUser->assignRole('employee');

        $response = $this->actingAs($regularUser)
            ->post(route('admin.manual-check-in'), [
                'employee_id' => $this->employee->id,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_check_in_employee_from_another_company(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'slug' => 'other-company',
        ]);

        $otherEmployeeUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherEmployeeUser->assignRole('employee');
        $otherEmployee = Employee::withoutGlobalScopes()->create([
            'user_id' => $otherEmployeeUser->id,
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.manual-check-in'), [
                'employee_id' => $otherEmployee->id,
            ]);

        $response->assertSessionHasErrors('employee_id');

        $this->assertDatabaseMissing('time_entries', [
            'employee_id' => $otherEmployee->id,
        ]);
    }

    public function test_employee_id_is_required(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.manual-check-in'), []);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_cannot_check_in_employee_already_clocked_in(): void
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
}
