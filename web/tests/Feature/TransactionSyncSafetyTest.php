<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionSyncSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Business $business): array
    {
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        return [$staff, ['Authorization' => 'Bearer '.$staff->createToken('sync-test')->plainTextToken]];
    }

    private function item(string $id, int $amount = 245): array
    {
        return [
            'client_id' => $id, 'amount' => $amount, 'type' => 'income',
            'category' => 'Sales', 'source' => 'Cash',
            'date' => now(config('business.timezone'))->toDateString(),
            'notes' => 'Synthetic sale',
        ];
    }

    public function test_staff_cannot_take_over_another_staff_members_transaction_through_sync_or_batch(): void
    {
        $business = Business::create(['name' => 'Synthetic cafe', 'status' => 'active', 'active' => true]);
        [$owner, $ownerHeaders] = $this->staff($business);
        [, $otherHeaders] = $this->staff($business);
        $item = $this->item((string) Str::uuid());
        $this->postJson('/api/v1/sync', ['transactions' => [$item]], $ownerHeaders)->assertOk();
        $record = Transaction::where('client_id', $item['client_id'])->firstOrFail();

        $this->app['auth']->forgetGuards();
        $changed = $this->item($item['client_id'], 1);
        $this->postJson('/api/v1/sync', ['transactions' => [$changed]], $otherHeaders)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/transactions/batch', ['transactions' => [$changed]], $otherHeaders)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/transactions', $changed, $otherHeaders)->assertForbidden();

        $this->assertSame($owner->id, $record->fresh()->user_id);
        $this->assertSame('245.00', $record->fresh()->amount);
    }

    public function test_retries_are_read_only_and_conflicting_identifiers_roll_back_the_whole_batch(): void
    {
        $business = Business::create(['name' => 'Synthetic cafe', 'status' => 'active', 'active' => true]);
        [, $headers] = $this->staff($business);
        $item = $this->item((string) Str::uuid());
        $first = $this->postJson('/api/v1/sync', ['transactions' => [$item]], $headers)->assertOk();
        $id = $first->json('mappings.transactions.0.server_id');
        $this->postJson('/api/v1/sync', ['transactions' => [$item]], $headers)
            ->assertOk()->assertJsonPath('mappings.transactions.0.server_id', $id);

        $this->postJson('/api/v1/sync', ['transactions' => [
            $this->item((string) Str::uuid()), $this->item($item['client_id'], 1),
        ]], $headers)->assertStatus(409);
        $this->postJson('/api/v1/transactions/batch', ['transactions' => [$this->item($item['client_id'], 1)]], $headers)
            ->assertStatus(409);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('245.00', Transaction::findOrFail($id)->amount);
    }

    public function test_other_business_and_order_generated_identifiers_cannot_be_reused(): void
    {
        $firstBusiness = Business::create(['name' => 'First synthetic cafe', 'status' => 'active', 'active' => true]);
        $secondBusiness = Business::create(['name' => 'Second synthetic cafe', 'status' => 'active', 'active' => true]);
        [$firstStaff] = $this->staff($firstBusiness);
        [, $secondHeaders] = $this->staff($secondBusiness);
        $clientId = (string) Str::uuid();
        Transaction::create([
            'business_id' => $firstBusiness->id, 'user_id' => $firstStaff->id,
            'client_id' => $clientId, 'amount' => 245, 'type' => 'income',
            'category' => 'Sales', 'source' => 'Cash',
            'transaction_date' => now(config('business.timezone'))->toDateString(),
            'entry_method' => 'order_system',
        ]);

        $this->postJson('/api/v1/sync', ['transactions' => [$this->item($clientId)]], $secondHeaders)
            ->assertStatus(409);
        $this->assertDatabaseCount('transactions', 1);

        $firstHeaders = ['Authorization' => 'Bearer '.$firstStaff->createToken('sync-order-test')->plainTextToken];
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/sync', ['transactions' => [$this->item($clientId)]], $firstHeaders)
            ->assertStatus(409);
    }
}
