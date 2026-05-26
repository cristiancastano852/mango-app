<?php

namespace Tests\Feature\SuperAdmin;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $employee;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        config(['tenancy.base_domain' => 'mango-app.test']);
        URL::forceRootUrl('http://admin.mango-app.test');

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'timezone' => 'America/Bogota',
        ]);

        $this->superAdmin = User::factory()->create(['company_id' => null]);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->assignRole('admin');

        $this->employee = User::factory()->create(['company_id' => $this->company->id]);
        $this->employee->assignRole('employee');
    }

    // --- Index ---

    public function test_super_admin_can_list_companies(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.companies.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Companies/Index')
            ->has('companies', 1)
        );
    }

    public function test_admin_cannot_access_companies_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('super-admin.companies.index'));

        $response->assertForbidden();
    }

    public function test_employee_cannot_access_companies_index(): void
    {
        $response = $this->actingAs($this->employee)->get(route('super-admin.companies.index'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_companies_index(): void
    {
        $response = $this->get(route('super-admin.companies.index'));

        $response->assertRedirect(route('login'));
    }

    // --- Edit ---

    public function test_super_admin_can_access_edit_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.companies.edit', $this->company));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Companies/Edit')
            ->where('company.id', $this->company->id)
            ->has('admins')
        );
    }

    public function test_edit_page_shows_existing_admins(): void
    {
        $anotherAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $anotherAdmin->assignRole('admin');

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.companies.edit', $this->company));

        $response->assertInertia(fn ($page) => $page
            ->has('admins', 2)
        );
    }

    public function test_edit_page_shows_empty_admins_when_none(): void
    {
        $companyWithoutAdmins = Company::create([
            'name' => 'No Admin Co',
            'slug' => 'no-admin-co',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.companies.edit', $companyWithoutAdmins));

        $response->assertInertia(fn ($page) => $page
            ->has('admins', 0)
        );
    }

    public function test_admin_cannot_access_edit_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('super-admin.companies.edit', $this->company));

        $response->assertForbidden();
    }

    // --- Update ---

    public function test_super_admin_can_update_company(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(route('super-admin.companies.update', $this->company), [
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'timezone' => 'America/Bogota',
            'country' => 'CO',
            'subscription_plan' => 'premium',
            'trial_ends_at' => null,
        ]);

        $response->assertRedirect(route('super-admin.companies.edit', $this->company));

        $this->assertDatabaseHas('companies', [
            'id' => $this->company->id,
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'subscription_plan' => 'premium',
        ]);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        Company::create([
            'name' => 'Other Company',
            'slug' => 'other-company',
        ]);

        $response = $this->actingAs($this->superAdmin)->put(route('super-admin.companies.update', $this->company), [
            'name' => 'Test Company',
            'slug' => 'other-company',
            'timezone' => 'America/Bogota',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_empty_name_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(route('super-admin.companies.update', $this->company), [
            'name' => '',
            'slug' => 'test-company',
            'timezone' => 'America/Bogota',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_cannot_update_company(): void
    {
        $response = $this->actingAs($this->admin)->put(route('super-admin.companies.update', $this->company), [
            'name' => 'Hacked Name',
            'slug' => 'test-company',
            'timezone' => 'America/Bogota',
        ]);

        $response->assertForbidden();
    }

    // --- Store admin user ---

    public function test_super_admin_can_create_admin_user_for_company(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.companies.admin-users.store', $this->company), [
            'name' => 'New Admin',
            'email' => 'newadmin@company.com',
        ]);

        $response->assertRedirect(route('super-admin.companies.edit', $this->company));
        $response->assertSessionHas('created_password');

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@company.com',
            'company_id' => $this->company->id,
        ]);

        $newUser = User::where('email', 'newadmin@company.com')->first();
        $this->assertTrue($newUser->hasRole('admin'));
    }

    public function test_created_admin_password_is_hashed(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.companies.admin-users.store', $this->company), [
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);

        $user = User::where('email', 'admin@test.com')->first();
        $plainPassword = session('created_password');
        $this->assertNotEquals($plainPassword, $user->password);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.companies.admin-users.store', $this->company), [
            'name' => 'Duplicate',
            'email' => $this->admin->email,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.companies.admin-users.store', $this->company), [
            'name' => 'Bad Email',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_admin_cannot_create_admin_user(): void
    {
        $response = $this->actingAs($this->admin)->post(route('super-admin.companies.admin-users.store', $this->company), [
            'name' => 'New Admin',
            'email' => 'another@company.com',
        ]);

        $response->assertForbidden();
    }

    // --- Dashboard redirect ---

    public function test_super_admin_is_redirected_from_dashboard_to_companies(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertRedirect(route('super-admin.companies.index'));
    }

    public function test_admin_sees_dashboard_normally(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
    }
}
