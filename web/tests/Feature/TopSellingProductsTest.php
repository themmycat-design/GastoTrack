<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
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
