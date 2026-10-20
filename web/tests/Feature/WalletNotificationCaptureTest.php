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
            'currency' => 'PHP', 'merchant' => 'Alex Rivera',
            'counterparty_name' => 'Alex Rivera', 'reference' => 'GCASH-REF-2026-00125',
            'message' => 'You received PHP 125.00 from Alex Rivera. Ref No: GCASH-REF-2026-00125',
            'captured_at' => '2026-10-11T08:00:00Z',
        ];

        $this->postJson('/api/v1/wallet-notifications/batch', ['captures' => [$capture]])
            ->assertOk()->assertJsonPath('saved_ids.0', $capture['client_id']);
        $this->postJson('/api/v1/wallet-notifications/batch', ['captures' => [$capture]])->assertOk();
        $this->assertDatabaseCount('wallet_notification_captures', 1);
        $this->assertDatabaseHas('wallet_notification_captures', [
            'client_id' => $capture['client_id'],
            'counterparty_name' => 'Alex Rivera',
            'reference' => 'GCASH-REF-2026-00125',
        ]);
        $this->assertSame(0, Transaction::count());
        $this->getJson('/api/v1/wallet-notifications')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('captures.0.source', 'gcash')
            ->assertJsonPath('captures.0.counterparty_name', 'Alex Rivera')
            ->assertJsonPath('captures.0.reference', 'GCASH-REF-2026-00125');
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

    public function test_only_completed_transaction_signals_are_accepted_and_listed(): void
    {
        $business = Business::create(['name' => 'Green Cup', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);

        $this->assertFalse(WalletNotificationCapture::isTransactionSignal(
            'gcash', 'sent', 79.50, 'You have a present worth PHP 79.50.'
        ));

        WalletNotificationCapture::create([
            'client_id' => 'f694e06f-bac8-4d09-9461-58c3db4f290d',
            'business_id' => $business->id, 'user_id' => $staff->id,
            'source' => 'gcash', 'direction' => 'received', 'amount' => 250,
            'currency' => 'PHP', 'message' => 'Special offer: You received PHP 250.00 in wallet credits',
            'captured_at' => now(),
        ]);
        WalletNotificationCapture::create([
            'client_id' => '6a3eec58-3512-4aab-9cdd-90ce790f4c67',
            'business_id' => $business->id, 'user_id' => $staff->id,
            'source' => 'maya', 'direction' => 'sent', 'amount' => 79.5,
            'currency' => 'PHP', 'counterparty_name' => 'Green Cup',
            'reference' => 'MAYA-REF-20261019-0795',
            'message' => 'You paid PHP 79.50 to Green Cup. Reference No: MAYA-REF-20261019-0795',
            'captured_at' => now(),
        ]);

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/wallet-notifications')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('captures.0.source', 'maya');
        $this->postJson('/api/v1/wallet-notifications/batch', ['captures' => [[
            'client_id' => '3e5beb95-65ef-4939-b759-73db312d8298',
            'source' => 'gcash', 'direction' => 'received', 'amount' => 250,
            'currency' => 'PHP', 'message' => 'Special offer: You received PHP 250.00 in wallet credits',
            'captured_at' => now()->toISOString(),
        ]]])->assertUnprocessable();
        $this->assertDatabaseCount('wallet_notification_captures', 2);

        $this->actingAs($owner)->get(route('transactions.index', ['view' => 'raw']))
            ->assertOk()
            ->assertSee('You paid PHP 79.50 to Green Cup')
            ->assertSee('Green Cup')
            ->assertSee('MAYA-REF-20261019-0795')
            ->assertSee('PHP 79.50')
            ->assertDontSee('Special offer');
    }
}
