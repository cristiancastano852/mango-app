<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_settings_page_redirects_to_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('two-factor.show'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_two_factor_settings_page_redirects_unauthenticated_users_to_login(): void
    {
        $this->get(route('two-factor.show'))
            ->assertRedirect(route('login'));
    }
}
