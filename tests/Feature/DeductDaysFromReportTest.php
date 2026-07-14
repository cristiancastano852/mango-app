<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\DayDeductionDecision;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\GenerateEmployeeReport;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Exports\EmployeeReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeductDaysFromReportTest extends TestCase
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
            'normal_day_value' => 50000,
        ]);

        $this->createWorkedDay();
    }

    private function createWorkedDay(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(16, 0),
            'gross_hours' => 8.0,
            'break_hours' => 0.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);
    }

    public function test_admin_report_reflects_day_deduction(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'deducted_days' => 2,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.deducted_days', 2)
            ->where('report.cost_summary.deducted_days', 2)
            ->where('report.cost_summary.day_deduction', 100000)
        );
    }

    public function test_super_admin_report_reflects_day_deduction(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'deducted_days' => 1,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.deducted_days', 1)
            ->where('report.cost_summary.day_deduction', 50000)
        );
    }

    public function test_exporting_persists_day_deduction(): void
    {
        $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'deducted_days' => 2,
        ]))->assertOk();

        $this->assertDatabaseHas('day_deduction_decisions', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'deducted_days' => 2,
            'exported_by' => $this->adminUser->id,
        ]);
    }

    public function test_viewing_report_does_not_persist_day_deduction(): void
    {
        $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'deducted_days' => 2,
        ]))->assertOk();

        $this->assertDatabaseCount('day_deduction_decisions', 0);
    }

    public function test_admin_cannot_deduct_days_for_other_company_employee(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'hourly_rate' => 10000,
            'normal_day_value' => 50000,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $otherEmployee->id,
            'deducted_days' => 2,
        ]));

        $response->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('day_deduction_decisions', 0);
    }

    public function test_excel_export_includes_day_deduction_row(): void
    {
        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
            deductedDays: 2,
        );

        $rows = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->keyBy(fn ($row) => $row[0] ?? '');

        $label = 'Descuento por días (2 × 50000)';
        $this->assertArrayHasKey($label, $rows->all());
        $this->assertEquals(-100000.0, $rows[$label][3]);
        $this->assertEquals($report['cost_summary']['final_pay'], $rows['TOTAL A PAGAR'][3]);
    }

    public function test_negative_days_do_not_deduct(): void
    {
        $decision = DayDeductionDecision::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'deducted_days' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
            'deducted_days' => -2,
        ]));

        // La validación rechaza negativos (min:0).
        $response->assertSessionHasErrors('deducted_days');
        $decision->refresh();
        $this->assertSame(0, $decision->deducted_days);
    }
}
