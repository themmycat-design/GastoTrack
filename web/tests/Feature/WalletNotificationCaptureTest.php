<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletNotificationCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletNotificationCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_capture_is_idempotent_and_not_accounted_as_a_sale(): void
    {
        $business = Business::create(['name' => 'Brew House', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        Sanctum::actingAs($staff);
        $capture = [
            'client_id' => '4110f9cc-e7c9-48a4-9702-04bd25a7e6a1',
            'source' => 'gcash', 'direction' => 'received', 'amount' => 125,
            'currency' => 'PHP', 'message' => 'You received PHP 125.00',
            'captured_at' => '2026-10-11T08:00:00Z',
        ];

        $this->postJson('/api/v1/wallet-notifications/batch', ['captures' => [$capture]])
            ->assertOk()->assertJsonPath('saved_ids.0', $capture['client_id']);
        $this->postJson('/api/v1/wallet-notifications/batch', ['captures' => [$capture]])->assertOk();
        $this->assertDatabaseCount('wallet_notification_captures', 1);
        $this->assertSame(0, Transaction::count());
        $this->getJson('/api/v1/wallet-notifications')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('captures.0.source', 'gcash');
    }

    public function test_owner_cannot_upload_and_businesses_cannot_read_each_others_captures(): void
    {
        $first = Business::create(['name' => 'First Cafe', 'status' => 'active', 'active' => true]);
        $second = Business::create(['name' => 'Second Cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $first->id]);
        $owner = User::factory()->owner()->create(['business_id' => $second->id]);
        WalletNotificationCapture::create([
            'client_id' => 'b84ed359-f84b-45d8-a05e-b555e45f2af1',
            'business_id' => $first->id, 'user_id' => $staff->id,
            'source' => 'maya', 'direction' => 'unknown', 'currency' => 'PHP',
            'message' => 'Wallet alert', 'captured_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/wallet-notifications')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/wallet-notifications/batch', ['captures' => []])->assertForbidden();
        $this->actingAs($owner)->get(route('transactions.index', ['view' => 'raw']))
            ->assertOk()->assertDontSee('Wallet alert');
    }
}
