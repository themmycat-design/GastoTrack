<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerInventoryPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_inventory_summary_filters_and_activity_are_business_scoped(): void
    {
        [$business, $owner, $staff] = $this->accounts('Cafe');
        $available = $this->stock($business, 'Coffee beans', 10, 3, 5);
        $low = $this->stock($business, 'Milk', 2, 3, 3);
        $out = $this->stock($business, 'Cups', 0, 10, 1);
        $this->stock($business, 'Inactive syrup', 100, 10, 1, false);
        $this->movement($available, $staff, 5);
        [$otherBusiness, , $otherStaff] = $this->accounts('Other cafe');
        $private = $this->stock($otherBusiness, 'Private stock', 99, 10, 100);
        $this->movement($private, $otherStaff, 1);

        $this->actingAs($owner)->get(route('stock.index'))
            ->assertOk()
            ->assertViewHas('totalItems', 3)
            ->assertViewHas('inStockCount', 1)
            ->assertViewHas('lowStockCount', 1)
            ->assertViewHas('outOfStockCount', 1)
            ->assertViewHas('totalValue', fn ($value): bool => (float) $value === 56.0)
            ->assertViewHas('recentMovements', fn ($movements): bool => $movements->count() === 1 && $movements->first()->stock_name === 'Coffee beans')
            ->assertSee('Stock overview')
            ->assertSee('Recent activity')
            ->assertDontSee('Private stock');

        $this->actingAs($owner)->get(route('stock.index', ['stock_status' => 'low', 'active' => 1]))
            ->assertOk()
            ->assertViewHas('stockItems', fn ($items): bool => $items->total() === 1 && $items->first()->id === $low->id);
        $this->actingAs($owner)->get(route('stock.index', ['stock_status' => 'out', 'active' => 1]))
            ->assertOk()
            ->assertViewHas('stockItems', fn ($items): bool => $items->total() === 1 && $items->first()->id === $out->id);
        $this->actingAs($owner)->get(route('stock.index', ['stock_status' => 'in', 'active' => 1]))
            ->assertOk()
            ->assertViewHas('stockItems', fn ($items): bool => $items->total() === 1 && $items->first()->id === $available->id);
        $this->actingAs($owner)->get(route('stock.index', ['low_stock' => 1, 'active' => 1]))
            ->assertOk()
            ->assertViewHas('stockItems', fn ($items): bool => $items->total() === 2);
    }

    public function test_inventory_csv_is_owner_only_and_excludes_other_business_records(): void
    {
        [$business, $owner, $staff] = $this->accounts('Cafe');
        $this->stock($business, '=Milk', 2, 3, 5);
        $this->stock($business, 'Inactive syrup', 4, 1, 5, false);
        [$otherBusiness] = $this->accounts('Other cafe');
        $this->stock($otherBusiness, 'Private stock', 5, 1, 5);

        $this->actingAs($staff)->get(route('stock.export'))->assertForbidden();
        $response = $this->actingAs($owner)->get(route('stock.export'))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Milk", $csv);
        $this->assertStringContainsString('Inactive syrup', $csv);
        $this->assertStringNotContainsString('Private stock', $csv);
    }

    private function accounts(string $name): array
    {
        $business = Business::create(['name' => $name, 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);

        return [$business, $owner, $staff];
    }

    private function stock(Business $business, string $name, float $current, float $minimum, float $cost, bool $active = true): StockItem
    {
        return StockItem::create([
            'business_id' => $business->id,
            'name' => $name,
            'unit' => 'kg',
            'current_quantity' => $current,
            'minimum_quantity' => $minimum,
            'unit_cost' => $cost,
            'active' => $active,
        ]);
    }

    private function movement(StockItem $stock, User $staff, float $quantity): void
    {
        StockMovement::create([
            'stock_item_id' => $stock->id,
            'user_id' => $staff->id,
            'type' => 'in',
            'quantity' => $quantity,
            'previous_quantity' => 5,
            'new_quantity' => 5 + $quantity,
        ]);
    }
}
