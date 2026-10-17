<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopSellingProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-09 12:00:00');
        CarbonImmutable::setTestNow('2026-10-09 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_top_selling_products_follow_selected_period_and_only_recorded_confirmed_sales(): void
    {
        [$business, $owner, $staff] = $this->accounts('Cafe');
        $latte = $this->product($business, 'Latte', 100);
        $tea = $this->product($business, 'Milk Tea', 50);

        $this->order($business, $staff, $latte, 2, 200, 180, 'confirmed');
        $pendingRefund = $this->order($business, $staff, $latte, 1, 100, 100, 'refund_pending');
        Transaction::where('order_id', $pendingRefund->id)->firstOrFail()->update([
            'order_id' => null,
            'metadata' => ['order_id' => $pendingRefund->id],
        ]);
        $this->order($business, $staff, $tea, 2, 100, 100, 'confirmed');
        $this->order($business, $staff, $tea, 9, 450, 450, 'confirmed', ['status' => 'cancelled']);
        $this->order($business, $staff, $latte, 20, 2000, 2000, 'refunded');
        $this->order($business, $staff, $latte, 10, 1000, 1000, 'detected');
        $this->order($business, $staff, $tea, 5, 250, 250, 'confirmed', ['record_sale' => false]);
        $this->order($business, $staff, $tea, 8, 400, 400, 'confirmed', ['completed_at' => '2026-09-30 10:00:00']);

        [$otherBusiness, , $otherStaff] = $this->accounts('Other shop');
        $privateProduct = $this->product($otherBusiness, 'Private Cake', 1000);
        $this->order($otherBusiness, $otherStaff, $privateProduct, 50, 50000, 50000, 'confirmed');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'month']))
            ->assertOk()
            ->assertViewHas('topSellingProducts', function ($products): bool {
                return $products->count() === 2
                    && $products[0]->product_name === 'Latte'
                    && (int) $products[0]->units_sold === 3
                    && (float) $products[0]->sales_amount === 280.0
                    && (float) $products[0]->sales_share === 73.7
                    && $products[1]->product_name === 'Milk Tea'
                    && (int) $products[1]->units_sold === 2
                    && (float) $products[1]->sales_amount === 100.0;
            })
            ->assertSee('Top selling products')
            ->assertSee('Latte')
            ->assertDontSee('Private Cake');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['start_date' => '2026-10-08', 'end_date' => '2026-10-08']))
            ->assertOk()
            ->assertViewHas('topSellingProducts', fn ($products): bool => $products->isEmpty())
            ->assertSee('No product sales in this period');
    }

    public function test_analytics_separates_recipe_cost_waste_and_gross_margin_from_the_ledger(): void
    {
        [$business, $owner, $staff] = $this->accounts('Costed Cafe');
        $milk = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Milk',
            'unit' => 'ml',
            'current_quantity' => 500,
            'minimum_quantity' => 50,
            'unit_cost' => 0.50,
            'active' => true,
        ]);
        $latte = $this->product($business, 'Latte', 100);
        $latte->productIngredients()->create([
            'stock_item_id' => $milk->id,
            'quantity' => 40,
        ]);
        $order = $this->order($business, $staff, $latte, 2, 200, 180, 'confirmed');
        $order->items()->firstOrFail()->update([
            'ingredients_snapshot' => [[
                'stock_item_id' => $milk->id,
                'stock_name' => 'Milk',
                'unit' => 'ml',
                'quantity' => 40,
                'unit_cost' => 0.50,
            ]],
            'product_cost_snapshot' => 0,
        ]);
        $uncosted = $this->product($business, 'Uncosted item', 20);
        $uncosted->update(['cost' => 0]);
        $this->order($business, $staff, $uncosted, 1, 20, 20, 'confirmed');
        StockMovement::create([
            'stock_item_id' => $milk->id,
            'user_id' => $staff->id,
            'type' => 'out',
            'quantity' => 10,
            'previous_quantity' => 500,
            'new_quantity' => 490,
            'reason' => 'Spoiled before closing',
            'movement_category' => 'waste',
            'unit_cost_at_movement' => 0.50,
        ]);
        $this->transaction($business, $staff, 'expense', 50, '2026-10-09');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'month']))
            ->assertOk()
            ->assertViewHas('costInsights', fn (array $costs): bool =>
                $costs['ingredientCost'] === 40.0
                && $costs['grossMargin'] === 140.0
                && $costs['costedSales'] === 180.0
                && $costs['costCoveragePercent'] === 90.0
                && $costs['wasteCost'] === 5.0
                && $costs['wasteCostEstimated'] === false
            )
            ->assertViewHas('topSellingProducts', fn ($products): bool =>
                $products->count() === 2
                && $products[0]->ingredient_cost === 40.0
                && $products[0]->gross_margin === 140.0
                && $products[0]->gross_margin_percent === 77.8
                && $products[0]->cost_basis === 'Recipe cost'
                && $products[1]->gross_margin === null
                && $products[1]->cost_basis === 'Missing cost'
            )
            ->assertViewHas('current', fn (array $ledger): bool => $ledger['income'] === 200.0 && $ledger['expenses'] === 50.0)
            ->assertSee('Ingredient and product cost insights')
            ->assertSee('Recorded waste cost')
            ->assertSee('These do not change recorded income or expenses.');
    }

    public function test_owner_can_open_cost_logs_for_the_selected_period_and_only_their_business(): void
    {
        [$business, $owner, $staff] = $this->accounts('Cost log cafe');
        $milk = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Milk',
            'unit' => 'ml',
            'current_quantity' => 500,
            'minimum_quantity' => 50,
            'unit_cost' => 0.50,
            'active' => true,
        ]);
        $latte = $this->product($business, 'Latte', 100);
        $latte->productIngredients()->create(['stock_item_id' => $milk->id, 'quantity' => 40]);
        $order = $this->order($business, $staff, $latte, 2, 200, 180, 'confirmed');
        $order->items()->firstOrFail()->update([
            'ingredients_snapshot' => [[
                'stock_item_id' => $milk->id,
                'stock_name' => 'Milk',
                'unit' => 'ml',
                'quantity' => 40,
                'unit_cost' => 0.50,
            ]],
        ]);
        StockMovement::create([
            'stock_item_id' => $milk->id,
            'user_id' => $staff->id,
            'type' => 'out',
            'quantity' => 10,
            'previous_quantity' => 500,
            'new_quantity' => 490,
            'reason' => 'Milk spoiled',
            'movement_category' => 'waste',
            'unit_cost_at_movement' => 0.50,
        ]);

        [$otherBusiness, , $otherStaff] = $this->accounts('Other cost log cafe');
        $privateStock = StockItem::create([
            'business_id' => $otherBusiness->id,
            'name' => 'Private stock',
            'unit' => 'kg',
            'current_quantity' => 10,
            'minimum_quantity' => 1,
            'unit_cost' => 100,
            'active' => true,
        ]);
        StockMovement::create([
            'stock_item_id' => $privateStock->id,
            'user_id' => $otherStaff->id,
            'type' => 'out',
            'quantity' => 1,
            'previous_quantity' => 10,
            'new_quantity' => 9,
            'reason' => 'Private waste entry',
            'movement_category' => 'waste',
            'unit_cost_at_movement' => 100,
        ]);

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'month']))
            ->assertOk()
            ->assertSee(route('analytics.cost-logs', ['start_date' => '2026-10-01', 'end_date' => '2026-10-09']));

        $this->get(route('analytics.cost-logs', ['start_date' => '2026-10-09', 'end_date' => '2026-10-09']))
            ->assertOk()
            ->assertSee('Ingredient and waste cost logs')
            ->assertSee('Latte')
            ->assertSee('Milk spoiled')
            ->assertDontSee('Private stock')
            ->assertViewHas('costEntries', fn ($entries): bool =>
                $entries->count() === 1
                && $entries[0]['ingredient_cost'] === 40.0
                && $entries[0]['product_cost'] === 40.0
                && $entries[0]['gross_margin'] === 140.0
            )
            ->assertViewHas('wasteTotal', 5.0);

        $this->actingAs($staff)
            ->get(route('analytics.cost-logs'))
            ->assertForbidden();
    }

    private function accounts(string $name): array
    {
        $business = Business::create(['name' => $name, 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);

        return [$business, $owner, $staff];
    }

    private function product(Business $business, string $name, float $price): Product
    {
        return Product::create([
            'business_id' => $business->id,
            'name' => $name,
            'category' => 'Drinks',
            'price' => $price,
            'cost' => 20,
            'active' => true,
            'is_available' => true,
        ]);
    }

    private function order(
        Business $business,
        User $staff,
        Product $product,
        int $quantity,
        float $subtotal,
        float $total,
        string $paymentStatus,
        array $overrides = [],
    ): Order {
        $recordSale = $overrides['record_sale'] ?? true;
        unset($overrides['record_sale']);
        $order = Order::create(array_merge([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal' => $subtotal,
            'total' => $total,
            'payment_method' => 'cash',
            'status' => 'completed',
            'payment_status' => $paymentStatus,
            'payment_confirmed_at' => $paymentStatus === 'detected' ? null : '2026-10-09 10:00:00',
            'financials_committed_at' => '2026-10-09 10:00:00',
            'completed_at' => '2026-10-09 10:00:00',
        ], $overrides));
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ]);
        if ($recordSale) {
            Transaction::create([
                'business_id' => $business->id,
                'user_id' => $staff->id,
                'order_id' => $order->id,
                'type' => 'income',
                'source' => 'cash',
                'category' => 'Sales',
                'amount' => $total,
                'transaction_date' => '2026-10-09',
                'entry_method' => 'order_system',
            ]);
        }

        return $order;
    }
}
