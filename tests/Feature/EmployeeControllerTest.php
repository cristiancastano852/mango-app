<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
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

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->adminUser->assignRole('admin');

        $this->employeeUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->employeeUser->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'company_id' => $this->company->id,
        ]);
    }

    // --- Authorization: employees cannot access admin routes ---

    public function test_employee_cannot_access_employee_index(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('employees.index'));

        $response->assertForbidden();
    }

    public function test_employee_cannot_access_employee_create(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('employees.create'));

        $response->assertForbidden();
    }

    public function test_employee_cannot_store_employee(): void
    {
        $response = $this->actingAs($this->employeeUser)->post(route('employees.store'), [
            'name' => 'New Employee',
            'email' => 'new@test.com',
        ]);

        $response->assertForbidden();
    }

    public function test_employee_cannot_edit_employee(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('employees.edit', $this->employee));

        $response->assertForbidden();
    }

    public function test_employee_cannot_update_employee(): void
    {
        $response = $this->actingAs($this->employeeUser)->put(route('employees.update', $this->employee), [
            'name' => 'Updated Name',
            'email' => $this->employeeUser->email,
        ]);

        $response->assertForbidden();
    }

    public function test_employee_cannot_delete_employee(): void
    {
        $response = $this->actingAs($this->employeeUser)->delete(route('employees.destroy', $this->employee));

        $response->assertForbidden();
    }

    // --- Admin access: admins can access employee routes ---

    public function test_admin_can_access_employee_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('employees.index'));

        $response->assertOk();
    }

    public function test_admin_can_access_employee_create(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('employees.create'));

        $response->assertOk();
    }

    public function test_admin_can_store_employee(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'name' => 'New Employee',
            'email' => 'new@test.com',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('users', ['email' => 'new@test.com']);
    }

    public function test_admin_can_edit_employee(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('employees.edit', $this->employee));

        $response->assertOk();
    }

    public function test_admin_can_update_employee(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('employees.update', $this->employee), [
            'name' => 'Updated Name',
            'email' => $this->employeeUser->email,
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('users', [
            'id' => $this->employeeUser->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_employee(): void
    {
        $response = $this->actingAs($this->adminUser)->delete(route('employees.destroy', $this->employee));

        $response->assertRedirect(route('employees.index'));
    }

    // --- Super admin access ---

    public function test_super_admin_can_access_employee_index(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('employees.index'));

        $response->assertOk();
    }

    // --- Guest access ---

    public function test_guest_cannot_access_employee_routes(): void
    {
        $response = $this->get(route('employees.index'));

        $response->assertRedirect(route('login'));
    }
}
