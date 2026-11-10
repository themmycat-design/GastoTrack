<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductEditRequest;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductEditRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_staff_edit_waits_for_owner_approval_and_converts_recipe_units(): void
    {
        $business = Business::create(['name' => 'Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $product = Product::create(['business_id' => $business->id, 'name' => 'Milk Tea', 'category' => 'Drinks', 'price' => 100]);
        $milk = StockItem::create(['business_id' => $business->id, 'name' => 'Milk', 'unit' => 'L',
            'current_quantity' => 10, 'minimum_quantity' => 1, 'unit_cost' => 80]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('test')->plainTextToken];

        $response = $this->putJson('/api/v1/products/'.$product->id, [
            'name' => 'Iced Milk Tea', 'price' => 120, 'size' => 'Large',
            'replace_ingredients' => true,
            'ingredients' => [['stock_id' => $milk->id, 'quantity' => 250, 'unit' => 'ml']],
        ], $headers)->assertStatus(202)->assertJsonPath('approval_pending', true);

        $editRequest = ProductEditRequest::findOrFail($response->json('edit_request_id'));
        $this->assertSame('100.00', $product->fresh()->price);
        $this->assertSame('pending', $editRequest->status);
        $this->assertSame('Large', $editRequest->changes['size']);
        $this->assertSame(0.25, $editRequest->changes['ingredients'][0]['quantity']);
        $this->assertSame(1, Notification::where('user_id', $owner->id)->where('type', 'product_edit_request')->count());
        $this->putJson('/api/v1/products/'.$product->id, ['price' => 130], $headers)->assertStatus(409);

        $this->actingAs($owner)->get(route('products.index'))
            ->assertOk()->assertSee('Staff product edit requests')->assertSee('Iced Milk Tea');
        $this->actingAs($owner)->post(route('products.edit-requests.approve', $editRequest))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Iced Milk Tea', 'price' => 120, 'size' => 'Large']);
        $this->assertDatabaseHas('product_ingredients', [
            'product_id' => $product->id, 'stock_item_id' => $milk->id, 'quantity' => 0.25,
        ]);
        $this->assertSame('approved', $editRequest->fresh()->status);
        $this->assertNotNull(Notification::where('user_id', $owner->id)->firstOrFail()->read_at);
        $this->actingAs($owner)->get(route('products.index'))
            ->assertOk()->assertSee('Iced Milk Tea')->assertSee('120.00');
        $this->actingAs($staff)->getJson('/api/v1/products', $headers)
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Iced Milk Tea')
            ->assertJsonPath('products.0.size', 'Large')
            ->assertJsonPath('products.0.price', '120.00')
            ->assertJsonPath('products.0.approval_pending', false)
            ->assertJsonPath('products.0.product_ingredients.0.quantity', '0.2500');
        $this->getJson('/api/v1/products?category=Drinks&size=Medium', $headers)
            ->assertOk()->assertJsonCount(0, 'products');
        $this->getJson('/api/v1/products?category=Drinks&size=Large', $headers)
            ->assertOk()->assertJsonCount(1, 'products');
    }

    public function test_only_the_business_owner_can_reject_a_pending_photo_edit(): void
    {
        Storage::fake('public');
        $business = Business::create(['name' => 'Cafe', 'status' => 'active', 'active' => true]);
        $otherBusiness = Business::create(['name' => 'Other', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $product = Product::create(['business_id' => $business->id, 'name' => 'Tea', 'category' => 'Drinks', 'price' => 80]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('test')->plainTextToken];

        $response = $this->post('/api/v1/products/'.$product->id, [
            '_method' => 'PUT', 'price' => 90,
            'image' => UploadedFile::fake()->image('tea.png'),
        ], $headers)->assertStatus(202);
        $editRequest = ProductEditRequest::findOrFail($response->json('edit_request_id'));
        Storage::disk('public')->assertExists($editRequest->image_path);

        $this->actingAs($otherOwner)->post(route('products.edit-requests.reject', $editRequest))->assertNotFound();
        $this->assertSame('pending', $editRequest->fresh()->status);
        $this->actingAs($owner)->post(route('products.edit-requests.reject', $editRequest))
            ->assertRedirect(route('products.index'));
        $this->assertSame('rejected', $editRequest->fresh()->status);
        $this->assertSame('80.00', $product->fresh()->price);
        Storage::disk('public')->assertMissing($editRequest->image_path);
    }

    public function test_owner_cannot_approve_an_edit_based_on_outdated_product_details(): void
    {
        $business = Business::create(['name' => 'Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $product = Product::create(['business_id' => $business->id, 'name' => 'Tea', 'category' => 'Drinks', 'price' => 80]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('test')->plainTextToken];

        $editId = $this->putJson('/api/v1/products/'.$product->id, ['price' => 90], $headers)
            ->assertStatus(202)->json('edit_request_id');
        $product->update(['price' => 95]);

        $this->actingAs($owner)->post(route('products.edit-requests.approve', $editId))
            ->assertSessionHasErrors('request');
        $this->assertSame('95.00', $product->fresh()->price);
        $this->assertDatabaseHas('product_edit_requests', ['id' => $editId, 'status' => 'pending']);
    }
}
