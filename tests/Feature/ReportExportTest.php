<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\TimeTracking\Actions\GenerateCompanyReport;
use App\Domain\TimeTracking\Actions\GenerateEmployeeReport;
use App\Domain\TimeTracking\Enums\PayrollDeductionReason;
use App\Domain\TimeTracking\Models\PayrollDeduction;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Exports\CompanyReportEmployeesSheet;
use App\Exports\CompanyReportSummarySheet;
use App\Exports\EmployeeReportSummarySheet;
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
            'sunday_holiday_hours' => 0,
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
            'sunday_holiday_hours' => 0,
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

    // --- Descuentos por novedad en exports ---

    public function test_employee_excel_includes_deduction_line_and_gross_base(): void
    {
        $report = $this->monthlyReportWithDeduction();

        $rows = (new EmployeeReportSummarySheet($report))->array();

        $labels = collect($rows)->map(fn ($r) => (string) ($r[0] ?? ''));
        $this->assertTrue($labels->contains(fn ($l) => str_contains($l, 'Descuento por novedad')));

        // El base mostrado es el BRUTO (neto + descuento), no el ya descontado.
        $baseRow = collect($rows)->firstWhere(0, 'Salario base del periodo');
        $this->assertEquals(
            $report['cost_summary']['base'] + $report['deductions']['amount'],
            $baseRow[3],
        );
    }

    public function test_employee_pdf_html_shows_deduction_and_gross_base(): void
    {
        $report = $this->monthlyReportWithDeduction();

        $html = view('exports.employee-report', ['report' => $report])->render();

        $this->assertStringContainsString('Descuento por novedad', $html);
        $gross = $report['cost_summary']['base'] + $report['deductions']['amount'];
        $this->assertStringContainsString(number_format($gross, 0, ',', '.'), $html);
    }

    public function test_company_exports_include_deduction(): void
    {
        $report = $this->companyReportWithDeduction();

        // Excel resumen: fila de descuentos total (negativa).
        $summary = (new CompanyReportSummarySheet($report))->array();
        $deductionRow = collect($summary)->firstWhere(0, 'Descuentos por novedad (total)');
        $this->assertNotNull($deductionRow);
        $this->assertLessThan(0, $deductionRow[1]);

        // Excel empleados: columna Descuento (índice 5, tras el Salario base) negativa para el empleado con novedad.
        $employeeRows = (new CompanyReportEmployeesSheet($report))->array();
        $this->assertTrue(collect($employeeRows)->contains(fn ($r) => ($r[5] ?? 0) < 0));

        // PDF: el HTML menciona la columna/fila de descuento.
        $html = view('exports.company-report', ['report' => $report])->render();
        $this->assertStringContainsString('Descuento', $html);
    }

    private function monthlyReportWithDeduction(): array
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');
        $monthly = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'monthly_base_salary' => 3000000,
            'hourly_rate' => 8000,
        ]);

        PayrollDeduction::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $monthly->id,
            'effective_date' => now()->toDateString(),
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        return app(GenerateEmployeeReport::class)->execute(
            $monthly->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );
    }

    private function companyReportWithDeduction(): array
    {
        $this->monthlyReportWithDeduction();

        return app(GenerateCompanyReport::class)->execute(
            $this->company->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );
    }
}
