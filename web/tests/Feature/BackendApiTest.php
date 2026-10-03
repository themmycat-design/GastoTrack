<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackendApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_route_names_never_resolve_to_bearer_token_api_routes(): void
    {
        $this->assertSame('/stock', parse_url(route('stock.index'), PHP_URL_PATH));
        $this->assertSame('/transactions', parse_url(route('transactions.index'), PHP_URL_PATH));
        $this->assertSame('/staff', parse_url(route('staff.index'), PHP_URL_PATH));
    }

    public function test_owner_configures_business_and_staff_processes_orders(): void
    {
        Storage::fake('public');
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Adrian Cruz', 'email' => 'adrian@brewhaven.example',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'business_name' => 'Brew Haven Cafe',
        ])->assertCreated();

        // Business operations remain locked until a platform administrator approves it.
        Business::findOrFail($register->json('business.id'))->update([
            'status' => 'active',
            'active' => true,
        ]);

        $ownerHeaders = ['Authorization' => 'Bearer '.$register->json('token')];

        $stock = $this->postJson('/api/v1/stock', [
            'name' => 'Milk', 'unit' => 'ml', 'current_quantity' => 1000,
            'minimum_quantity' => 100, 'unit_cost' => 0.08,
        ], $ownerHeaders)->assertCreated()->json('item');

        $product = $this->postJson('/api/v1/products', [
            'name' => 'Latte', 'category' => 'Coffee', 'price' => 120,
            'ingredients' => [['stock_id' => $stock['id'], 'quantity' => 200]],
        ], $ownerHeaders)->assertCreated()->json('product');

        $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product['id'], 'quantity' => 1]],
            'payment_method' => 'cash',
        ], $ownerHeaders)->assertForbidden();

        $this->postJson('/api/v1/staff', [
            'name' => 'Lea Santos', 'email' => 'lea@brewhaven.example',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ], $ownerHeaders)->assertCreated();

        $staffLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'lea@brewhaven.example', 'password' => 'password123',
        ])->assertOk()->assertJsonPath('user.role', 'staff');
        $staffHeaders = ['Authorization' => 'Bearer '.$staffLogin->json('token')];

        // Sanctum's request guard is cached within a feature-test process.
        // Clear the previously resolved owner before switching Bearer tokens.
        $this->app['auth']->forgetGuards();

        // Staff maintain the ordering menu, including recipes and availability.
        $staffProduct = $this->withHeaders($staffHeaders)->post('/api/v1/products', [
            'name' => 'Staff Special', 'category' => 'Coffee', 'price' => 95,
            'is_available' => true,
            'ingredients' => [['stock_id' => $stock['id'], 'quantity' => 50]],
            'image' => UploadedFile::fake()->image('staff-special.jpg', 800, 800),
        ], $staffHeaders)->assertCreated()->json('product');
        Storage::disk('public')->assertExists($staffProduct['image']);
        $this->get('/api/v1/product-images/'.$staffProduct['id'])->assertOk();
        $this->putJson('/api/v1/products/'.$staffProduct['id'], [
            'price' => 105, 'is_available' => false,
            'ingredients' => [['stock_id' => $stock['id'], 'quantity' => 60]],
        ], $staffHeaders)->assertOk()
            ->assertJsonPath('product.price', '105.00')
            ->assertJsonPath('product.is_available', false);
        $this->getJson('/api/v1/products', $staffHeaders)->assertOk()
            ->assertJsonFragment(['name' => 'Staff Special', 'is_available' => false]);
        $this->deleteJson('/api/v1/products/'.$staffProduct['id'], [], $staffHeaders)->assertOk();
        $this->assertSoftDeleted('products', ['id' => $staffProduct['id']]);
        Storage::disk('public')->assertMissing($staffProduct['image']);

        $order = $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product['id'], 'quantity' => 2]],
            'payment_method' => 'cash',
        ], $staffHeaders)->assertCreated()->assertJsonPath('order.status', 'completed')->json('order');

        $this->assertDatabaseHas('stock_items', ['id' => $stock['id'], 'current_quantity' => 600]);
        $this->assertDatabaseHas('transactions', ['business_id' => $register->json('business.id'), 'amount' => 240, 'type' => 'income']);

        // Staff can count and adjust stock, but item setup remains an owner responsibility.
        $this->postJson('/api/v1/stock', [
            'name' => 'Unauthorized Item', 'unit' => 'pcs', 'current_quantity' => 1,
            'minimum_quantity' => 1, 'unit_cost' => 1,
        ], $staffHeaders)->assertForbidden();
        $this->putJson('/api/v1/stock/'.$stock['id'], ['name' => 'Renamed by Staff'], $staffHeaders)->assertForbidden();
        $this->postJson('/api/v1/stock/'.$stock['id'].'/adjust', [
            'type' => 'add', 'quantity' => 50, 'reason' => 'Supplier delivery',
        ], $staffHeaders)->assertOk()->assertJsonPath('item.current_quantity', '650.00');
        $this->postJson('/api/v1/stock/'.$stock['id'].'/adjust', [
            'type' => 'deduct', 'quantity' => 1000, 'reason' => 'Invalid count',
        ], $staffHeaders)->assertUnprocessable();
        $this->assertDatabaseHas('stock_items', ['id' => $stock['id'], 'current_quantity' => 650]);

        // OCR creates a normal staff-owned transaction that the same staff member may correct.
        $ocrTransaction = $this->postJson('/api/v1/transactions', [
            'amount' => 85, 'type' => 'expense', 'source' => 'Cash',
            'category' => 'Supplies', 'date' => now()->toDateString(),
            'notes' => 'Scanned receipt', 'entry_method' => 'ocr',
            'metadata' => ['ocr_confidence' => 91, 'ocr_provider' => 'device_ocr'],
        ], $staffHeaders)->assertCreated()->assertJsonPath('transaction.entry_method', 'ocr')->json('transaction');
        $this->putJson('/api/v1/transactions/'.$ocrTransaction['id'], [
            'amount' => 90,
        ], $staffHeaders)->assertOk()->assertJsonPath('transaction.amount', '90.00');

        // The income generated from a completed order is an immutable accounting record.
        $orderTransaction = Transaction::where('business_id', $register->json('business.id'))
            ->where('entry_method', 'order_system')
            ->firstOrFail();
        $this->putJson('/api/v1/transactions/'.$orderTransaction->id, [
            'amount' => 1,
        ], $staffHeaders)->assertStatus(409);
        $this->deleteJson('/api/v1/transactions/'.$orderTransaction->id, [], $staffHeaders)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->putJson('/api/v1/orders/'.$order['id'], ['status' => 'completed'], $ownerHeaders)->assertForbidden();
        $this->getJson('/api/v1/orders', $ownerHeaders)->assertOk()->assertJsonPath('orders.0.status', 'completed');
        $this->putJson('/api/v1/transactions/'.$orderTransaction->id, ['amount' => 1], $ownerHeaders)->assertStatus(409);
        $this->deleteJson('/api/v1/transactions/'.$orderTransaction->id, [], $ownerHeaders)->assertStatus(409);
    }
}
