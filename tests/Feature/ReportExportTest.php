<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\TimeTracking\Actions\GenerateEmployeeReport;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Exports\EmployeeReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportExportTest extends TestCase
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

        $this->createTimeEntry();
    }

    private function createTimeEntry(): void
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
            'night_hours' => 0,
            'overtime_day_hours' => 0,
            'dominical_hours' => 0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);
    }

    // --- Employee Excel ---

    public function test_admin_can_export_employee_report_as_excel(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('.xlsx', $contentDisposition);
    }

    public function test_admin_can_export_monthly_salary_employee_report(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');
        $monthly = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'monthly_base_salary' => 2000000,
            'hourly_rate' => 8000,
        ]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $monthly->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(18, 0),
            'gross_hours' => 10.0,
            'break_hours' => 0,
            'net_hours' => 10.0,
            'regular_hours' => 0,
            'night_hours' => 10.0,
            'overtime_day_hours' => 0,
            'dominical_hours' => 0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);

        $excel = $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $monthly->id,
        ]));
        $excel->assertOk();
        $excel->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $pdf = $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', [
            'date_range' => 'month',
            'employee_id' => $monthly->id,
        ]));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_excel_daily_sheet_has_schedule_and_excludes_in_progress(): void
    {
        // Turno abierto en otro día del mismo mes: no debe aparecer en el detalle diario.
        $openDate = now()->day === 1 ? now()->addDay() : now()->subDay();
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $openDate->toDateString(),
            'clock_in' => $openDate->copy()->setTime(8, 0),
            'clock_out' => null,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $dailyRows = (new EmployeeReportExport($report))->sheets()[1]->array();

        $this->assertCount(1, $dailyRows);
        $this->assertEquals(now()->toDateString(), $dailyRows[0][0]);
        $this->assertEquals('8:00 AM', $dailyRows[0][1]);
        $this->assertEquals('5:00 PM', $dailyRows[0][2]);
    }

    public function test_employee_pdf_view_has_schedule_and_excludes_in_progress(): void
    {
        $openDate = now()->day === 1 ? now()->addDay() : now()->subDay();
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $openDate->toDateString(),
            'clock_in' => $openDate->copy()->setTime(8, 0),
            'clock_out' => null,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $html = view('exports.employee-report', ['report' => $report])->render();

        $this->assertStringContainsString('8:00 AM', $html);
        $this->assertStringContainsString('5:00 PM', $html);
        $this->assertStringNotContainsString($openDate->toDateString(), $html);
    }

    // --- Employee PDF ---

    public function test_admin_can_export_employee_report_as_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('.pdf', $contentDisposition);
    }

    // --- Company Excel ---

    public function test_admin_can_export_company_report_as_excel(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company.excel', [
            'date_range' => 'month',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('reporte-empresa.xlsx', $contentDisposition);
    }

    // --- Company PDF ---

    public function test_admin_can_export_company_report_as_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company.pdf', [
            'date_range' => 'month',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('reporte-empresa.pdf', $contentDisposition);
    }

    // --- Access Control ---

    public function test_employee_cannot_export_employee_excel(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertForbidden();
    }

    public function test_employee_cannot_export_company_pdf(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('reports.company.pdf', [
            'date_range' => 'month',
        ]));

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_export(): void
    {
        $response = $this->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertRedirect(route('login'));
    }

    // --- Validation ---

    public function test_employee_excel_requires_employee_id(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
        ]));

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_custom_date_range_requires_dates(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company.excel', [
            'date_range' => 'custom',
        ]));

        $response->assertSessionHasErrors(['start_date', 'end_date']);
    }

    // --- Department Filter ---

    public function test_company_excel_with_department_filter(): void
    {
        $department = Department::withoutGlobalScopes()->create([
            'name' => 'Cocina',
            'company_id' => $this->company->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.company.excel', [
            'date_range' => 'month',
            'department_id' => $department->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    // --- Super-admin access ---

    public function test_super_admin_can_export_employee_report(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('reports.employee.pdf', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_super_admin_cannot_export_company_report(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('reports.company.excel', [
            'date_range' => 'month',
        ]));

        $response->assertForbidden();
    }

    public function test_admin_cannot_export_employee_report_from_another_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);

        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'hourly_rate' => 20000,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $otherEmployee->id,
        ]));

        $response->assertSessionHasErrors('employee_id');
    }

    // --- Excel filename includes employee name ---

    public function test_employee_excel_filename_contains_employee_name(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $this->employee->id,
        ]));

        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('reporte-', $contentDisposition);
        $this->assertStringContainsString('.xlsx', $contentDisposition);
    }

    // --- Empty data exports ---

    public function test_employee_excel_exports_with_no_data(): void
    {
        $newUser = User::factory()->create(['company_id' => $this->company->id]);
        $newUser->assignRole('employee');
        $newEmployee = Employee::create([
            'user_id' => $newUser->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 5000,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            'date_range' => 'month',
            'employee_id' => $newEmployee->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_company_pdf_exports_with_no_data_for_period(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reports.company.pdf', [
            'date_range' => 'custom',
            'start_date' => '2020-01-01',
            'end_date' => '2020-01-31',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    // --- Transport allowance row ---

    public function test_employee_excel_includes_transport_allowance_row(): void
    {
        $report = [
            'employee' => ['name' => 'Ana', 'department' => null, 'position' => null],
            'period' => ['start' => '2026-03-01', 'end' => '2026-03-15'],
            'totals' => $this->zeroTotals(),
            'cost_summary' => $this->monthlyCostSummary(transportAllowance: 120000.0),
            'breaks_by_type' => [],
        ];

        $rows = (new \App\Exports\EmployeeReportSummarySheet($report))->array();

        $allowanceRow = collect($rows)->first(fn ($r) => ($r[0] ?? null) === 'Auxilio de transporte');
        $this->assertNotNull($allowanceRow);
        $this->assertEquals(120000.0, $allowanceRow[3]);
    }

    public function test_employee_excel_omits_transport_allowance_row_when_zero(): void
    {
        $report = [
            'employee' => ['name' => 'Ana', 'department' => null, 'position' => null],
            'period' => ['start' => '2026-03-01', 'end' => '2026-03-15'],
            'totals' => $this->zeroTotals(),
            'cost_summary' => $this->monthlyCostSummary(transportAllowance: 0.0),
            'breaks_by_type' => [],
        ];

        $rows = (new \App\Exports\EmployeeReportSummarySheet($report))->array();

        $this->assertNull(collect($rows)->first(fn ($r) => ($r[0] ?? null) === 'Auxilio de transporte'));
    }

    public function test_company_excel_includes_transport_allowance_row(): void
    {
        $report = [
            'period' => ['start' => '2026-03-01', 'end' => '2026-03-15'],
            'totals' => array_merge($this->zeroTotals(), ['total_employees' => 1, 'total_days_worked' => 1]),
            'cost_summary' => $this->monthlyCostSummary(transportAllowance: 120000.0),
        ];

        $rows = (new \App\Exports\CompanyReportSummarySheet($report))->array();

        $allowanceRow = collect($rows)->first(fn ($r) => ($r[0] ?? null) === 'Auxilio de transporte (total)');
        $this->assertNotNull($allowanceRow);
        $this->assertEquals(120000.0, $allowanceRow[1]);
    }

    /**
     * @return array<string, float|int>
     */
    private function zeroTotals(): array
    {
        return [
            'days_worked' => 0, 'gross_hours' => 0, 'break_hours' => 0, 'paid_break_overage_hours' => 0, 'net_hours' => 0,
            'regular_hours' => 0, 'night_hours' => 0, 'dominical_hours' => 0, 'night_dominical_hours' => 0,
            'holiday_hours' => 0, 'night_holiday_hours' => 0,
            'overtime_day_hours' => 0, 'overtime_night_hours' => 0, 'overtime_day_dominical_hours' => 0, 'overtime_night_dominical_hours' => 0,
            'overtime_day_holiday_hours' => 0, 'overtime_night_holiday_hours' => 0, 'dominical_worked_days' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlyCostSummary(float $transportAllowance): array
    {
        return [
            'regular' => 0, 'night' => 0, 'dominical' => 0, 'night_dominical' => 0,
            'holiday' => 0, 'night_holiday' => 0,
            'overtime_day' => 0, 'overtime_night' => 0, 'overtime_day_dominical' => 0, 'overtime_night_dominical' => 0,
            'overtime_day_holiday' => 0, 'overtime_night_holiday' => 0,
            'base' => 1000000.0, 'transport_allowance' => $transportAllowance,
            'total' => 1000000.0 + $transportAllowance,
            'salary_type' => 'monthly', 'pay_overtime' => true, 'pay_dominical' => true,
            'dominical_mode' => 'hour', 'dominical_day_value' => 0, 'dominical_worked_days' => 0, 'dominical_paid_days' => 0,
            'details' => [],
        ];
    }
}
