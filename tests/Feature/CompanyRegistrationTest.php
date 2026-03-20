<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);
        Role::create(['name' => 'super-admin']);
    }

    public function test_registration_form_is_accessible_to_guests(): void
    {
        $response = $this->get('/register/company');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_is_redirected_to_dashboard(): void
    {
        $company = Company::create([
            'name' => 'Existing Co',
            'slug' => 'existing-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/register/company');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_company_and_admin_user_are_created_on_registration(): void
    {
        $response = $this->post('/register/company', [
            'company_name' => 'Nueva Empresa SAS',
            'name' => 'Juan Pérez',
            'email' => 'juan@empresa.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('onboarding.company'));

        $this->assertDatabaseHas('companies', [
            'name' => 'Nueva Empresa SAS',
            'onboarding_completed' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Juan Pérez',
            'email' => 'juan@empresa.com',
        ]);

        $user = User::where('email', 'juan@empresa.com')->first();
        $this->assertTrue($user->hasRole('admin'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        $company = Company::create([
            'name' => 'Existing Co',
            'slug' => 'existing-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
        User::factory()->create(['company_id' => $company->id, 'email' => 'existing@empresa.com']);

        $response = $this->post('/register/company', [
            'company_name' => 'Nueva Empresa',
            'name' => 'Ana García',
            'email' => 'existing@empresa.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_registration_fails_with_mismatched_passwords(): void
    {
        $response = $this->post('/register/company', [
            'company_name' => 'Nueva Empresa',
            'name' => 'Ana García',
            'email' => 'ana@empresa.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_registration_fails_with_missing_company_name(): void
    {
        $response = $this->post('/register/company', [
            'company_name' => '',
            'name' => 'Ana García',
            'email' => 'ana@empresa.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('company_name');
    }

    public function test_honeypot_prevents_registration(): void
    {
        $response = $this->post('/register/company', [
            'company_name' => 'Spam Co',
            'name' => 'Spammer',
            'email' => 'spam@empresa.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'website' => 'https://spam.com',
        ]);

        $response->assertRedirect(route('register.company.create'));
        $this->assertDatabaseMissing('users', ['email' => 'spam@empresa.com']);
    }
}
