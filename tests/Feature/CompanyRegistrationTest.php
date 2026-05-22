<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_route_returns_404(): void
    {
        $response = $this->get('/register/company');

        $response->assertStatus(404);
    }

    public function test_public_registration_post_returns_404(): void
    {
        $response = $this->post('/register/company', [
            'company_name' => 'Nueva Empresa SAS',
            'name' => 'Juan Pérez',
            'email' => 'juan@empresa.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(404);
    }
}
