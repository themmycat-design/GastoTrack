<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletNotificationCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualWalletPaymentDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_wallet_confirmation_requires_todays_payment_date(): void
    {
        Storage::fake('local');
        $business = Business::create(['name' => 'Demo Cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $stock = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Milk',
            'unit' => 'ml',
            'current_quantity' => 1000,
            'minimum_quantity' => 100,
            'unit_cost' => 0.08,
            'active' => true,
        ]);
        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Cafe latte',
            'category' => 'Coffee',
            'price' => 120,
            'active' => true,
            'is_available' => true,
        ]);
        ProductIngredient::create([
            'product_id' => $product->id,
            'stock_item_id' => $stock->id,
            'quantity' => 200,
        ]);
        Sanctum::actingAs($staff);

        $order = $this->postJson('/api/v1/orders', [
            'payment_method' => 'maya',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('order');
        $today = now(config('business.timezone'))->toDateString();
        $payment = [
            'amount' => 120,
            'currency' => 'PHP',
            'reference' => 'MAYA-UNIQUE-12345',
            'received_on' => now(config('business.timezone'))->subDay()->toDateString(),
        ];

        $this->postJson('/api/v1/orders/'.$order['id'].'/payments/confirm', $payment)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('received_on');
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'payment_status' => 'pending']);
        $this->assertSame(1000.0, (float) $stock->fresh()->current_quantity);
        $this->assertSame(0, Transaction::count());

        $this->post('/api/v1/orders/'.$order['id'].'/payments/confirm', [
            ...$payment,
            'received_on' => $today,
            'screenshot' => UploadedFile::fake()->create('payment.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('screenshot');
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'payment_status' => 'pending']);

        $this->post('/api/v1/orders/'.$order['id'].'/payments/confirm', [
            ...$payment,
            'received_on' => $today,
            'sender_name' => 'Demo customer',
            'screenshot' => UploadedFile::fake()->image('payment.png'),
        ])->assertOk()->assertJsonPath('order.status', 'completed');
        $this->assertDatabaseHas('transactions', [
            'order_id' => $order['id'],
            'type' => 'income',
            'transaction_date' => $today,
        ]);
        $this->assertSame(800.0, (float) $stock->fresh()->current_quantity);
        $capture = WalletNotificationCapture::where('used_order_id', $order['id'])->sole();
        $this->assertSame('staff_screenshot', $capture->capture_method);
        $this->assertSame('maya', $capture->source);
        $this->assertSame('Demo customer', $capture->counterparty_name);
        $this->assertSame('MAYA-UNIQUE-12345', $capture->reference);
        Storage::disk('local')->assertExists($capture->screenshot_path);
        $this->assertSame(1, Transaction::count());

        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $this->actingAs($owner)->get(route('transactions.index', ['view' => 'raw']))
            ->assertOk()->assertSee('View payment screenshot');
        $this->get(route('transactions.wallet-screenshot', $capture))->assertOk();

        $otherBusiness = Business::create(['name' => 'Other Cafe', 'status' => 'active', 'active' => true]);
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id]);
        $this->actingAs($otherOwner)->get(route('transactions.wallet-screenshot', $capture))->assertNotFound();
    }

    public function test_manual_screenshot_joins_an_existing_wallet_notification_in_raw_transactions(): void
    {
        Storage::fake('local');
        $business = Business::create(['name' => 'Tea Shop', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Tea', 'category' => 'Drinks',
            'price' => 85, 'active' => true, 'is_available' => true,
        ]);
        Sanctum::actingAs($staff);
        $order = $this->postJson('/api/v1/orders', [
            'payment_method' => 'gcash', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('order');
        $capture = WalletNotificationCapture::create([
            'client_id' => 'c1bef5cb-7dd1-427b-bd7b-7d40789b1966',
            'business_id' => $business->id, 'user_id' => $staff->id,
            'source' => 'gcash', 'direction' => 'received', 'amount' => 85,
            'currency' => 'PHP', 'reference' => 'GCASH-REF-12345',
            'message' => 'You received PHP 85.00. Ref No: GCASH-REF-12345',
            'captured_at' => now(),
        ]);

        $this->post('/api/v1/orders/'.$order['id'].'/payments/confirm', [
            'amount' => 85, 'currency' => 'PHP', 'reference' => 'GCASH-REF-12345',
            'received_on' => now(config('business.timezone'))->toDateString(),
            'screenshot' => UploadedFile::fake()->image('gcash.png'),
        ])->assertOk();

        $this->assertDatabaseCount('wallet_notification_captures', 1);
        $this->assertSame('notification_with_screenshot', $capture->fresh()->capture_method);
        Storage::disk('local')->assertExists($capture->fresh()->screenshot_path);
        $this->assertSame(1, Transaction::count());
    }
}
