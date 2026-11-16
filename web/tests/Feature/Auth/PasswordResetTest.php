<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use App\Services\OwnerTrustedDeviceService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_owner_can_sign_in_with_existing_password_while_reset_link_is_unused(): void
    {
        Notification::fake();

        $business = Business::create(['name' => 'Reset Link Shop', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);
        $originalHash = $owner->password;

        $this->post(route('password.email'), ['email' => $owner->email])
            ->assertSessionHas('status');
        Notification::assertSentTo($owner, ResetPassword::class);
        $this->assertSame($originalHash, $owner->fresh()->password);
        $this->assertFalse($owner->fresh()->must_reset_password);

        $this->post(route('login'), ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));

        $secret = Fortify::currentEncrypter()->decrypt($owner->two_factor_secret);
        $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_owner_reset_link_request_keeps_an_existing_trusted_browser(): void
    {
        Notification::fake();

        $business = Business::create(['name' => 'Trusted Reset Shop', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);

        $this->post(route('login'), ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $secret = Fortify::currentEncrypter()->decrypt($owner->two_factor_secret);
        $challenge = $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ]);
        $trustedCookie = $challenge->getCookie(OwnerTrustedDeviceService::COOKIE_NAME);
        $this->assertNotNull($trustedCookie);
        $this->post(route('logout'));

        $this->post(route('password.email'), ['email' => $owner->email])
            ->assertSessionHas('status');
        $this->assertSame(1, $owner->trustedDevices()->count());

        $this->withCookie(OwnerTrustedDeviceService::COOKIE_NAME, $trustedCookie->getValue())
            ->post(route('login'), ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'GastoTrack9!',
                'password_confirmation' => 'GastoTrack9!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
