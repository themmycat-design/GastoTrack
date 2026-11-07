<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
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
            'business_type' => 'coffee',
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
            'business_type' => 'coffee',
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
                'business_type' => 'coffee',
                'email' => 'carlo@harborcoffee.example',
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'carlo@harborcoffee.example']);
    }

    public function test_mail_transport_failure_keeps_registration_pending_and_shows_retry_guidance(): void
    {
        Notification::fake();
        Event::listen(Registered::class, function (): void {
            throw new UnexpectedResponseException('SMTP authentication required');
        });

        $this->post('/register', [
            'name' => 'Pending Owner',
            'business_name' => 'Pending Cafe',
            'business_type' => 'coffee',
            'email' => 'pending@example.test',
            'password' => 'GastoTrack9!',
            'password_confirmation' => 'GastoTrack9!',
        ])->assertRedirect(route('verification.notice', absolute: false))
            ->assertSessionHas('verification_delivery_failed', true);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('businesses', ['name' => 'Pending Cafe', 'status' => 'pending', 'active' => false]);
        $this->get('/verify-email')->assertOk()->assertSee('Your account was created, but we could not send');
    }
}
