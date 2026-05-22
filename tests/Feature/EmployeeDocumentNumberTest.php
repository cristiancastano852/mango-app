<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeDocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private Employee $existingEmployee;

    protected function setUp(): void
    {
        parent::setUp();

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

        $existingUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $existingUser->assignRole('employee');

        $this->existingEmployee = Employee::create([
            'user_id' => $existingUser->id,
            'company_id' => $this->company->id,
            'document_number' => '111111111',
        ]);
    }

    public function test_admin_can_create_employee_with_document_number(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'name' => 'Manuela Zapata',
            'email' => 'manuela@test.com',
            'document_number' => '987654321',
        ]);

        $employee = Employee::whereHas('user', fn ($q) => $q->where('email', 'manuela@test.com'))->first();
        $response->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', [
            'document_number' => '987654321',
        ]);
    }

    public function test_creating_employee_without_document_number_fails(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'name' => 'Sin Cedula',
            'email' => 'sincedula@test.com',
        ]);

        $response->assertSessionHasErrors('document_number');
        $this->assertDatabaseMissing('users', ['email' => 'sincedula@test.com']);
    }

    public function test_duplicate_document_number_in_same_company_fails(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('employees.store'), [
            'name' => 'Duplicate Doc',
            'email' => 'duplicate@test.com',
            'document_number' => '111111111',
        ]);

        $response->assertSessionHasErrors('document_number');
        $this->assertDatabaseMissing('users', ['email' => 'duplicate@test.com']);
    }

    public function test_same_document_number_in_different_companies_is_allowed(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $otherAdmin = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherAdmin->assignRole('admin');

        $response = $this->actingAs($otherAdmin)->post(route('employees.store'), [
            'name' => 'Other Employee',
            'email' => 'other@other.com',
            'document_number' => '111111111',
        ]);

        $employee = Employee::whereHas('user', fn ($q) => $q->where('email', 'other@other.com'))->first();
        $response->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', [
            'company_id' => $otherCompany->id,
            'document_number' => '111111111',
        ]);
    }

    public function test_admin_can_update_employee_document_number(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('employees.update', $this->existingEmployee), [
            'name' => $this->existingEmployee->user->name,
            'email' => $this->existingEmployee->user->email,
            'document_number' => '222222222',
        ]);

        $response->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $this->existingEmployee->id,
            'document_number' => '222222222',
        ]);
    }

    public function test_update_document_number_to_same_value_passes(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('employees.update', $this->existingEmployee), [
            'name' => $this->existingEmployee->user->name,
            'email' => $this->existingEmployee->user->email,
            'document_number' => '111111111',
        ]);

        $response->assertRedirect(route('employees.index'));
    }

    public function test_update_document_number_to_duplicate_in_same_company_fails(): void
    {
        $anotherUser = User::factory()->create(['company_id' => $this->company->id]);
        $anotherUser->assignRole('employee');
        $anotherEmployee = Employee::create([
            'user_id' => $anotherUser->id,
            'company_id' => $this->company->id,
            'document_number' => '333333333',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('employees.update', $anotherEmployee), [
            'name' => $anotherEmployee->user->name,
            'email' => $anotherEmployee->user->email,
            'document_number' => '111111111',
        ]);

        $response->assertSessionHasErrors('document_number');
    }
}
