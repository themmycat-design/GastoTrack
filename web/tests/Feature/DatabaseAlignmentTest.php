<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OwnerDashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_stock_keeps_four_decimal_costs_quantities_and_long_notes(): void
    {
        $business = Business::create(['name' => 'Synthetic cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $this->withoutVite();
        $this->actingAs($owner);

        $this->post(route('stock.store'), [
            'name' => 'Ingredient', 'unit' => 'g', 'current_quantity' => '1.2500',
            'minimum_quantity' => '0.0001', 'unit_cost' => '0.0001',
        ])->assertRedirect(route('stock.index'));
        $stock = StockItem::where('business_id', $business->id)->firstOrFail();
        $this->assertSame('0.0001', $stock->unit_cost);

        $reason = str_repeat('R', 500);
        $this->post(route('stock.adjust', $stock), [
            'type' => 'subtract', 'quantity' => '0.1250', 'reason' => $reason,
            'movement_category' => 'waste',
        ])->assertOk()->assertJsonPath('new_quantity', '1.1250');

        $opening = StockMovement::where('stock_item_id', $stock->id)
            ->where('movement_category', 'opening')->firstOrFail();
        $this->assertSame('1.2500', $opening->quantity);
        $movement = StockMovement::where('stock_item_id', $stock->id)
            ->where('movement_category', 'waste')->firstOrFail();
        $this->assertSame($reason, $movement->reason);
        $this->assertSame('0.0001', $movement->unit_cost_at_movement);
        $this->assertSame('1.1250', $stock->fresh()->current_quantity);

        $this->post(route('stock.adjust', $stock), [
            'type' => 'add', 'quantity' => '0.00001',
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(2, StockMovement::where('stock_item_id', $stock->id)->count());
    }

    public function test_staff_stock_cost_validation_matches_database_precision(): void
    {
        $business = Business::create(['name' => 'Synthetic cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        Sanctum::actingAs($staff);
        $payload = [
            'name' => 'Fine spice', 'unit' => 'g', 'current_quantity' => '100.0000',
            'minimum_quantity' => '0.0000', 'unit_cost' => '0.0001',
        ];

        $this->postJson('/api/v1/stock', $payload)->assertCreated()
            ->assertJsonPath('item.unit_cost', '0.0001');
        $this->postJson('/api/v1/stock', array_replace($payload, ['unit_cost' => '0.00001']))
            ->assertUnprocessable()->assertJsonValidationErrors('unit_cost');
    }

    public function test_business_currency_is_saved_before_financial_setup_and_locked_after_it(): void
    {
        $business = Business::create(['name' => 'Synthetic cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/business', ['currency' => 'usd'])->assertOk()
            ->assertJsonPath('business.currency', 'USD');
        StockItem::create([
            'business_id' => $business->id, 'name' => 'Ingredient', 'unit' => 'g',
            'current_quantity' => 1, 'minimum_quantity' => 0, 'unit_cost' => 1,
        ]);
        $this->putJson('/api/v1/business', ['currency' => 'PHP'])->assertUnprocessable();
        $this->assertSame('USD', $business->fresh()->currency);
    }

    public function test_waste_uses_sub_cent_snapshot_cost_before_rounding_final_total(): void
    {
        $business = Business::create(['name' => 'Synthetic cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $stock = StockItem::create([
            'business_id' => $business->id, 'name' => 'Spice', 'unit' => 'g',
            'current_quantity' => 100, 'minimum_quantity' => 0, 'unit_cost' => '0.0001',
        ]);
        StockMovement::create([
            'stock_item_id' => $stock->id, 'user_id' => $staff->id, 'type' => 'out',
            'quantity' => 100, 'previous_quantity' => 100, 'new_quantity' => 0,
            'movement_category' => 'waste', 'unit_cost_at_movement' => '0.0001',
        ]);

        $today = CarbonImmutable::now();
        $costs = app(OwnerDashboardMetrics::class)->wasteCostsForOwner($owner, $today->startOfDay(), $today->endOfDay());
        $this->assertSame(0.01, $costs['total']);
    }
}
