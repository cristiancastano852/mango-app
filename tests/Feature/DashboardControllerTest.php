<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private User $employeeUser;

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

        $this->adminUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->adminUser->assignRole('admin');

        $this->employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->employeeUser->assignRole('employee');
        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_can_view_dashboard(): void
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

    public function test_super_admin_can_view_dashboard(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_employee_is_redirected_to_time_clock(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('dashboard'));

        $response->assertRedirect(route('time-clock.index'));
    }

    public function test_dashboard_kpis_reflect_todays_entries(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.present', 1)
        );
    }

    public function test_employee_status_list_loads_correctly_for_multiple_employees(): void
    {
        // Crear un segundo empleado con entry hoy — verifica que limit(1) no estaba
        // truncando los datos de otros empleados (bug ya corregido en DashboardController)
        $secondEmployeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $secondEmployeeUser->assignRole('employee');
        $secondEmployee = Employee::create([
            'user_id' => $secondEmployeeUser->id,
            'company_id' => $this->company->id,
        ]);

        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'status' => 'pending',
        ]);

        TimeEntry::create([
            'employee_id' => $secondEmployee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(9, 0),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.present', 2)
        );
    }
}
