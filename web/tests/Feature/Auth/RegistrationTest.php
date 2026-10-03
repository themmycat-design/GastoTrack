<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Carlo Reyes',
            'business_name' => 'Harbor Coffee',
            'business_type' => 'cafe',
            'email' => 'carlo@harborcoffee.example',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'carlo@harborcoffee.example',
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('businesses', [
            'name' => 'Harbor Coffee',
            'business_type' => 'cafe',
            'status' => 'pending',
            'active' => false,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('business.status', absolute: false));
    }
}
