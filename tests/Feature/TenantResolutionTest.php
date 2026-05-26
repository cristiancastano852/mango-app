<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Organization\Models\Department;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.base_domain' => 'mango-app.test',
            'tenancy.reserved_subdomains' => ['www', 'admin'],
        ]);

        Route::middleware('web')->get('/__tenant-probe', function () {
            return response()->json(['id' => app(TenantContext::class)->id()]);
        });

        Route::middleware('web')->get('/__tenant-departments', function () {
            return response()->json(['ids' => Department::pluck('id')->all()]);
        });
    }

    private function company(string $slug): Company
    {
        return Company::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);
    }

    public function test_valid_subdomain_resolves_to_company(): void
    {
        $company = $this->company('elmango');

        $response = $this->get('http://elmango.mango-app.test/__tenant-probe');

        $response->assertOk()->assertExactJson(['id' => $company->id]);
    }

    public function test_unknown_subdomain_returns_404(): void
    {
        $response = $this->get('http://noexiste.mango-app.test/__tenant-probe');

        $response->assertNotFound();
    }

    public function test_central_apex_has_no_tenant(): void
    {
        $this->company('elmango');

        $response = $this->get('http://mango-app.test/__tenant-probe');

        $response->assertOk()->assertExactJson(['id' => null]);
    }

    public function test_reserved_subdomain_has_no_tenant(): void
    {
        $response = $this->get('http://www.mango-app.test/__tenant-probe');

        $response->assertOk()->assertExactJson(['id' => null]);
    }

    public function test_admin_host_has_no_tenant(): void
    {
        $this->company('admin');

        $response = $this->get('http://admin.mango-app.test/__tenant-probe');

        $response->assertOk()->assertExactJson(['id' => null]);
    }

    public function test_custom_admin_subdomain_is_not_treated_as_tenant(): void
    {
        config(['tenancy.admin_subdomain' => 'platform']);

        $response = $this->get('http://platform.mango-app.test/__tenant-probe');

        $response->assertOk()->assertExactJson(['id' => null]);
    }

    public function test_queries_are_scoped_to_subdomain_tenant(): void
    {
        $alpha = $this->company('alpha');
        $beta = $this->company('beta');
        $depAlpha = Department::create(['company_id' => $alpha->id, 'name' => 'Alpha Dept']);
        Department::create(['company_id' => $beta->id, 'name' => 'Beta Dept']);

        $response = $this->get('http://alpha.mango-app.test/__tenant-departments');

        $response->assertOk()->assertExactJson(['ids' => [$depAlpha->id]]);
    }

    public function test_without_subdomain_falls_back_to_authenticated_user_company(): void
    {
        Role::create(['name' => 'admin']);
        $alpha = $this->company('alpha');
        $beta = $this->company('beta');
        Department::create(['company_id' => $alpha->id, 'name' => 'Alpha Dept']);
        $depBeta = Department::create(['company_id' => $beta->id, 'name' => 'Beta Dept']);

        $user = User::factory()->create(['company_id' => $beta->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('http://mango-app.test/__tenant-departments');

        $response->assertOk()->assertExactJson(['ids' => [$depBeta->id]]);
    }
}
