<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\TimeTracking\Models\BreakType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

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
            'onboarding_completed' => false,
        ]);

        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->assignRole('admin');
    }

    public function test_step1_shows_company_data(): void
    {
        $response = $this->actingAs($this->admin)->get('/onboarding/company');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Onboarding/Company')
            ->has('company')
        );
    }

    public function test_step1_updates_company_and_redirects_to_step2(): void
    {
        $response = $this->actingAs($this->admin)->post('/onboarding/company', [
            'name' => 'Nueva Empresa SAS',
            'country' => 'CO',
            'timezone' => 'America/Bogota',
        ]);

        $response->assertRedirect(route('onboarding.schedule'));
        $this->assertDatabaseHas('companies', [
            'id' => $this->company->id,
            'name' => 'Nueva Empresa SAS',
        ]);
    }

    public function test_step1_fails_with_invalid_timezone(): void
    {
        $response = $this->actingAs($this->admin)->post('/onboarding/company', [
            'name' => 'Test Co',
            'country' => 'CO',
            'timezone' => 'Invalid/Zone',
        ]);

        $response->assertSessionHasErrors('timezone');
    }

    public function test_step2_creates_schedule_and_redirects_to_step3(): void
    {
        $response = $this->actingAs($this->admin)->post('/onboarding/schedule', [
            'name' => 'Jornada Normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect(route('onboarding.break-types'));
        $this->assertDatabaseHas('schedules', [
            'company_id' => $this->company->id,
            'name' => 'Jornada Normal',
        ]);
        $schedule = \App\Domain\Organization\Models\Schedule::where('company_id', $this->company->id)->first();
        $this->company->refresh();
        $this->assertEquals($schedule->id, $this->company->settings['default_schedule_id']);
    }

    public function test_step2_skip_redirects_to_step3_without_creating_schedule(): void
    {
        $response = $this->actingAs($this->admin)->post('/onboarding/schedule', [
            'skip' => '1',
        ]);

        $response->assertRedirect(route('onboarding.break-types'));
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_step2_fails_when_end_time_before_start_time(): void
    {
        $response = $this->actingAs($this->admin)->post('/onboarding/schedule', [
            'name' => 'Jornada Normal',
            'start_time' => '17:00',
            'end_time' => '08:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_step3_updates_break_types_and_completes_onboarding(): void
    {
        $bt1 = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_active' => true,
            'is_paid' => true,
            'is_default' => true,
        ]);
        $bt2 = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Baño',
            'slug' => 'bano',
            'is_active' => true,
            'is_paid' => true,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->admin)->post('/onboarding/break-types', [
            'active_ids' => [$bt1->id],
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('companies', [
            'id' => $this->company->id,
            'onboarding_completed' => true,
        ]);
        $this->assertDatabaseHas('break_types', ['id' => $bt1->id, 'is_active' => true]);
        $this->assertDatabaseHas('break_types', ['id' => $bt2->id, 'is_active' => false]);
    }

    public function test_employee_cannot_access_wizard(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');

        $response = $this->actingAs($employeeUser)->get('/onboarding/company');

        $response->assertStatus(403);
    }

    public function test_admin_with_completed_onboarding_is_redirected_to_dashboard(): void
    {
        $this->company->update(['onboarding_completed' => true]);

        $response = $this->actingAs($this->admin)->get('/onboarding/company');

        $response->assertRedirect(route('dashboard'));
    }
}
