<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportControllerTest extends TestCase
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
            'hourly_rate' => 10000,
        ]);
    }

    public function test_admin_can_view_reports_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('employees')
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore departments assertion when re-enabling.
            // ->has('departments')
        );
    }

    public function test_employee_cannot_view_reports(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('reports.index'));

        $response->assertForbidden();
    }

    public function test_employee_report_returns_correct_data(): void
    {
        $entry = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'overtime_day_hours' => 0,
            'night_hours' => 0,
            'sunday_holiday_hours' => 0,
            'status' => 'calculated',
        ]);

        $lunchType = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'icon' => '🍽️',
            'color' => '#FF9800',
            'is_paid' => false,
            'is_active' => true,
        ]);

        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $lunchType->id,
            'started_at' => now()->setTime(12, 0),
            'ended_at' => now()->setTime(13, 0),
            'duration_minutes' => 60,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Employee')
            ->has('report')
            ->has('report.totals')
            ->has('report.cost_summary')
            ->where('report.breaks_by_type', [])
            ->has('report.employee')
            ->has('filters')
            ->has('employees')
            // El desglose diario llega completo a la vista, campo a campo.
            ->has('report.daily_breakdown', 1)
            ->where('report.daily_breakdown.0.date', now()->toDateString())
            ->where('report.daily_breakdown.0.clock_in', now()->setTime(8, 0)->toIso8601String())
            ->where('report.daily_breakdown.0.clock_out', now()->setTime(17, 0)->toIso8601String())
            ->where('report.daily_breakdown.0.status', 'calculated')
            // JSON serializa floats enteros como int (9.0 → 9).
            ->where('report.daily_breakdown.0.gross_hours', 9)
            ->where('report.daily_breakdown.0.break_hours', 1)
            ->where('report.daily_breakdown.0.net_hours', 8)
            ->where('report.daily_breakdown.0.regular_hours', 8)
            ->where('report.daily_breakdown.0.night_hours', 0)
            ->has('report.daily_breakdown.0.breaks', 1)
            ->where('report.daily_breakdown.0.breaks.0.name', 'Almuerzo')
            ->where('report.daily_breakdown.0.breaks.0.icon', '🍽️')
            ->where('report.daily_breakdown.0.breaks.0.color', '#FF9800')
            ->where('report.daily_breakdown.0.breaks.0.is_paid', false)
            ->where('report.daily_breakdown.0.breaks.0.started_at', now()->setTime(12, 0)->toIso8601String())
            ->where('report.daily_breakdown.0.breaks.0.ended_at', now()->setTime(13, 0)->toIso8601String())
            ->where('report.daily_breakdown.0.breaks.0.duration_minutes', 60)
            ->where('report.daily_breakdown.0.breaks.0.in_progress', false)
        );
    }

    public function test_company_report_returns_correct_data(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.company', [
            'date_range' => 'month',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Company')
            ->has('report')
            ->has('report.totals')
            ->has('report.employees')
            ->has('report.daily_attendance')
            ->has('report.cost_summary')
            ->has('filters')
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore departments assertion when re-enabling.
            // ->has('departments')
        );
    }

    public function test_employee_report_requires_employee_id(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
        ]));

        $response->assertRedirect();
    }

    public function test_custom_date_range_requires_start_and_end(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company', [
            'date_range' => 'custom',
        ]));

        $response->assertRedirect();
    }

    public function test_custom_date_range_end_must_be_after_start(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company', [
            'date_range' => 'custom',
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-01',
        ]));

        $response->assertRedirect();
    }

    public function test_company_report_filters_by_department(): void
    {
        $dept = Department::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Cocina',
        ]);

        $this->employee->update(['department_id' => $dept->id]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.company', [
            'date_range' => 'month',
            'department_id' => $dept->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Company')
            ->where('report.totals.total_employees', 1)
        );
    }

    public function test_report_with_no_data_returns_empty_state(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company', [
            'date_range' => 'custom',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('report.totals.total_employees', 0)
            ->where('report.totals.net_hours', 0)
        );
    }

    public function test_super_admin_can_view_reports(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('reports.index'));

        $response->assertOk();
    }

    public function test_super_admin_can_view_employee_report(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'overtime_day_hours' => 0,
            'night_hours' => 0,
            'sunday_holiday_hours' => 0,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Reports/Employee'));
    }

    public function test_super_admin_cannot_view_company_report(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('reports.company', [
            'date_range' => 'month',
        ]));

        $response->assertForbidden();
    }

    public function test_admin_cannot_view_employee_report_from_another_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);

        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'hourly_rate' => 20000,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $otherEmployee->id,
        ]));

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_day_preset_returns_today_only(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        // Entry de ayer — no debería incluirse
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(8, 0),
            'clock_out' => now()->subDay()->setTime(17, 0),
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'day',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('report.totals.days_worked', 1)
            ->where('report.totals.net_hours', 8)
        );
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->get(route('reports.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_invalid_date_range_preset_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company', [
            'date_range' => 'invalid',
        ]));

        $response->assertRedirect();
    }
}
