<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
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

    public function test_admin_can_view_calendar(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('calendar.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Calendar/Index')
            ->has('month')
            ->has('entriesByDate')
            ->has('employees')
        );
    }

    public function test_non_admin_cannot_view_calendar(): void
    {
        $regularUser = User::factory()->create(['company_id' => $this->company->id]);
        $regularUser->assignRole('employee');

        $response = $this->actingAs($regularUser)->get(route('calendar.index'));
        $response->assertForbidden();
    }

    public function test_calendar_returns_entries_for_requested_month(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-15',
            'clock_in' => '2026-03-15 08:00:00',
            'clock_out' => '2026-03-15 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('calendar.index', ['month' => '2026-03']));

        $response->assertInertia(fn ($page) => $page
            ->where('month', '2026-03')
            ->has('entriesByDate.2026-03-15')
        );
    }

    public function test_calendar_filters_by_employee(): void
    {
        $anotherEmployeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $anotherEmployeeUser->assignRole('employee');
        $anotherEmployee = Employee::create([
            'user_id' => $anotherEmployeeUser->id,
            'company_id' => $this->company->id,
        ]);

        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-15',
            'clock_in' => '2026-03-15 08:00:00',
            'clock_out' => '2026-03-15 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'pending',
        ]);

        TimeEntry::create([
            'employee_id' => $anotherEmployee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-15',
            'clock_in' => '2026-03-15 08:00:00',
            'clock_out' => '2026-03-15 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('calendar.index', [
            'month' => '2026-03',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertInertia(fn ($page) => $page
            ->has('entriesByDate.2026-03-15', 1)
        );
    }
}
