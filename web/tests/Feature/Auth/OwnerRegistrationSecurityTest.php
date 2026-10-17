<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use App\Services\OwnerTrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OwnerRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unverified_owner_is_blocked_from_portal_and_verification_opens_business_status(): void
    {
        [$business, $owner] = $this->makeOwner('pending', false, true);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
        $this->get(route('transactions.index'))
            ->assertRedirect(route('verification.notice'));
        $this->get(route('profile.edit'))
            ->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $owner->id, 'hash' => sha1($owner->email)]
        );

        $this->get($verificationUrl)
            ->assertRedirect(route('business.status'));
        $this->assertTrue($owner->fresh()->hasVerifiedEmail());

        $this->get(route('business.status'))
            ->assertOk()
            ->assertViewIs('auth.business-status');
        $this->assertSame('pending', $business->fresh()->status);
    }

    public function test_approved_owner_must_enroll_totp_before_owner_pages(): void
    {
        [, $owner] = $this->makeOwner('active', true);
        $owner->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('owner.two-factor.setup'));

        $this->get(route('owner.two-factor.setup'))
            ->assertOk()
            ->assertViewIs('auth.owner-two-factor-setup')
            ->assertSee('Manual setup key');

        $secret = Fortify::currentEncrypter()->decrypt($owner->fresh()->two_factor_secret);
        $totp = new Google2FA;

        $this->post(route('owner.two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertNull($owner->fresh()->two_factor_confirmed_at);

        $this->post(route('owner.two-factor.confirm'), ['code' => $totp->getCurrentOtp($secret)])
            ->assertRedirect(route('owner.two-factor.recovery-codes'))
            ->assertCookie(OwnerTrustedDeviceService::COOKIE_NAME);
        $this->assertNotNull($owner->fresh()->two_factor_confirmed_at);
        $this->assertCount(1, $owner->trustedDevices()->get());

        $recoveryPage = $this->get(route('owner.two-factor.recovery-codes'))
            ->assertOk()
            ->assertViewIs('auth.owner-two-factor-recovery-codes')
            ->assertViewHas('recoveryCodes');
        $this->assertNotEmpty($recoveryPage->viewData('recoveryCodes'));

        $this->post(route('owner.two-factor.recovery-codes.acknowledge'))
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_owner_sign_in_requires_totp_and_a_recovery_code_is_one_time(): void
    {
        [, $owner] = $this->makeOwner('active', true);
        $recoveryCode = 'TEST-RECOVERY-CODE-1';
        $owner->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode([
                $recoveryCode,
                'TEST-RECOVERY-CODE-2',
            ])),
        ])->save();

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->get(route('two-factor.login'))->assertOk()->assertViewIs('auth.two-factor-challenge');

        $this->post(route('two-factor.login.store'), ['code' => 'not-a-valid-code'])
            ->assertRedirect();
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
            ->assertRedirect();
        $this->assertAuthenticatedAs($owner);
        $this->assertSame($owner->id, (int) session('owner.two_factor_user_id'));

        $this->post('/logout');
        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
            ->assertRedirect();
        $this->assertGuest();
    }

    public function test_successful_owner_mfa_remembers_the_browser_for_30_days(): void
    {
        [, $owner] = $this->makeOwner('active', true);

        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));

        $secret = Fortify::currentEncrypter()->decrypt($owner->two_factor_secret);
        $challenge = $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ]);

        $challenge->assertRedirect(route('dashboard', absolute: false))
            ->assertCookie(OwnerTrustedDeviceService::COOKIE_NAME);

        $trustedCookie = $challenge->getCookie(OwnerTrustedDeviceService::COOKIE_NAME);
        $this->assertNotNull($trustedCookie);
        $device = $owner->trustedDevices()->sole();
        $this->assertSame(hash('sha256', $trustedCookie->getValue()), $device->token_hash);
        $this->assertTrue($device->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));

        $this->post('/logout');
        $this->withCookie(OwnerTrustedDeviceService::COOKIE_NAME, $trustedCookie->getValue())
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($owner);
        $this->assertSame($owner->id, (int) session('owner.two_factor_user_id'));
    }

    public function test_expired_or_another_owners_trust_cookie_does_not_skip_mfa(): void
    {
        [, $owner] = $this->makeOwner('active', true);

        $this->post('/login', ['email' => $owner->email, 'password' => 'password']);
        $secret = Fortify::currentEncrypter()->decrypt($owner->two_factor_secret);
        $challenge = $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ]);
        $trustedCookie = $challenge->getCookie(OwnerTrustedDeviceService::COOKIE_NAME);

        $this->post('/logout');
        $owner->trustedDevices()->update(['expires_at' => now()->subSecond()]);
        $this->withCookie(OwnerTrustedDeviceService::COOKIE_NAME, $trustedCookie->getValue())
            ->post('/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->post('/logout');
        $otherOwner = $this->makeOwner('active', true)[1];
        $this->withCookie(OwnerTrustedDeviceService::COOKIE_NAME, $trustedCookie->getValue())
            ->post('/login', ['email' => $otherOwner->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_trusted_device_does_not_remove_password_requirement_and_password_change_revokes_trust(): void
    {
        [, $owner] = $this->makeOwner('active', true);

        $this->post('/login', ['email' => $owner->email, 'password' => 'password']);
        $secret = Fortify::currentEncrypter()->decrypt($owner->two_factor_secret);
        $challenge = $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ]);
        $trustedCookie = $challenge->getCookie(OwnerTrustedDeviceService::COOKIE_NAME);

        $this->post('/logout');
        $this->withCookie(OwnerTrustedDeviceService::COOKIE_NAME, $trustedCookie->getValue())
            ->post('/login', ['email' => $owner->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $owner->trustedDevices()->delete();
        $owner->trustedDevices()->create([
            'token_hash' => hash('sha256', 'a-test-token-that-is-not-used-for-login'),
            'expires_at' => now()->addDays(30),
        ]);
        $this->actingAs($owner)->withSession(['owner.two_factor_user_id' => $owner->id])
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'AnotherStrongPassword!2026',
                'password_confirmation' => 'AnotherStrongPassword!2026',
            ])->assertRedirect();

        $this->assertDatabaseCount('owner_trusted_devices', 0);
    }

    public function test_enabled_mfa_does_not_allow_direct_portal_access_without_a_successful_challenge(): void
    {
        [, $owner] = $this->makeOwner('active', true);

        $this->actingAs($owner)
            ->withSession(['owner.two_factor_user_id' => null])
            ->get(route('dashboard'))
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($owner->id, session('login.id'));
    }

    public function test_trusted_cookie_restores_mfa_for_an_authenticated_remembered_session(): void
    {
        [, $owner] = $this->makeOwner('active', true);
        $token = str_repeat('b', 64);
        $owner->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($owner)
            ->withCookie(OwnerTrustedDeviceService::COOKIE_NAME, $token)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame($owner->id, (int) session('owner.two_factor_user_id'));
    }

    public function test_owner_mfa_session_expires_after_30_days_even_while_still_signed_in(): void
    {
        [, $owner] = $this->makeOwner('active', true);

        $this->actingAs($owner)
            ->withSession([
                'owner.two_factor_user_id' => $owner->id,
                'owner.two_factor_verified_at' => now()->subDays(31)->timestamp,
            ])
            ->get(route('dashboard'))
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($owner->id, session('login.id'));
    }

    private function makeOwner(string $businessStatus, bool $active, bool $unverified = false): array
    {
        $business = Business::create([
            'name' => 'Security Test Cafe',
            'status' => $businessStatus,
            'active' => $active,
        ]);
        $owner = User::factory()->owner()->create([
            'business_id' => $business->id,
            'email_verified_at' => $unverified ? null : now(),
        ]);
        $business->update(['owner_id' => $owner->id]);

        return [$business, $owner];
    }
}
