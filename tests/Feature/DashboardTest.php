<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

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
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_visit_dashboard(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('kpis')
            ->has('employeeStatus')
            ->has('lateArrivals')
        );
    }

    public function test_employee_is_redirected_to_time_clock(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');

        $response = $this->actingAs($employeeUser)->get(route('dashboard'));
        $response->assertRedirect(route('time-clock.index'));
    }

    public function test_kpis_reflect_present_employees_today(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'company_id' => $this->company->id,
        ]);

        TimeEntry::create([
            'employee_id' => $employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('dashboard'));
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.present', 1)
        );
    }

    public function test_employee_status_list_contains_all_employees(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        Employee::create([
            'user_id' => $employeeUser->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('dashboard'));
        $response->assertInertia(fn ($page) => $page
            ->has('employeeStatus', 1)
        );
    }
}
