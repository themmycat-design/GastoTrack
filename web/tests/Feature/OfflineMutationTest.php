<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineMutationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Business $business): User
    {
        return User::factory()->staff()->create(['business_id' => $business->id]);
    }

    public function test_replaying_stock_creation_does_not_duplicate_the_item(): void
    {
        $business = Business::create(['name' => 'Demo', 'status' => 'active', 'active' => true]);
        $staff = $this->staff($business);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline')->plainTextToken];
        $payload = ['client_id' => (string) Str::uuid(), 'kind' => 'stock_create',
            'data' => ['name' => 'Milk', 'unit' => 'L', 'current_quantity' => 4,
                'minimum_quantity' => 1, 'unit_cost' => 30]];

        $first = $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertCreated();
        $second = $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertCreated();
        $this->assertSame($first->json('item.id'), $second->json('item.id'));
        $this->assertSame(1, StockItem::where('business_id', $business->id)->count());
        $this->postJson('/api/v1/offline/mutations', array_replace($payload, ['data' => array_replace($payload['data'], ['current_quantity' => 8])]), $headers)
            ->assertStatus(409);
    }

    public function test_stale_product_edit_is_rejected_without_overwriting_newer_changes(): void
    {
        $business = Business::create(['name' => 'Demo', 'status' => 'active', 'active' => true]);
        $staff = $this->staff($business);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline')->plainTextToken];
        $product = Product::create(['business_id' => $business->id, 'name' => 'Tea',
            'category' => 'Drinks', 'price' => 80]);
        $baseline = $product->updated_at->toIso8601String();
        DB::table('products')->where('id', $product->id)->update(['price' => 90, 'updated_at' => now()->addMinute()]);

        $this->postJson('/api/v1/offline/mutations', [
            'client_id' => (string) Str::uuid(), 'kind' => 'product_update',
            'target_id' => $product->id, 'expected_updated_at' => $baseline,
            'expected_revision' => 0,
            'data' => ['price' => 70],
        ], $headers)->assertStatus(409);
        $this->assertSame('90.00', $product->fresh()->price);
    }

    public function test_staff_cannot_edit_a_product_in_another_business(): void
    {
        $firstBusiness = Business::create(['name' => 'First', 'status' => 'active', 'active' => true]);
        $secondBusiness = Business::create(['name' => 'Second', 'status' => 'active', 'active' => true]);
        $staff = $this->staff($firstBusiness);
        $product = Product::create(['business_id' => $secondBusiness->id, 'name' => 'Tea',
            'category' => 'Drinks', 'price' => 80]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline')->plainTextToken];

        $this->postJson('/api/v1/offline/mutations', [
            'client_id' => (string) Str::uuid(), 'kind' => 'product_update',
            'target_id' => $product->id, 'expected_updated_at' => $product->updated_at->toIso8601String(),
            'expected_revision' => 0,
            'data' => ['price' => 70],
        ], $headers)->assertNotFound();
        $this->assertSame('80.00', $product->fresh()->price);
    }

    public function test_product_revision_detects_an_edit_within_the_same_second(): void
    {
        $business = Business::create(['name' => 'Demo', 'status' => 'active', 'active' => true]);
        $staff = $this->staff($business);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline')->plainTextToken];
        $product = Product::create(['business_id' => $business->id, 'name' => 'Tea',
            'category' => 'Drinks', 'price' => 80]);
        $baseline = $product->updated_at->toIso8601String();
        $product->update(['price' => 90]);

        $this->assertSame(1, $product->fresh()->revision);
        $this->postJson('/api/v1/offline/mutations', [
            'client_id' => (string) Str::uuid(), 'kind' => 'product_update',
            'target_id' => $product->id, 'expected_updated_at' => $baseline,
            'expected_revision' => 0, 'data' => ['price' => 70],
        ], $headers)->assertStatus(409);
        $this->assertSame('90.00', $product->fresh()->price);
    }

    public function test_successful_product_edit_replays_without_a_second_update(): void
    {
        $business = Business::create(['name' => 'Demo', 'status' => 'active', 'active' => true]);
        $staff = $this->staff($business);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline')->plainTextToken];
        $product = Product::create(['business_id' => $business->id, 'name' => 'Tea',
            'category' => 'Drinks', 'price' => 80]);
        $payload = ['client_id' => (string) Str::uuid(), 'kind' => 'product_update',
            'target_id' => $product->id, 'expected_updated_at' => $product->updated_at->toIso8601String(),
            'expected_revision' => 0, 'data' => ['price' => 85]];

        $first = $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertOk();
        $this->assertSame('85.00', $product->fresh()->price);
        $this->assertSame(1, $product->fresh()->revision);
        $second = $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertOk();
        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, $product->fresh()->revision);
    }

    public function test_transaction_delete_replays_without_deleting_again(): void
    {
        $business = Business::create(['name' => 'Demo', 'status' => 'active', 'active' => true]);
        $staff = $this->staff($business);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline')->plainTextToken];
        $transaction = Transaction::create(['business_id' => $business->id, 'user_id' => $staff->id,
            'amount' => 35, 'type' => 'expense', 'source' => 'Cash', 'category' => 'Supplies',
            'transaction_date' => today(), 'entry_method' => 'manual']);
        $payload = ['client_id' => (string) Str::uuid(), 'kind' => 'transaction_delete',
            'target_id' => $transaction->id, 'expected_updated_at' => $transaction->updated_at->toIso8601String(),
            'expected_revision' => 0, 'data' => []];

        $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertOk();
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertOk();
    }

    public function test_stock_adjustment_rejects_a_changed_item_but_its_own_retry_remains_idempotent(): void
    {
        $business = Business::create(['name' => 'Demo', 'status' => 'active', 'active' => true]);
        $staff = $this->staff($business);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline')->plainTextToken];
        $item = StockItem::create(['business_id' => $business->id, 'name' => 'Milk', 'unit' => 'L',
            'current_quantity' => 5, 'minimum_quantity' => 1, 'unit_cost' => 30, 'active' => true]);
        $first = ['type' => 'add', 'quantity' => 1, 'reason' => 'Delivery',
            'client_id' => (string) Str::uuid(), 'expected_revision' => 0];

        $this->postJson("/api/v1/stock/{$item->id}/adjust", $first, $headers)->assertOk();
        $this->assertSame(1, $item->fresh()->revision);
        $this->postJson("/api/v1/stock/{$item->id}/adjust", $first, $headers)->assertOk();
        $this->assertSame('6.0000', $item->fresh()->current_quantity);
        $this->postJson("/api/v1/stock/{$item->id}/adjust", array_replace($first, [
            'client_id' => (string) Str::uuid(), 'quantity' => 2,
        ]), $headers)->assertStatus(409);
        $this->assertSame('6.0000', $item->fresh()->current_quantity);
    }
}
