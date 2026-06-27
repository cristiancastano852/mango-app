<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\OvertimePaymentDecision;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OvertimePaymentDecisionTest extends TestCase
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

        $this->adminUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->adminUser->assignRole('admin');

        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 10000,
        ]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 6.0,
            'overtime_night_hours' => 2.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);
    }

    private function period(): array
    {
        return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
    }

    public function test_exporting_employee_report_saves_the_decision(): void
    {
        [$start, $end] = $this->period();

        $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'pay_overtime' => 0,
        ]))->assertOk();

        $this->assertDatabaseHas('overtime_payment_decisions', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => $start,
            'end_date' => $end,
            'pay_overtime' => false,
            'exported_by' => $this->adminUser->id,
        ]);
    }

    public function test_exporting_company_report_saves_a_global_decision_with_null_employee(): void
    {
        [$start, $end] = $this->period();

        $this->actingAs($this->adminUser)->get(route('reports.company.excel', [
            'date_range' => 'month',
            'pay_overtime' => 0,
        ]))->assertOk();

        $this->assertDatabaseHas('overtime_payment_decisions', [
            'company_id' => $this->company->id,
            'employee_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'pay_overtime' => false,
        ]);
    }

    public function test_viewing_the_report_does_not_persist_a_decision(): void
    {
        $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'pay_overtime' => 0,
        ]))->assertOk();

        $this->assertDatabaseCount('overtime_payment_decisions', 0);
    }

    public function test_reexporting_overwrites_the_previous_decision(): void
    {
        $params = [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ];

        $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', $params + ['pay_overtime' => 0]))->assertOk();
        $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', $params + ['pay_overtime' => 1]))->assertOk();

        $this->assertDatabaseCount('overtime_payment_decisions', 1);
        $this->assertDatabaseHas('overtime_payment_decisions', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'pay_overtime' => true,
        ]);
    }

    public function test_view_preloads_the_saved_decision(): void
    {
        [$start, $end] = $this->period();

        OvertimePaymentDecision::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => $start,
            'end_date' => $end,
            'pay_overtime' => false,
        ]);

        $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]))->assertInertia(fn ($page) => $page
            ->where('filters.pay_overtime', false)
            ->where('report.cost_summary.pay_overtime', false)
        );
    }

    public function test_view_falls_back_to_company_default_when_no_decision(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_overtime_by_default' => false]);

        $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]))->assertInertia(fn ($page) => $page->where('filters.pay_overtime', false));
    }

    public function test_exporting_employee_report_saves_the_overtime_payable_hours(): void
    {
        [$start, $end] = $this->period();

        $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'overtime_payable_hours' => 3,
        ]))->assertOk();

        $this->assertDatabaseHas('overtime_payment_decisions', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => $start,
            'end_date' => $end,
            'overtime_payable_hours' => 3,
        ]);
    }

    public function test_viewing_the_report_does_not_persist_overtime_payable_hours(): void
    {
        $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'overtime_payable_hours' => 3,
        ]))->assertOk();

        $this->assertDatabaseCount('overtime_payment_decisions', 0);
    }

    public function test_view_preloads_the_saved_overtime_payable_hours(): void
    {
        [$start, $end] = $this->period();

        OvertimePaymentDecision::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => $start,
            'end_date' => $end,
            'pay_overtime' => true,
            'overtime_payable_hours' => 5,
        ]);

        $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]))->assertInertia(fn ($page) => $page
            ->where('filters.overtime_payable_hours', fn ($v) => (float) $v === 5.0)
        );
    }

    public function test_employee_cannot_access_reports(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');

        $this->actingAs($employeeUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'overtime_payable_hours' => 3,
        ]))->assertForbidden();
    }

    public function test_decisions_are_isolated_per_company(): void
    {
        [$start, $end] = $this->period();

        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);

        $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'pay_overtime' => 0,
        ]))->assertOk();

        $this->assertDatabaseMissing('overtime_payment_decisions', [
            'company_id' => $otherCompany->id,
        ]);
        $this->assertDatabaseHas('overtime_payment_decisions', [
            'company_id' => $this->company->id,
        ]);
    }
}
