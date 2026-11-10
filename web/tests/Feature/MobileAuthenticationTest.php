<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_mobile_registration_aliases_are_disabled(): void
    {
        foreach ([
            '/api/register',
            '/api/auth/register',
            '/api/v1/register',
            '/api/v1/auth/register',
        ] as $path) {
            $this->postJson($path, [])->assertNotFound();
        }
    }

    public function test_owner_cannot_sign_in_or_refresh_a_mobile_access_token(): void
    {
        $business = $this->activeBusiness();
        $owner = User::factory()->owner()->create([
            'email' => 'owner@example.test',
            'password' => 'password123',
            'business_id' => $business->id,
        ]);
        $business->update(['owner_id' => $owner->id]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email,
            'password' => 'password123',
        ])->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_staff_mobile_sign_in_remains_available(): void
    {
        $business = $this->activeBusiness();
        $staff = User::factory()->staff()->create([
            'email' => 'staff@example.test',
            'password' => 'password123',
            'business_id' => $business->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_owner_password_change_allows_forced_reset_staff_to_sign_in_again(): void
    {
        $business = $this->activeBusiness();
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);
        $staff = User::factory()->staff()->create([
            'business_id' => $business->id,
            'password' => 'OldStrongPassword1!',
            'must_reset_password' => true,
        ]);
        $staff->createToken('old-mobile-session');

        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'OldStrongPassword1!',
        ])->assertForbidden();

        $this->actingAs($owner)->put(route('staff.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'role' => 'staff',
            'password' => 'NewStrongPassword1!',
            'password_confirmation' => 'NewStrongPassword1!',
        ])->assertRedirect(route('staff.index'));

        $this->assertFalse($staff->fresh()->must_reset_password);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'OldStrongPassword1!',
        ])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'NewStrongPassword1!',
        ])->assertOk()->assertJsonPath('user.role', 'staff');
    }

    public function test_owner_sees_why_a_staff_password_change_was_rejected(): void
    {
        $this->withoutVite();
        $business = $this->activeBusiness();
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);

        $this->actingAs($owner)->put(route('staff.update', $staff), [
            'user_id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'role' => 'staff',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ])->assertSessionHasErrors('password');

        $this->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Could not save this user.')
            ->assertSee('uppercase and lowercase letters');
    }

    private function activeBusiness(): Business
    {
        return Business::create([
            'name' => 'Mobile Auth Cafe',
            'status' => 'active',
            'active' => true,
        ]);
    }
}
