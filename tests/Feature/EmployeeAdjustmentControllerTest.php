<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeAdjustmentControllerTest extends TestCase
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

        $this->company = Company::create(['name' => 'Test Company', 'slug' => 'test-company']);

        $this->adminUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->adminUser->assignRole('admin');

        $this->employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->employeeUser->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'company_id' => $this->company->id,
            'document_number' => '999999999',
        ]);
    }

    public function test_admin_can_create_adjustment(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('employees.adjustments.store', $this->employee),
            ['date' => '2026-06-10', 'type' => 'Bonus', 'amount' => 100000, 'concept' => 'Bono productividad'],
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_adjustments', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'type' => 'Bonus',
            'amount' => 100000,
            'concept' => 'Bono productividad',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_admin_can_update_adjustment(): void
    {
        $adjustment = EmployeeAdjustment::factory()->deduction()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'amount' => 50000,
        ]);

        $response = $this->actingAs($this->adminUser)->put(
            route('employees.adjustments.update', [$this->employee, $adjustment]),
            ['date' => '2026-06-12', 'type' => 'Deduction', 'amount' => 75000, 'concept' => 'Préstamo'],
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_adjustments', ['id' => $adjustment->id, 'amount' => 75000, 'concept' => 'Préstamo']);
    }

    public function test_admin_can_delete_adjustment(): void
    {
        $adjustment = EmployeeAdjustment::factory()->bonus()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(
            route('employees.adjustments.destroy', [$this->employee, $adjustment]),
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('employee_adjustments', ['id' => $adjustment->id]);
    }

    public function test_amount_must_be_positive(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('employees.adjustments.store', $this->employee),
            ['date' => '2026-06-10', 'type' => 'Bonus', 'amount' => 0, 'concept' => 'Bono'],
        );

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('employee_adjustments', 0);
    }

    public function test_concept_and_note_are_optional(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('employees.adjustments.store', $this->employee),
            ['date' => '2026-06-10', 'type' => 'Bonus', 'amount' => 100000],
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_adjustments', [
            'employee_id' => $this->employee->id,
            'amount' => 100000,
            'concept' => null,
            'note' => null,
        ]);
    }

    public function test_type_must_be_valid(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('employees.adjustments.store', $this->employee),
            ['date' => '2026-06-10', 'type' => 'Invalid', 'amount' => 1000, 'concept' => 'X'],
        );

        $response->assertSessionHasErrors('type');
    }

    public function test_employee_cannot_create_adjustment(): void
    {
        $response = $this->actingAs($this->employeeUser)->post(
            route('employees.adjustments.store', $this->employee),
            ['date' => '2026-06-10', 'type' => 'Bonus', 'amount' => 1000, 'concept' => 'X'],
        );

        $response->assertForbidden();
    }

    public function test_admin_cannot_create_adjustment_for_employee_of_another_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'document_number' => '111111111',
        ]);

        $response = $this->actingAs($this->adminUser)->post(
            route('employees.adjustments.store', $otherEmployee),
            ['date' => '2026-06-10', 'type' => 'Bonus', 'amount' => 1000, 'concept' => 'X'],
        );

        // El empleado de otra empresa no existe para este tenant (CompanyScope) → 404.
        $response->assertNotFound();
        $this->assertDatabaseCount('employee_adjustments', 0);
    }

    public function test_cannot_update_adjustment_of_another_employee(): void
    {
        $otherEmployeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $otherEmployeeUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherEmployeeUser->id,
            'company_id' => $this->company->id,
            'document_number' => '222222222',
        ]);
        $adjustment = EmployeeAdjustment::factory()->bonus()->create([
            'company_id' => $this->company->id,
            'employee_id' => $otherEmployee->id,
        ]);

        // El ajuste no pertenece a $this->employee → scopeBindings lo rechaza (404).
        $response = $this->actingAs($this->adminUser)->put(
            route('employees.adjustments.update', [$this->employee, $adjustment]),
            ['date' => '2026-06-12', 'type' => 'Bonus', 'amount' => 9999, 'concept' => 'Hack'],
        );

        $response->assertNotFound();
    }
}
