<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeAdjustment;
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

    // --- Colapso de recargo premium en el export ---

    public function test_employee_excel_folds_collapsed_night_dominical_into_night(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_night_dominical' => false]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(20, 0),
            'clock_out' => now()->subDay()->setTime(23, 0),
            'gross_hours' => 6.0,
            'break_hours' => 0,
            'net_hours' => 6.0,
            'night_hours' => 2.0,
            'night_dominical_hours' => 4.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $rows = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->keyBy(fn ($row) => $row[0] ?? '');

        // El nocturno absorbe las horas dominicales colapsadas (2 + 4 = 6h) y su costo fundido.
        $this->assertEquals(6.0, $rows['Horas nocturnas'][1]);
        $this->assertEquals(81000.0, $rows['Horas nocturnas'][3]);
        // El renglón premium desactivado ya no se emite (sus horas quedan en el nocturno base).
        $this->assertFalse($rows->has('Horas nocturnas dominicales'));
    }

    // --- Ocultar filas de recargo con pago desactivado ---

    private function createNightDominicalEntry(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(20, 0),
            'clock_out' => now()->subDay()->setTime(23, 0),
            'gross_hours' => 6.0,
            'break_hours' => 0,
            'net_hours' => 6.0,
            'night_hours' => 2.0,
            'night_dominical_hours' => 4.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);
    }

    public function test_employee_excel_hides_disabled_premium_row(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_night_dominical' => false]);
        $this->createNightDominicalEntry();

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $labels = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->map(fn ($row) => $row[0] ?? '');

        // La fila premium desactivada (0h/$0 tras el colapso) ya no se emite.
        $this->assertFalse($labels->contains('Horas nocturnas dominicales'));
        // El nocturno base (que absorbió las horas) y el festivo diurno siguen presentes.
        $this->assertTrue($labels->contains('Horas nocturnas'));
        $this->assertTrue($labels->contains('Horas festivas'));
    }

    public function test_employee_pdf_hides_disabled_premium_row(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_night_dominical' => false]);
        $this->createNightDominicalEntry();

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $html = view('exports.employee-report', ['report' => $report])->render();

        $this->assertStringNotContainsString('Recargo nocturno dominical', $html);
        $this->assertStringContainsString('Recargo festivo', $html);
    }

    private function createNightOvertimeEntry(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(18, 0),
            'clock_out' => now()->subDay()->setTime(22, 0),
            'gross_hours' => 4.0,
            'break_hours' => 0,
            'net_hours' => 4.0,
            'overtime_day_hours' => 1.0,
            'overtime_night_hours' => 3.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);
    }

    public function test_employee_excel_hides_night_overtime_row_when_flag_off(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_overtime_night' => false]);
        $this->createNightOvertimeEntry();

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $rows = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->keyBy(fn ($row) => $row[0] ?? '');

        // La extra nocturna (0h/$0 tras el colapso) ya no se emite.
        $this->assertFalse($rows->has('Horas extra nocturnas'));
        // La extra diurna absorbe sus horas (1 + 3 = 4h) a tarifa diurna.
        $this->assertTrue($rows->has('Horas extra diurnas'));
        $this->assertEquals(4.0, $rows['Horas extra diurnas'][1]);
    }

    public function test_employee_pdf_hides_night_overtime_row_when_flag_off(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_overtime_night' => false]);
        $this->createNightOvertimeEntry();

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $html = view('exports.employee-report', ['report' => $report])->render();

        $this->assertStringNotContainsString('<td>Extra nocturna</td>', $html);
        $this->assertStringContainsString('<td>Extra diurna</td>', $html);
    }

    public function test_employee_excel_keeps_unpaid_dominical_row_in_hourly_mode(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_dominical_by_default' => false]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(8, 0),
            'clock_out' => now()->subDay()->setTime(14, 0),
            'gross_hours' => 6.0,
            'break_hours' => 0,
            'net_hours' => 6.0,
            'dominical_hours' => 6.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $rows = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->keyBy(fn ($row) => $row[0] ?? '');

        // Dominical sin recargo en modo hourly se paga a tarifa ordinaria: la fila permanece.
        $this->assertTrue($rows->has('Horas dominicales'));
        $this->assertEquals(60000.0, $rows['Horas dominicales'][3]); // 6h × 10.000
    }

    public function test_employee_excel_shows_days_in_dominical_day_mode(): void
    {
        $this->employee->update([
            'dominical_payment_mode' => 'day',
            'normal_day_value' => 60000,
        ]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(8, 0),
            'clock_out' => now()->subDay()->setTime(14, 0),
            'gross_hours' => 6.0,
            'break_hours' => 0,
            'net_hours' => 6.0,
            'dominical_hours' => 6.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $rows = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->keyBy(fn ($row) => $row[0] ?? '');

        // En modo por día la celda de cantidad muestra los días pagados, no las horas.
        $this->assertEquals('1 día', $rows['Horas dominicales'][1]);
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

    // --- Seguridad social en los exports ---

    public function test_employee_excel_includes_social_security_deduction_and_net_pay(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(8, 0),
            'clock_out' => now()->subDay()->setTime(16, 0),
            'gross_hours' => 8.0,
            'break_hours' => 0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $rows = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->keyBy(fn ($row) => $row[0] ?? '');

        $total = $report['cost_summary']['total'];
        $this->assertEquals($total, $rows['TOTAL DEVENGADO'][3]);
        $this->assertEquals(-round($total * 0.04, 2), $rows['Salud (4%)'][3]);
        $this->assertEquals(-round($total * 0.04, 2), $rows['Pensión (4%)'][3]);
        $this->assertEquals($report['cost_summary']['net_pay'], $rows['NETO A PAGAR'][3]);
    }

    public function test_employee_pdf_view_includes_social_security_deduction(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => now()->subDay()->setTime(8, 0),
            'clock_out' => now()->subDay()->setTime(16, 0),
            'gross_hours' => 8.0,
            'break_hours' => 0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'completed',
            'pin_verified' => true,
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->subDays(2)->startOfDay(),
            now()->endOfDay(),
        );

        $html = view('exports.employee-report', ['report' => $report])->render();

        $this->assertStringContainsString('TOTAL DEVENGADO', $html);
        $this->assertStringContainsString('Salud (4%)', $html);
        $this->assertStringContainsString('Pensión (4%)', $html);
        $this->assertStringContainsString('NETO A PAGAR', $html);
    }

    // --- Ajustes de nómina en los exports ---

    public function test_employee_excel_includes_adjustments_and_final_pay(): void
    {
        EmployeeAdjustment::factory()->bonus()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'amount' => 100000,
            'concept' => 'Bono',
        ]);
        EmployeeAdjustment::factory()->deduction()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'amount' => 40000,
            'concept' => 'Préstamo',
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $rows = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->keyBy(fn ($row) => $row[0] ?? '');

        $this->assertEquals(100000.0, $rows['Bonificación: Bono'][3]);
        $this->assertEquals(-40000.0, $rows['Deducción: Préstamo'][3]);
        $this->assertEquals($report['cost_summary']['final_pay'], $rows['TOTAL A PAGAR'][3]);
    }

    public function test_employee_pdf_view_includes_adjustments(): void
    {
        EmployeeAdjustment::factory()->bonus()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'amount' => 100000,
            'concept' => 'Bono',
        ]);

        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $html = view('exports.employee-report', ['report' => $report])->render();

        $this->assertStringContainsString('Bonificación: Bono', $html);
        $this->assertStringContainsString('TOTAL A PAGAR', $html);
    }

    public function test_employee_excel_omits_final_pay_row_without_adjustments(): void
    {
        $report = app(GenerateEmployeeReport::class)->execute(
            $this->employee->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $labels = collect((new EmployeeReportExport($report))->sheets()[0]->array())
            ->map(fn ($row) => $row[0] ?? '');

        $this->assertFalse($labels->contains('TOTAL A PAGAR'));
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
            'social_security_base' => 1000000.0, 'health_rate' => 4.0, 'health_deduction' => 40000.0,
            'pension_rate' => 4.0, 'pension_deduction' => 40000.0, 'net_pay' => 920000.0 + $transportAllowance,
            'salary_type' => 'monthly', 'pay_overtime' => true, 'pay_dominical' => true,
            'dominical_mode' => 'hour', 'normal_day_value' => 0, 'dominical_worked_days' => 0, 'dominical_paid_days' => 0,
            'details' => [],
        ];
    }
}
