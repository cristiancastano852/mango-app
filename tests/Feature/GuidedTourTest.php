<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuidedTourTest extends TestCase
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
            'onboarding_completed' => true,
        ]);

        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->assignRole('admin');
    }

    public function test_dashboard_shows_tour_for_admin_after_onboarding(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('showTour', true)
        );
    }

    public function test_dashboard_does_not_show_tour_after_dismiss(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['tour_dismissed' => true])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('showTour', false)
        );
    }

    public function test_dashboard_does_not_show_tour_for_incomplete_onboarding(): void
    {
        $this->company->update(['onboarding_completed' => false]);

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('showTour', false)
        );
    }

    public function test_tour_dismiss_sets_session_and_redirects(): void
    {
        $response = $this->actingAs($this->admin)->post('/tour/dismiss');

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('tour_dismissed', true);
    }

    public function test_tour_dismiss_is_idempotent(): void
    {
        $this->actingAs($this->admin)->post('/tour/dismiss');
        $response = $this->actingAs($this->admin)
            ->withSession(['tour_dismissed' => true])
            ->post('/tour/dismiss');

        $response->assertRedirect(route('dashboard'));
    }
}
