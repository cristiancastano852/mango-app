<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminCompanyCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);
        Role::create(['name' => 'super-admin']);

        config(['tenancy.base_domain' => 'mango-app.test']);
        URL::forceRootUrl('http://admin.mango-app.test');

        $this->superAdmin = User::factory()->create(['company_id' => null]);
        $this->superAdmin->assignRole('super-admin');
    }

    public function test_super_admin_can_access_create_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/super-admin/companies/create');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_create_company_with_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Nueva Empresa SAS',
            'admin_name' => 'Juan Pérez',
            'admin_email' => 'juan@empresa.com',
        ]);

        $company = Company::where('name', 'Nueva Empresa SAS')->first();

        $this->assertNotNull($company);
        $response->assertRedirect(route('super-admin.companies.edit', $company));

        $this->assertDatabaseHas('companies', [
            'name' => 'Nueva Empresa SAS',
            'timezone' => 'America/Bogota',
            'country' => 'CO',
            'onboarding_completed' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Juan Pérez',
            'email' => 'juan@empresa.com',
            'company_id' => $company->id,
        ]);

        $user = User::where('email', 'juan@empresa.com')->first();
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_company_observer_seeds_surcharge_rules_on_creation(): void
    {
        $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Empresa con Reglas',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@empresa.com',
        ]);

        $company = Company::where('name', 'Empresa con Reglas')->first();
        $this->assertNotNull($company);
        $this->assertDatabaseHas('surcharge_rules', ['company_id' => $company->id]);
    }

    public function test_created_password_is_flashed_to_session(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Empresa Flash',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@flash.com',
        ]);

        $response->assertSessionHas('created_password');
    }

    public function test_creation_fails_with_duplicate_email(): void
    {
        $existingCompany = Company::create([
            'name' => 'Existing Co',
            'slug' => 'existing-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
        User::factory()->create(['company_id' => $existingCompany->id, 'email' => 'existing@empresa.com']);

        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Nueva Empresa',
            'admin_name' => 'Juan',
            'admin_email' => 'existing@empresa.com',
        ]);

        $response->assertSessionHasErrors('admin_email');
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_creation_fails_with_empty_company_name(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => '',
            'admin_name' => 'Juan',
            'admin_email' => 'juan@empresa.com',
        ]);

        $response->assertSessionHasErrors('company_name');
    }

    public function test_creation_fails_with_empty_admin_name(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Empresa',
            'admin_name' => '',
            'admin_email' => 'juan@empresa.com',
        ]);

        $response->assertSessionHasErrors('admin_name');
    }

    public function test_creation_fails_with_invalid_email(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Empresa',
            'admin_name' => 'Juan',
            'admin_email' => 'no-es-email',
        ]);

        $response->assertSessionHasErrors('admin_email');
    }

    public function test_admin_role_cannot_access_create_form(): void
    {
        $company = Company::create([
            'name' => 'My Co',
            'slug' => 'my-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/super-admin/companies/create');

        $response->assertStatus(403);
    }

    public function test_admin_role_cannot_create_companies(): void
    {
        $company = Company::create([
            'name' => 'My Co',
            'slug' => 'my-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post('/super-admin/companies', [
            'company_name' => 'Empresa Hackeo',
            'admin_name' => 'Hacker',
            'admin_email' => 'hack@empresa.com',
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_role_cannot_access_create_form(): void
    {
        $company = Company::create([
            'name' => 'My Co',
            'slug' => 'my-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $response = $this->actingAs($employee)->get('/super-admin/companies/create');

        $response->assertStatus(403);
    }

    public function test_employee_role_cannot_create_companies(): void
    {
        $company = Company::create([
            'name' => 'My Co',
            'slug' => 'my-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $response = $this->actingAs($employee)->post('/super-admin/companies', [
            'company_name' => 'Empresa Hackeo',
            'admin_name' => 'Hacker',
            'admin_email' => 'hack@empresa.com',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/super-admin/companies/create');

        $response->assertRedirect('/login');
    }

    public function test_slug_is_autogenerated_clean_without_random_suffix(): void
    {
        $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Nueva Empresa SAS',
            'admin_name' => 'Juan',
            'admin_email' => 'juan@empresa.com',
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Nueva Empresa SAS',
            'slug' => 'nueva-empresa-sas',
        ]);
    }

    public function test_slug_collision_gets_numeric_suffix(): void
    {
        Company::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);

        $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Acme',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@acme2.com',
        ]);

        $this->assertDatabaseHas('companies', ['name' => 'Acme', 'slug' => 'acme-2']);
    }

    public function test_explicit_subdomain_is_used(): void
    {
        $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Restaurante El Mango',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@elmango.com',
            'subdomain' => 'elmango',
        ]);

        $this->assertDatabaseHas('companies', ['name' => 'Restaurante El Mango', 'slug' => 'elmango']);
    }

    public function test_duplicate_subdomain_is_rejected(): void
    {
        Company::create([
            'name' => 'Taken',
            'slug' => 'taken',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);

        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Otra',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@otra.com',
            'subdomain' => 'taken',
        ]);

        $response->assertSessionHasErrors('subdomain');
        $this->assertDatabaseMissing('companies', ['name' => 'Otra']);
    }

    public function test_invalid_subdomain_format_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Otra',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@otra.com',
            'subdomain' => 'Mal Formato!',
        ]);

        $response->assertSessionHasErrors('subdomain');
    }

    public function test_reserved_subdomain_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/super-admin/companies', [
            'company_name' => 'Otra',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@otra.com',
            'subdomain' => 'www',
        ]);

        $response->assertSessionHasErrors('subdomain');
    }
}
