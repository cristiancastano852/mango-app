<?php

namespace Tests\Feature\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);
        Role::create(['name' => 'super-admin']);

        $this->company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->adminUser->assignRole('admin');

        $this->employeeUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->employeeUser->assignRole('employee');

        Employee::create([
            'user_id' => $this->employeeUser->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_can_view_company_profile(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('company-profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/CompanyProfile')
            ->where('company.name', 'Test Co')
            ->where('company.country', 'CO')
            ->where('company.timezone', 'America/Bogota')
        );
    }

    public function test_super_admin_without_company_sees_null(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('company-profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/CompanyProfile')
            ->where('company', null)
        );
    }

    public function test_employee_cannot_view_company_profile(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('company-profile.edit'));

        $response->assertForbidden();
    }

    public function test_admin_can_update_name_country_timezone(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => 'Updated Co',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ]);

        $response->assertRedirect(route('company-profile.edit'));

        $this->assertDatabaseHas('companies', [
            'id' => $this->company->id,
            'name' => 'Updated Co',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ]);
    }

    public function test_admin_can_upload_logo(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => 'Test Co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $response->assertRedirect(route('company-profile.edit'));

        $this->company->refresh();
        $this->assertNotNull($this->company->logo);
        Storage::disk('public')->assertExists($this->company->logo);
    }

    public function test_admin_can_remove_logo(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->image('logo.png')->store('logos', 'public');
        $this->company->update(['logo' => $path]);

        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => 'Test Co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
            'remove_logo' => true,
        ]);

        $response->assertRedirect(route('company-profile.edit'));

        $this->company->refresh();
        $this->assertNull($this->company->logo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_upload_non_image_fails(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => 'Test Co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
            'logo' => UploadedFile::fake()->create('document.pdf', 500, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_upload_oversized_logo_fails(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => 'Test Co',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
            'logo' => UploadedFile::fake()->image('logo.png')->size(3000),
        ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_validates_empty_name(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => '',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_validates_invalid_timezone(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => 'Test Co',
            'country' => 'CO',
            'timezone' => 'Invalid/Zone',
        ]);

        $response->assertSessionHasErrors('timezone');
    }

    public function test_validates_invalid_country_format(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('company-profile.update'), [
            'name' => 'Test Co',
            'country' => 'Colombia',
            'timezone' => 'America/Bogota',
        ]);

        $response->assertSessionHasErrors('country');
    }
}
