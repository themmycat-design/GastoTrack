<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
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
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Carlo Reyes',
            'business_name' => 'Harbor Coffee',
            'business_type' => 'cafe',
            'email' => 'carlo@harborcoffee.example',
            'password' => 'GastoTrack9!',
            'password_confirmation' => 'GastoTrack9!',
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

        $owner = \App\Models\User::where('email', 'carlo@harborcoffee.example')->firstOrFail();
        Notification::assertSentTo($owner, VerifyEmail::class);
        $this->assertFalse($owner->hasVerifiedEmail());
        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_weak_password_is_rejected_during_registration(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Carlo Reyes',
                'business_name' => 'Harbor Coffee',
                'business_type' => 'cafe',
                'email' => 'carlo@harborcoffee.example',
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'carlo@harborcoffee.example']);
    }
}
