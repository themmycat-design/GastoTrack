<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Transaction;
use App\Models\User;
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

    public function test_staff_summary_counts_business_income_records_for_the_selected_day(): void
    {
        $business = Business::create(['name' => 'Daily Income Cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $colleague = User::factory()->staff()->create(['business_id' => $business->id]);
        $today = now(config('business.timezone'))->toDateString();
        $yesterday = now(config('business.timezone'))->subDay()->toDateString();

        $record = static function (User $user, string $type, string $date, float $amount) use ($business): void {
            Transaction::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'source' => 'Cash',
                'category' => $type === 'income' ? 'Sales' : 'Supplies',
                'transaction_date' => $date,
                'description' => 'Test record',
                'entry_method' => 'manual',
            ]);
        };

        $record($staff, 'income', $today, 20);
        $record($staff, 'income', $today, 15);
        $record($staff, 'expense', $today, 5);
        $record($staff, 'income', $yesterday, 40);
        $record($colleague, 'income', $today, 25);

        $headers = ['Authorization' => 'Bearer '.$staff->createToken('daily-income-test')->plainTextToken];
        $response = $this->getJson('/api/v1/transactions/summary?from='.$today.'&to='.$today, $headers)
            ->assertOk()
            ->assertJsonPath('income_count', 3);

        $this->assertEquals(60, (float) $response->json('income'));
    }

    public function test_staff_can_update_personal_details_and_access_their_private_profile_photo(): void
    {
        Storage::fake('local');
        $business = Business::create(['name' => 'Profile Photo Cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('profile-photo-test')->plainTextToken];

        $response = $this->withHeaders($headers)->post('/api/v1/user/profile', [
            'name' => 'Updated Staff Name',
            'phone' => '09123456789',
            'profile_photo' => UploadedFile::fake()->image('staff-avatar.jpg'),
        ])->assertOk()
            ->assertJsonPath('user.name', 'Updated Staff Name')
            ->assertJsonPath('user.phone', '09123456789')
            ->assertJsonPath('user.has_profile_photo', true);

        $photoPath = User::findOrFail($staff->id)->profile_photo_path;
        $this->assertNotEmpty($photoPath);
        Storage::disk('local')->assertExists($photoPath);
        $this->get('/api/v1/user/profile-photo', $headers)->assertOk();
        $this->getJson('/api/v1/user', $headers)
            ->assertOk()
            ->assertJsonPath('user.has_profile_photo', true);
    }

    public function test_staff_can_record_stock_waste_with_a_cost_snapshot(): void
    {
        $business = Business::create(['name' => 'Waste Tracking Cafe', 'status' => 'active', 'active' => true]);
        $staff = \App\Models\User::factory()->staff()->create(['business_id' => $business->id]);
        $stock = \App\Models\StockItem::create([
            'business_id' => $business->id,
            'name' => 'Fresh milk',
            'unit' => 'ml',
            'current_quantity' => 1000,
            'minimum_quantity' => 100,
            'unit_cost' => 0.25,
            'active' => true,
        ]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('waste-test')->plainTextToken];

        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', [
            'type' => 'deduct',
            'quantity' => 40,
            'reason' => 'Expired milk',
            'movement_category' => 'waste',
        ], $headers)->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $stock->id,
            'type' => 'out',
            'movement_category' => 'waste',
            'quantity' => 40,
            'unit_cost_at_movement' => 0.25,
        ]);

        $this->postJson('/api/v1/stock/'.$stock->id.'/adjust', [
            'type' => 'add',
            'quantity' => 40,
            'movement_category' => 'waste',
        ], $headers)->assertUnprocessable();
    }

    public function test_owner_configures_business_and_staff_processes_orders(): void
    {
        Storage::fake('public');
        $business = Business::create([
            'name' => 'Brew Haven Cafe',
            'status' => 'pending',
            'active' => false,
        ]);
        $owner = \App\Models\User::factory()->owner()->create([
            'name' => 'Adrian Cruz',
            'email' => 'adrian@brewhaven.example',
            'password' => 'password123',
            'business_id' => $business->id,
        ]);
        $business->update(['owner_id' => $owner->id]);

        // Business operations remain locked until a platform administrator approves it.
        $business->update([
            'status' => 'active',
            'active' => true,
        ]);

        $ownerHeaders = ['Authorization' => 'Bearer '.$owner->createToken('backend-api-test')->plainTextToken];

        $volumeStock = $this->postJson('/api/v1/stock', [
            'name' => 'Milk', 'unit' => 'ml', 'current_quantity' => 1000,
            'minimum_quantity' => 100, 'unit_cost' => 0.08,
        ], $ownerHeaders)->assertCreated()->json('item');

        $this->putJson('/api/v1/stock/'.$volumeStock['id'], ['unit' => 'L'], $ownerHeaders)
            ->assertUnprocessable()->assertJsonValidationErrors('unit');

        $stock = $this->postJson('/api/v1/stock', [
            'name' => 'Milk powder', 'unit' => 'g', 'current_quantity' => 1000,
            'minimum_quantity' => 100, 'unit_cost' => 0.08,
        ], $ownerHeaders)->assertCreated()->json('item');

        $this->putJson('/api/v1/stock/'.$stock['id'], ['unit' => 'kg'], $ownerHeaders)
            ->assertUnprocessable()->assertJsonValidationErrors('unit');

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
            'password' => 'GastoTrack9!', 'password_confirmation' => 'GastoTrack9!',
        ], $ownerHeaders)->assertCreated();

        $staffLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'lea@brewhaven.example', 'password' => 'GastoTrack9!',
        ])->assertOk()->assertJsonPath('user.role', 'staff');
        $staffHeaders = ['Authorization' => 'Bearer '.$staffLogin->json('token')];

        // Sanctum's request guard is cached within a feature-test process.
        // Clear the previously resolved owner before switching Bearer tokens.
        $this->app['auth']->forgetGuards();

        // Staff can manage reusable transaction categories and payment sources.
        $this->postJson('/api/v1/transaction-options', [
            'kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Equipment Repair',
        ], $staffHeaders)->assertCreated()->assertJsonPath('option.name', 'Equipment Repair');
        $customSource = $this->postJson('/api/v1/transaction-options', [
            'kind' => 'source', 'name' => 'GrabPay',
        ], $staffHeaders)->assertCreated()->assertJsonPath('option.transaction_type', 'all')->json('option');
        $this->putJson('/api/v1/transaction-options/'.$customSource['id'], [
            'name' => 'GrabPay Wallet',
        ], $staffHeaders)->assertOk()->assertJsonPath('option.name', 'GrabPay Wallet');
        $this->getJson('/api/v1/transaction-options', $staffHeaders)->assertOk()
            ->assertJsonFragment(['name' => 'Equipment Repair'])
            ->assertJsonFragment(['name' => 'GrabPay Wallet']);
        $this->deleteJson('/api/v1/transaction-options/'.$customSource['id'], [], $staffHeaders)->assertOk();

        // Staff maintain the ordering menu, including recipes and availability.
        $staffProduct = $this->withHeaders($staffHeaders)->post('/api/v1/products', [
            'name' => 'Staff Special', 'category' => 'Coffee', 'price' => 95,
            'is_available' => true,
            'ingredients' => [['stock_id' => $stock['id'], 'quantity' => 50]],
            'image' => UploadedFile::fake()->image('staff-special.jpg', 800, 800),
        ], $staffHeaders)->assertCreated()->json('product');
        Storage::disk('public')->assertExists($staffProduct['image']);
        $this->get('/api/v1/product-images/'.$staffProduct['id'])->assertOk();
        $editRequestId = $this->putJson('/api/v1/products/'.$staffProduct['id'], [
            'price' => 105, 'is_available' => false,
            'ingredients' => [['stock_id' => $stock['id'], 'quantity' => 60]],
        ], $staffHeaders)->assertStatus(202)
            ->assertJsonPath('approval_pending', true)
            ->assertJsonPath('product.price', '95.00')
            ->json('edit_request_id');
        $this->assertDatabaseHas('product_edit_requests', ['id' => $editRequestId, 'status' => 'pending']);
        $this->actingAs($owner)->post(route('products.edit-requests.approve', $editRequestId))
            ->assertRedirect(route('products.index'));
        $this->actingAs($staff);
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
        $this->assertDatabaseHas('transactions', ['business_id' => $business->id, 'amount' => 240, 'type' => 'income']);
        $this->getJson('/api/v1/transactions', $staffHeaders)->assertOk()
            ->assertJsonPath('transactions.0.transaction_date', now()->toDateString())
            ->assertJsonPath('transactions.0.description', '2× Latte');

        // Staff can add stock items and adjust counts, while structural edits remain owner-controlled.
        $this->postJson('/api/v1/stock', [
            'name' => 'Paper Cups', 'unit' => 'pcs', 'current_quantity' => 100,
            'minimum_quantity' => 20, 'unit_cost' => 2,
        ], $staffHeaders)->assertCreated()->assertJsonPath('item.name', 'Paper Cups');
        $this->putJson('/api/v1/stock/'.$stock['id'], ['name' => 'Renamed by Staff'], $staffHeaders)->assertForbidden();
        $this->postJson('/api/v1/stock/'.$stock['id'].'/adjust', [
            'type' => 'add', 'quantity' => 50, 'reason' => 'Supplier delivery',
        ], $staffHeaders)->assertOk()->assertJsonPath('item.current_quantity', '650.0000');
        $this->postJson('/api/v1/stock/'.$stock['id'].'/adjust', [
            'type' => 'deduct', 'quantity' => 1000, 'reason' => 'Invalid count',
        ], $staffHeaders)->assertUnprocessable();
        $this->assertDatabaseHas('stock_items', ['id' => $stock['id'], 'current_quantity' => 650]);

        // OCR creates a normal staff-owned transaction that the same staff member may correct.
        $ocrTransaction = $this->postJson('/api/v1/transactions', [
            'amount' => 85, 'type' => 'expense', 'source' => 'Cash',
            'category' => 'Supplies', 'date' => now()->toDateString(),
            'notes' => 'Scanned receipt', 'entry_method' => 'ocr',
            'client_id' => '914a4e80-7ac0-4ccf-a37a-3ee0b83bcf13',
            'metadata' => ['ocr_confidence' => 91, 'ocr_provider' => 'device_ocr'],
        ], $staffHeaders)->assertCreated()->assertJsonPath('transaction.entry_method', 'ocr')->json('transaction');
        $this->postJson('/api/v1/transactions', [
            'amount' => 85, 'type' => 'expense', 'source' => 'Cash',
            'category' => 'Supplies', 'date' => now()->toDateString(),
            'notes' => 'Scanned receipt', 'entry_method' => 'ocr',
            'client_id' => '914a4e80-7ac0-4ccf-a37a-3ee0b83bcf13',
            'metadata' => ['ocr_confidence' => 91, 'ocr_provider' => 'device_ocr'],
        ], $staffHeaders)->assertCreated()->assertJsonPath('transaction.id', $ocrTransaction['id']);
        $this->assertDatabaseCount('transactions', 2);
        $this->putJson('/api/v1/transactions/'.$ocrTransaction['id'], [
            'amount' => 90,
        ], $staffHeaders)->assertOk()->assertJsonPath('transaction.amount', '90.00');

        // The income generated from a completed order is an immutable accounting record.
        $orderTransaction = Transaction::where('business_id', $business->id)
            ->where('entry_method', 'order_system')
            ->firstOrFail();
        $this->putJson('/api/v1/transactions/'.$orderTransaction->id, [
            'amount' => 1,
        ], $staffHeaders)->assertStatus(409);
        $this->deleteJson('/api/v1/transactions/'.$orderTransaction->id, [], $staffHeaders)->assertStatus(409);

        $this->app['auth']->forgetGuards();
        $this->putJson('/api/v1/orders/'.$order['id'], ['status' => 'completed'], $ownerHeaders)->assertForbidden();
        $this->getJson('/api/v1/orders', $ownerHeaders)->assertOk()->assertJsonPath('orders.0.status', 'completed');
        $this->putJson('/api/v1/transactions/'.$orderTransaction->id, ['amount' => 1], $ownerHeaders)->assertStatus(409);
        $this->deleteJson('/api/v1/transactions/'.$orderTransaction->id, [], $ownerHeaders)->assertStatus(409);
    }
}
