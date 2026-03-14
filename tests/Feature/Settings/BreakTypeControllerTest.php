<?php

namespace Tests\Feature\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\BreakType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BreakTypeControllerTest extends TestCase
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

    public function test_admin_can_view_break_types(): void
    {
        BreakType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->adminUser)->get(route('break-types.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/BreakTypes')
            ->has('breakTypes', 1)
        );
    }

    public function test_super_admin_can_view_break_types(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('break-types.index'));

        $response->assertOk();
    }

    public function test_employee_cannot_view_break_types(): void
    {
        $response = $this->actingAs($this->employeeUser)->get(route('break-types.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_break_type(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('break-types.store'), [
            'name' => 'Almuerzo',
            'is_paid' => false,
            'max_duration_minutes' => 60,
            'max_per_day' => 1,
            'is_default' => true,
        ]);

        $response->assertRedirect(route('break-types.index'));

        $this->assertDatabaseHas('break_types', [
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'max_duration_minutes' => 60,
            'max_per_day' => 1,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_break_type(): void
    {
        $breakType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('break-types.update', $breakType), [
            'name' => 'New Name',
            'is_paid' => true,
            'max_duration_minutes' => 30,
            'max_per_day' => 2,
            'is_default' => false,
        ]);

        $response->assertRedirect(route('break-types.index'));

        $this->assertDatabaseHas('break_types', [
            'id' => $breakType->id,
            'name' => 'New Name',
            'slug' => 'new-name',
            'is_paid' => true,
            'max_duration_minutes' => 30,
            'max_per_day' => 2,
        ]);
    }

    public function test_admin_can_toggle_break_type_active(): void
    {
        $breakType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->patch(route('break-types.toggle', $breakType));

        $response->assertRedirect(route('break-types.index'));

        $this->assertDatabaseHas('break_types', [
            'id' => $breakType->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_reactivate_break_type(): void
    {
        $breakType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->patch(route('break-types.toggle', $breakType));

        $response->assertRedirect(route('break-types.index'));

        $this->assertDatabaseHas('break_types', [
            'id' => $breakType->id,
            'is_active' => true,
        ]);
    }

    public function test_cannot_deactivate_default_break_type(): void
    {
        $breakType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->patch(route('break-types.toggle', $breakType));

        $response->assertSessionHasErrors('break_type');

        $this->assertDatabaseHas('break_types', [
            'id' => $breakType->id,
            'is_active' => true,
        ]);
    }

    public function test_marking_default_unmarks_previous_default(): void
    {
        $oldDefault = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_default' => true,
            'name' => 'Old Default',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('break-types.store'), [
            'name' => 'New Default',
            'is_paid' => false,
            'is_default' => true,
        ]);

        $response->assertRedirect(route('break-types.index'));

        $this->assertDatabaseHas('break_types', [
            'id' => $oldDefault->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('break_types', [
            'name' => 'New Default',
            'is_default' => true,
        ]);
    }

    public function test_update_marking_default_unmarks_previous(): void
    {
        $oldDefault = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_default' => true,
            'name' => 'Old Default',
        ]);

        $newDefault = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_default' => false,
            'name' => 'Will Be Default',
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('break-types.update', $newDefault), [
            'name' => 'Will Be Default',
            'is_paid' => false,
            'is_default' => true,
        ]);

        $response->assertRedirect(route('break-types.index'));

        $this->assertDatabaseHas('break_types', [
            'id' => $oldDefault->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('break_types', [
            'id' => $newDefault->id,
            'is_default' => true,
        ]);
    }

    public function test_admin_cannot_update_break_type_of_another_company(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Co',
            'slug' => 'other-co',
        ]);

        $breakType = BreakType::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Break',
        ]);

        $response = $this->actingAs($this->adminUser)->put(
            'settings/break-types/'.$breakType->id,
            [
                'name' => 'Hacked',
                'is_paid' => false,
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('break_types', [
            'id' => $breakType->id,
            'name' => 'Other Break',
        ]);
    }

    public function test_admin_cannot_toggle_break_type_of_another_company(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Co',
            'slug' => 'other-co',
        ]);

        $breakType = BreakType::factory()->create([
            'company_id' => $otherCompany->id,
            'is_active' => true,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->patch(
            'settings/break-types/'.$breakType->id.'/toggle'
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('break_types', [
            'id' => $breakType->id,
            'is_active' => true,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('break-types.store'), []);

        $response->assertSessionHasErrors(['name', 'is_paid']);
    }

    public function test_store_validates_name_unique_per_company(): void
    {
        BreakType::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('break-types.store'), [
            'name' => 'Almuerzo',
            'is_paid' => false,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_validates_numeric_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('break-types.store'), [
            'name' => 'Test',
            'is_paid' => false,
            'max_duration_minutes' => -5,
            'max_per_day' => 0,
        ]);

        $response->assertSessionHasErrors(['max_duration_minutes', 'max_per_day']);
    }

    public function test_admin_only_sees_own_company_break_types(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Co',
            'slug' => 'other-co',
        ]);

        BreakType::factory()->create(['company_id' => $this->company->id]);
        BreakType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->adminUser)->get(route('break-types.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('breakTypes', 1)
        );
    }
}
