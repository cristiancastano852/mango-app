<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Enums\PayrollDeductionReason;
use App\Domain\TimeTracking\Models\PayrollDeduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollDeductionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private User $employeeUser;

    private Employee $monthlyEmployee;

    private Employee $hourlyEmployee;

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

        $this->monthlyEmployee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'monthly_base_salary' => 2000000,
            'hourly_rate' => 8000,
        ]);

        $hourlyUser = User::factory()->create(['company_id' => $this->company->id]);
        $hourlyUser->assignRole('employee');
        $this->hourlyEmployee = Employee::create([
            'user_id' => $hourlyUser->id,
            'company_id' => $this->company->id,
            'salary_type' => 'hourly',
            'hourly_rate' => 10000,
        ]);
    }

    public function test_admin_can_register_a_deduction(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('payroll-deductions.store'), [
            'employee_id' => $this->monthlyEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_deductions', [
            'company_id' => $this->company->id,
            'employee_id' => $this->monthlyEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_super_admin_can_register_a_deduction(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->post(route('payroll-deductions.store'), [
            'employee_id' => $this->monthlyEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 1.5,
            'reason' => PayrollDeductionReason::PermisoNoRemunerado->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_deductions', [
            'employee_id' => $this->monthlyEmployee->id,
            'company_id' => $this->company->id,
            'days' => 1.5,
            'created_by' => $superAdmin->id,
        ]);
    }

    public function test_employee_cannot_register_a_deduction(): void
    {
        $response = $this->actingAs($this->employeeUser)->post(route('payroll-deductions.store'), [
            'employee_id' => $this->monthlyEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('payroll_deductions', 0);
    }

    public function test_hourly_employee_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('payroll-deductions.store'), [
            'employee_id' => $this->hourlyEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('payroll_deductions', 0);
    }

    public function test_admin_cannot_register_deduction_for_other_company_employee(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other-co']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'salary_type' => 'monthly',
            'monthly_base_salary' => 2000000,
            'hourly_rate' => 8000,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('payroll-deductions.store'), [
            'employee_id' => $otherEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('payroll_deductions', 0);
    }

    public function test_days_must_be_at_least_half_a_day(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('payroll-deductions.store'), [
            'employee_id' => $this->monthlyEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 0,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        $response->assertSessionHasErrors('days');
    }

    public function test_admin_can_delete_a_deduction(): void
    {
        $deduction = PayrollDeduction::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->monthlyEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('payroll-deductions.destroy', $deduction));

        $response->assertRedirect();
        $this->assertDatabaseMissing('payroll_deductions', ['id' => $deduction->id]);
    }

    public function test_admin_cannot_delete_other_company_deduction(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other-co']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'salary_type' => 'monthly',
            'monthly_base_salary' => 2000000,
            'hourly_rate' => 8000,
        ]);
        $deduction = PayrollDeduction::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'effective_date' => '2026-03-10',
            'days' => 2,
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('payroll-deductions.destroy', $deduction));

        $response->assertNotFound();
        $this->assertDatabaseHas('payroll_deductions', ['id' => $deduction->id]);
    }
}
