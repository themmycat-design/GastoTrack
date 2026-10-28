<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockAdjustmentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_changes_stock_once_and_preserves_four_decimal_quantities(): void
    {
        $business = Business::create(['name' => 'Synthetic inventory', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $stock = StockItem::create([
            'business_id' => $business->id, 'name' => 'Milk', 'unit' => 'L',
            'current_quantity' => 1, 'minimum_quantity' => 0, 'unit_cost' => 25,
        ]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('stock-retry')->plainTextToken];
        $payload = [
            'client_id' => (string) Str::uuid(), 'type' => 'deduct', 'quantity' => '0.1250',
            'reason' => 'Measured waste', 'movement_category' => 'waste',
            'offline_recorded_at' => now()->subDay()->toIso8601String(),
        ];

        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', $payload, $headers)->assertOk();
        $this->assertSame('0.8750', $stock->fresh()->current_quantity);
        $this->assertSame(1, StockMovement::where('client_id', $payload['client_id'])->count());
        $movement = StockMovement::where('client_id', $payload['client_id'])->firstOrFail();
        $this->assertSame($payload['offline_recorded_at'], $movement->offline_recorded_at->toIso8601String());
        $this->assertSame($movement->offline_recorded_at->timestamp, $movement->created_at->timestamp);

        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', $payload + [], $headers)->assertOk();
        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', array_replace($payload, ['quantity' => '0.2500']), $headers)
            ->assertStatus(409);
        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', array_replace($payload, ['offline_recorded_at' => now()->toIso8601String()]), $headers)
            ->assertStatus(409);
        $this->assertSame('0.8750', $stock->fresh()->current_quantity);
    }

    public function test_another_staff_member_cannot_reuse_the_same_adjustment_identifier(): void
    {
        $business = Business::create(['name' => 'Synthetic inventory', 'status' => 'active', 'active' => true]);
        $first = User::factory()->staff()->create(['business_id' => $business->id]);
        $second = User::factory()->staff()->create(['business_id' => $business->id]);
        $stock = StockItem::create([
            'business_id' => $business->id, 'name' => 'Sugar', 'unit' => 'kg',
            'current_quantity' => 5, 'minimum_quantity' => 0, 'unit_cost' => 10,
        ]);
        $payload = ['client_id' => (string) Str::uuid(), 'type' => 'deduct', 'quantity' => 1];
        $firstHeaders = ['Authorization' => 'Bearer '.$first->createToken('first')->plainTextToken];
        $secondHeaders = ['Authorization' => 'Bearer '.$second->createToken('second')->plainTextToken];

        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', $payload, $firstHeaders)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', $payload, $secondHeaders)->assertForbidden();
        $this->assertSame('4.0000', $stock->fresh()->current_quantity);
    }
}
