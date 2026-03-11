<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\TimeTracking\Models\TimeEntry;
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
            'overtime_hours' => 0,
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
}
