<?php

namespace Tests\Feature\Auth;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantLoginGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        config([
            'tenancy.base_domain' => 'mango-app.test',
            'tenancy.admin_subdomain' => 'admin',
            'tenancy.reserved_subdomains' => ['www', 'admin'],
        ]);
    }

    private function company(string $slug): Company
    {
        return Company::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'timezone' => 'America/Bogota',
            'country' => 'CO',
        ]);
    }

    private function onHost(string $host): void
    {
        URL::forceRootUrl("http://{$host}");
    }

    public function test_tenant_user_can_login_on_its_subdomain(): void
    {
        $company = $this->company('alpha');
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $this->onHost('alpha.mango-app.test');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_of_another_company_cannot_login(): void
    {
        $this->company('alpha');
        $beta = $this->company('beta');
        $user = User::factory()->create(['company_id' => $beta->id]);
        $user->assignRole('admin');

        $this->onHost('alpha.mango-app.test');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_super_admin_cannot_login_on_tenant_subdomain(): void
    {
        $this->company('alpha');
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $this->onHost('alpha.mango-app.test');
        $this->post(route('login.store'), ['email' => $superAdmin->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_super_admin_can_login_on_admin_host(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $this->onHost('admin.mango-app.test');
        $this->post(route('login.store'), ['email' => $superAdmin->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_admin_cannot_login_on_admin_host(): void
    {
        $company = $this->company('alpha');
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $this->onHost('admin.mango-app.test');
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_login_fails_on_public_host(): void
    {
        $company = $this->company('alpha');
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $this->onHost('mango-app.test');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }
}
