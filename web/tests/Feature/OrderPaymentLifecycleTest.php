<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private User $staff;
    private StockItem $stock;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::create(['name' => 'Harbor Coffee', 'status' => 'active', 'active' => true]);
        $this->staff = User::factory()->staff()->create(['business_id' => $this->business->id]);
        $this->stock = StockItem::create([
            'business_id' => $this->business->id,
            'name' => 'Whole milk',
            'unit' => 'ml',
            'current_quantity' => 1000,
            'minimum_quantity' => 100,
            'unit_cost' => 0.08,
            'active' => true,
        ]);
        $this->product = Product::create([
            'business_id' => $this->business->id,
            'name' => 'Cafe latte',
            'category' => 'Coffee',
            'price' => 120,
            'active' => true,
            'is_available' => true,
        ]);
        ProductIngredient::create([
            'product_id' => $this->product->id,
            'stock_item_id' => $this->stock->id,
            'quantity' => 200,
        ]);

        Sanctum::actingAs($this->staff);
    }

    public function test_order_keeps_recipe_variants_separate_and_deducts_edited_amounts(): void
    {
        $sugar = StockItem::create([
            'business_id' => $this->business->id, 'name' => 'Sugar', 'unit' => 'g',
            'current_quantity' => 100, 'minimum_quantity' => 0, 'unit_cost' => 0.1, 'active' => true,
        ]);
        ProductIngredient::create([
            'product_id' => $this->product->id, 'stock_item_id' => $sugar->id, 'quantity' => 20,
        ]);

        $payload = [
            'client_id' => 'b4907912-8ca3-4c14-aad8-71fb1bbb15ba',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $this->product->id, 'quantity' => 1,
                    'ingredient_overrides' => [['stock_item_id' => $sugar->id, 'quantity' => 0]]],
                ['product_id' => $this->product->id, 'quantity' => 1,
                    'ingredient_overrides' => [['stock_item_id' => $sugar->id, 'quantity' => 10]]],
            ],
        ];

        $order = $this->postJson('/api/v1/orders', $payload)->assertCreated()->json('order');
        $this->assertCount(3, $order['items']);
        $this->assertSame(400.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertSame(70.0, (float) $sugar->fresh()->current_quantity);
        $sugarAmounts = collect($order['items'])->map(fn ($item) => collect($item['ingredients_snapshot'])
            ->firstWhere('stock_item_id', $sugar->id)['quantity'])->sort()->values()->all();
        $this->assertEquals([0, 10, 20], $sugarAmounts);
        $this->postJson('/api/v1/orders', $payload)->assertOk()->assertJsonPath('order.id', $order['id']);
        $this->assertDatabaseCount('orders', 1);

        $changed = $payload;
        $changed['items'][1]['ingredient_overrides'][0]['quantity'] = 5;
        $this->postJson('/api/v1/orders', $changed)->assertStatus(409);
        $this->assertSame(70.0, (float) $sugar->fresh()->current_quantity);
    }

    public function test_order_rejects_ingredient_increases_and_unknown_recipe_ingredients(): void
    {
        $payload = [
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1,
                'ingredient_overrides' => [['stock_item_id' => $this->stock->id, 'quantity' => 201]]]],
        ];
        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable();
        $payload['items'][0]['ingredient_overrides'][0] = ['stock_item_id' => $this->stock->id + 9999, 'quantity' => 0];
        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
    }

    public function test_offline_cash_order_sync_keeps_customized_recipe_variants(): void
    {
        $sugar = StockItem::create([
            'business_id' => $this->business->id, 'name' => 'Sugar', 'unit' => 'g',
            'current_quantity' => 100, 'minimum_quantity' => 0, 'active' => true,
        ]);
        ProductIngredient::create([
            'product_id' => $this->product->id, 'stock_item_id' => $sugar->id, 'quantity' => 20,
        ]);
        $payload = [
            'client_id' => 'b67ee043-5854-41ae-afcb-ae71862c29b7',
            'payment_method' => 'cash',
            'offline_recorded_at' => now()->subMinutes(5)->toIso8601String(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'expected_unit_price' => '120.00'],
                ['product_id' => $this->product->id, 'quantity' => 1, 'expected_unit_price' => '120.00',
                    'ingredient_overrides' => [['stock_item_id' => $sugar->id, 'quantity' => 0]]],
            ],
        ];

        $this->postJson('/api/v1/orders', $payload)->assertCreated()->assertJsonCount(2, 'order.items');
        $this->assertSame(600.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertSame(80.0, (float) $sugar->fresh()->current_quantity);
        $this->postJson('/api/v1/orders', $payload)->assertOk();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_offline_cash_order_sync_uses_original_sale_day_and_replays_once(): void
    {
        $recordedAt = now()->subDays(2)->startOfDay()->addHours(4)->toIso8601String();
        $payload = [
            'client_id' => '79c93602-0e2d-4d9e-a194-84b97cf25628',
            'payment_method' => 'cash',
            'offline_recorded_at' => $recordedAt,
            'items' => [[
                'product_id' => $this->product->id, 'quantity' => 1,
                'expected_unit_price' => '120.00',
            ]],
        ];

        $created = $this->postJson('/api/v1/orders', $payload)->assertCreated()
            ->assertJsonPath('order.status', 'completed')->json('order');
        $this->postJson('/api/v1/orders', $payload)->assertOk()->assertJsonPath('order.id', $created['id']);

        $order = Order::findOrFail($created['id']);
        $this->assertSame($recordedAt, $order->offline_recorded_at->toIso8601String());
        $this->assertSame($order->offline_recorded_at->timestamp, $order->completed_at->timestamp);
        $this->assertSame($order->offline_recorded_at->timestamp, $order->created_at->timestamp);
        $this->assertSame($order->offline_recorded_at->setTimezone(config('business.timezone'))->toDateString(),
            Transaction::where('order_id', $order->id)->firstOrFail()->transaction_date->toDateString());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(800.0, (float) $this->stock->fresh()->current_quantity);
    }

    public function test_offline_cash_order_rejects_changed_price_without_creating_a_sale(): void
    {
        $this->product->update(['price' => 130]);
        $this->postJson('/api/v1/orders', [
            'client_id' => 'ad248c11-450a-4587-8787-647e12e640b3',
            'payment_method' => 'cash',
            'offline_recorded_at' => now()->subMinutes(10)->toIso8601String(),
            'items' => [[
                'product_id' => $this->product->id, 'quantity' => 1,
                'expected_unit_price' => '120.00',
            ]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
    }

    public function test_cashless_notification_is_only_a_signal_and_idempotent_order_commits_once_after_staff_confirmation(): void
    {
        $payload = [
            'client_id' => '8ac29654-54ec-4fee-b0cf-8b4ecdd52d3a',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'gcash',
        ];

        $created = $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.payment_status', 'pending')
            ->json('order');

        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertSame(0, Transaction::count());

        $replayed = $this->postJson('/api/v1/orders', $payload)
            ->assertOk()
            ->assertJsonPath('order.id', $created['id'])
            ->json('order');
        $this->assertSame($created['id'], $replayed['id']);
        $this->assertDatabaseCount('orders', 1);

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/detected', [
            'amount' => 240,
            'currency' => 'PHP',
            'source' => 'gcash',
        ])->assertOk()
            ->assertJsonPath('payment_verified', false)
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.payment_status', 'detected');

        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertSame(0, Transaction::count());

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm', [
            'amount' => 239,
            'currency' => 'PHP',
            'reference' => 'GC-ORDER-8831',
        ])->assertUnprocessable();

        $confirmation = [
            'amount' => 240,
            'currency' => 'PHP',
            'reference' => 'GC-ORDER-8831',
        ];
        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm', $confirmation)
            ->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.payment_status', 'confirmed')
            ->assertJsonPath('payment_verified', false)
            ->assertJsonPath('payment_manually_confirmed', true);

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm', $confirmation)->assertOk();

        $this->assertSame(600.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('transactions', [
            'order_id' => $created['id'],
            'type' => 'income',
            'amount' => 240,
            'entry_method' => 'order_system',
        ]);
        $this->assertDatabaseHas('order_payment_events', [
            'order_id' => $created['id'],
            'user_id' => $this->staff->id,
            'event_type' => 'payment_manually_confirmed',
            'reference' => 'GC-ORDER-8831',
        ]);
    }

    public function test_exact_wallet_notification_match_auto_confirms_and_commits_order_once(): void
    {
        $created = $this->postJson('/api/v1/orders', [
            'client_id' => '8ac29654-54ec-4fee-b0cf-8b4ecdd52d3a',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'gcash',
        ])->assertCreated()->json('order');

        $signal = [
            'amount' => 120,
            'currency' => 'PHP',
            'source' => 'gcash',
            'reference' => 'GCASH-REF-20261012-0001',
            'notification_id' => '7e2e640f-c116-4294-9282-52f413523458',
        ];

        DB::table('wallet_notification_captures')->insert([
            'client_id' => $signal['notification_id'],
            'business_id' => $this->staff->business_id,
            'user_id' => $this->staff->id,
            'source' => 'gcash',
            'direction' => 'received',
            'amount' => 120,
            'currency' => 'PHP',
            'reference' => $signal['reference'],
            'message' => 'You received PHP 120.00. Ref '.$signal['reference'],
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm-notification', $signal)
            ->assertOk()
            ->assertJsonPath('payment_verified', false)
            ->assertJsonPath('payment_auto_matched', true)
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.payment_status', 'confirmed')
            ->assertJsonPath('order.payment_reference', 'GCASH-REF-20261012-0001');

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm-notification', $signal)
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'confirmed');

        $this->assertSame(800.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('orders', [
            'id' => $created['id'],
            'payment_reference' => 'GCASH-REF-20261012-0001',
        ]);
        $event = DB::table('order_payment_events')
            ->where('order_id', $created['id'])
            ->where('event_type', 'device_payment_notification_auto_confirmed')
            ->first();
        $this->assertNotNull($event);
        $details = json_decode($event->details, true);
        $this->assertFalse($details['provider_api_verified']);
        $this->assertFalse($details['authoritative']);
    }

    public function test_wallet_notification_auto_confirmation_requires_exact_order_wallet_and_amount(): void
    {
        $created = $this->postJson('/api/v1/orders', [
            'client_id' => '192a4ee5-cded-4da0-bef1-d8146585e2de',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'gcash',
        ])->assertCreated()->json('order');

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm-notification', [
            'amount' => 120,
            'currency' => 'PHP',
            'source' => 'gcash',
            'notification_id' => 'c650ac17-a85d-4aec-85e1-94ec75c51c44',
        ])->assertUnprocessable()->assertJsonValidationErrors('reference');

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm-notification', [
            'amount' => 119,
            'currency' => 'PHP',
            'source' => 'gcash',
            'reference' => 'GCASH-REF-20261012-0002',
            'notification_id' => 'bf054e4a-91c0-40c4-a107-255350807f33',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm-notification', [
            'amount' => 120,
            'currency' => 'PHP',
            'source' => 'maya',
            'reference' => 'MAYA-REF-20261012-0003',
            'notification_id' => '2616d6c3-e2a6-4c93-93d5-9a2660d8a5d9',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $created['id'],
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_wallet_auto_confirmation_rejects_a_signal_not_saved_by_the_staff_device(): void
    {
        $created = $this->postJson('/api/v1/orders', [
            'client_id' => '825b56b0-dc2a-4542-bccd-9cad188e2c68',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'gcash',
        ])->assertCreated()->json('order');

        $this->postJson('/api/v1/orders/'.$created['id'].'/payments/confirm-notification', [
            'amount' => 120,
            'currency' => 'PHP',
            'source' => 'gcash',
            'reference' => 'UNSAVED-REF-01',
            'notification_id' => '0cdf8a2d-27f5-4d24-9f6c-0ff8421c2c4c',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $created['id'], 'status' => 'pending']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_four_digit_order_numbers_are_consecutive_per_business_and_retries_reuse_the_number(): void
    {
        $payload = [
            'client_id' => '192a4ee5-cded-4da0-bef1-d8146585e2de',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ];
        $first = $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()->assertJsonPath('order.order_number', '1001')->json('order');
        $this->postJson('/api/v1/orders', $payload)
            ->assertOk()->assertJsonPath('order.id', $first['id'])
            ->assertJsonPath('order.order_number', '1001');

        $this->postJson('/api/v1/orders', [
            'client_id' => 'd06b949f-1eeb-47a9-ace8-67643638029d',
            'items' => $payload['items'],
            'payment_method' => 'cash',
        ])->assertCreated()->assertJsonPath('order.order_number', '1002');

        Order::findOrFail($first['id'])->delete();
        $this->postJson('/api/v1/orders', [
            'client_id' => '2449526a-dd5a-4b71-bb48-8ea5d2611fbe',
            'items' => $payload['items'],
            'payment_method' => 'cash',
        ])->assertCreated()->assertJsonPath('order.order_number', '1003');

        $otherBusiness = Business::create(['name' => 'Second Coffee Shop', 'status' => 'active', 'active' => true]);
        $otherStaff = User::factory()->staff()->create(['business_id' => $otherBusiness->id]);
        $otherProduct = Product::create([
            'business_id' => $otherBusiness->id, 'name' => 'Tea', 'category' => 'Drinks',
            'price' => 50, 'active' => true, 'is_available' => true,
        ]);
        Sanctum::actingAs($otherStaff);
        $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $otherProduct->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])->assertCreated()->assertJsonPath('order.order_number', '1001');

        $this->assertSame(1004, (int) DB::table('businesses')->where('id', $this->business->id)->value('next_order_number'));
        $this->assertSame(1002, (int) DB::table('businesses')->where('id', $otherBusiness->id)->value('next_order_number'));
    }

    public function test_four_digit_sequence_stops_after_9999_without_creating_an_order(): void
    {
        DB::table('businesses')->where('id', $this->business->id)->update(['next_order_number' => 9999]);
        $payload = [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ];
        $this->postJson('/api/v1/orders', $payload)
            ->assertCreated()->assertJsonPath('order.order_number', '9999');
        $this->postJson('/api/v1/orders', $payload)->assertStatus(409);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_order_sale_uses_the_shop_day_after_utc_midnight_boundary(): void
    {
        config()->set('business.timezone', 'Asia/Manila');
        Carbon::setTestNow(Carbon::parse('2026-10-11 16:30:00', 'UTC'));

        try {
            $order = $this->postJson('/api/v1/orders', [
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ])->assertCreated()->json('order');

            $this->assertDatabaseHas('transactions', [
                'order_id' => $order['id'],
                'transaction_date' => '2026-10-12',
            ]);
            $this->postJson('/api/v1/transactions', [
                'amount' => 15,
                'type' => 'expense',
                'source' => 'Cash',
                'category' => 'Supplies',
                'date' => '2026-10-12',
            ])->assertCreated();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pending_order_can_be_cancelled_without_financial_or_stock_effects(): void
    {
        $order = $this->postJson('/api/v1/orders', [
            'client_id' => '30c824eb-e2f0-4a81-bd1e-9d89e10a53fd',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'maya',
        ])->assertCreated()->json('order');

        $this->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
            'reason' => 'Customer changed their mind before paying.',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
            'reason' => 'Customer changed their mind before paying.',
            'payment_not_received' => true,
        ])->assertOk()->assertJsonPath('order.status', 'cancelled');
        $this->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
            'reason' => 'Duplicate cancellation request.',
            'payment_not_received' => true,
        ])->assertOk();

        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('order_payment_events', [
            'order_id' => $order['id'],
            'user_id' => $this->staff->id,
            'event_type' => 'order_cancelled_before_payment',
            'reason' => 'Customer changed their mind before paying.',
        ]);
    }

    public function test_legacy_order_update_cancellation_requires_and_forwards_no_payment_attestation(): void
    {
        $order = $this->postJson('/api/v1/orders', [
            'client_id' => '38f4cf76-9777-427b-8af1-b60f387ea24f',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'gcash',
        ])->assertCreated()->json('order');

        $cancelPayload = [
            'status' => 'cancelled',
            'cancel_reason' => 'Customer cancelled before paying.',
        ];

        $this->putJson('/api/v1/orders/'.$order['id'], $cancelPayload)->assertUnprocessable();
        $this->putJson('/api/v1/orders/'.$order['id'], $cancelPayload + [
            'payment_not_received' => true,
        ])->assertOk()->assertJsonPath('order.status', 'cancelled');

        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('order_payment_events', [
            'order_id' => $order['id'],
            'event_type' => 'order_cancelled_before_payment',
            'reason' => 'Customer cancelled before paying.',
        ]);
    }

    public function test_payment_cannot_be_marked_failed_without_staff_attesting_no_payment_was_received(): void
    {
        $order = $this->postJson('/api/v1/orders', [
            'client_id' => 'b5149114-a1af-49a6-9dc9-33269384942d',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'maya',
        ])->assertCreated()->json('order');

        $this->postJson('/api/v1/orders/'.$order['id'].'/payments/fail', [
            'reason' => 'Payment did not arrive.',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/orders/'.$order['id'].'/payments/fail', [
            'reason' => 'Staff checked the wallet account.',
            'payment_not_received' => true,
        ])->assertOk()->assertJsonPath('order.payment_status', 'failed');

        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('order_payment_events', [
            'order_id' => $order['id'],
            'user_id' => $this->staff->id,
            'event_type' => 'payment_marked_failed',
        ]);
    }

    public function test_owner_cannot_confirm_a_staff_payment_attestation(): void
    {
        $order = $this->postJson('/api/v1/orders', [
            'client_id' => '98b12139-94b3-4fd6-a1cb-8ab79322f42d',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'maya',
        ])->assertCreated()->json('order');

        $owner = User::factory()->owner()->create(['business_id' => $this->business->id]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/orders/'.$order['id'].'/payments/confirm', [
            'amount' => 120,
            'currency' => 'PHP',
            'reference' => 'MAYA-OWNER-ATTEMPT',
        ])->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'payment_status' => 'pending']);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
    }

    public function test_manual_refund_is_pending_until_recorded_and_reversal_is_applied_once(): void
    {
        $order = $this->postJson('/api/v1/orders', [
            'client_id' => '44db6d6c-3241-4580-86ce-37405df710ee',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])->assertCreated()->json('order');

        $this->assertSame(800.0, (float) $this->stock->fresh()->current_quantity);
        $this->postJson('/api/v1/orders/'.$order['id'].'/refund/request', [
            'reason' => 'Customer returned the order.',
        ])->assertOk()->assertJsonPath('external_refund_performed', false)
            ->assertJsonPath('order.payment_status', 'refund_pending');
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(800.0, (float) $this->stock->fresh()->current_quantity);

        $details = ['amount' => 120, 'currency' => 'PHP', 'reference' => 'CASH-REFUND-410', 'restock' => true];
        $this->postJson('/api/v1/orders/'.$order['id'].'/refund/confirm', $details)
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'refunded')
            ->assertJsonPath('external_refund_performed', false);
        $this->postJson('/api/v1/orders/'.$order['id'].'/refund/confirm', $details)->assertOk();

        $this->assertSame(1000.0, (float) $this->stock->fresh()->current_quantity);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('transactions', [
            'reversal_of_id' => Transaction::where('order_id', $order['id'])->value('id'),
            'entry_method' => 'order_reversal',
            'type' => 'expense',
        ]);
    }
}
