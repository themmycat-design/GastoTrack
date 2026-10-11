<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalAccessTokenSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_token_cannot_be_reused_after_logout(): void
    {
        $business = Business::create(['name' => 'Harbor Coffee', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $token = $staff->createToken('mobile-session')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/user', $headers)->assertOk();
        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();
        $this->getJson('/api/v1/user', $headers)->assertUnauthorized();
    }

    public function test_expired_token_is_unauthorized_and_cannot_be_refreshed_without_a_live_token(): void
    {
        config(['sanctum.expiration' => 1]);
        $business = Business::create(['name' => 'Harbor Coffee', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $token = $staff->createToken('mobile-session')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->travel(2)->minutes();
        $this->getJson('/api/v1/user', $headers)->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', [], $headers)->assertUnauthorized();
        $this->travelBack();
    }

    public function test_malformed_bearer_token_is_rejected(): void
    {
        $this->getJson('/api/v1/user', ['Authorization' => 'Bearer not-a-valid-token'])->assertUnauthorized();
    }
}
