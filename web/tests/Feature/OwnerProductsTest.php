<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_create_update_filter_and_soft_delete_own_products(): void
    {
        $business = $this->business('Menu Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->post(route('products.store'), [
                'name' => 'Iced Matcha',
                'description' => 'Green tea latte',
                'category' => 'Tea',
                'size' => 'Small',
                'price' => 145,
                'cost' => 55,
                'emoji' => '🍵',
                'is_available' => '1',
            ])
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertTrue($product->is_available);
        $this->assertSame('Small', $product->size);

        $this->actingAs($owner)
            ->put(route('products.update', $product), [
                'name' => 'Iced Matcha',
                'description' => 'Green tea latte',
                'category' => 'Tea',
                'size' => 'Medium',
                'price' => 150,
                'cost' => 60,
                'emoji' => '🍵',
                'is_available' => '0',
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 150,
            'size' => 'Medium',
            'is_available' => 0,
        ]);

        $this->actingAs($owner)
            ->get(route('products.index', ['available' => '0']))
            ->assertOk()
            ->assertSee('Iced Matcha');

        $this->actingAs($owner)->get(route('products.index', ['category' => 'Tea', 'size' => 'Medium']))
            ->assertOk()->assertSee('Iced Matcha');
        $this->actingAs($owner)->get(route('products.index', ['category' => 'Tea', 'size' => 'Small']))
            ->assertOk()->assertDontSee('Iced Matcha');

        $this->actingAs($owner)
            ->deleteJson(route('products.destroy', $product))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_owner_cannot_list_toggle_or_modify_another_business_product(): void
    {
        $owner = User::factory()->owner()->create(['business_id' => $this->business('Owner Shop')->id]);
        $otherBusiness = $this->business('Different Shop');
        $product = Product::create([
            'business_id' => $otherBusiness->id,
            'name' => 'Private item',
            'category' => 'Coffee',
            'price' => 100,
            'active' => true,
            'is_available' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Private item');

        $this->actingAs($owner)->postJson(route('products.toggle', $product))->assertNotFound();
        $this->actingAs($owner)->put(route('products.update', $product), [
            'name' => 'Changed item', 'category' => 'Coffee', 'price' => 200,
        ])->assertNotFound();
        $this->actingAs($owner)->deleteJson(route('products.destroy', $product))->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Private item']);
    }

    public function test_product_filters_validate_inputs_and_availability_toggle_works(): void
    {
        $business = $this->business('Filter Shop');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Hot Chocolate',
            'category' => 'Drinks',
            'price' => 95,
            'active' => true,
            'is_available' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('products.index', ['available' => 'sometimes']))
            ->assertSessionHasErrors('available');

        $this->actingAs($owner)
            ->postJson(route('products.toggle', $product))
            ->assertOk()
            ->assertJsonPath('is_available', false);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_available' => 0]);
    }

    public function test_owner_can_upload_product_photo_and_manage_recipe_ingredients(): void
    {
        Storage::fake('public');
        $business = $this->business('Recipe Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $stock = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Fresh milk',
            'unit' => 'ml',
            'current_quantity' => 5000,
            'minimum_quantity' => 500,
            'unit_cost' => 0.08,
            'active' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('products.store'), [
                'name' => 'Milk Tea',
                'category' => 'Beverages',
                'price' => 120,
                'cost' => 40,
                'is_available' => '1',
                'replace_ingredients' => '1',
                'ingredients' => [0 => ['stock_id' => $stock->id, 'quantity' => '250']],
                'image' => UploadedFile::fake()->image('milk-tea.png'),
            ])
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertNotEmpty($product->image);
        Storage::disk('public')->assertExists($product->image);
        $this->assertDatabaseHas('product_ingredients', [
            'product_id' => $product->id,
            'stock_item_id' => $stock->id,
            'quantity' => 250,
        ]);

        $this->actingAs($owner)
            ->put(route('products.update', $product), [
                'name' => 'Milk Tea',
                'category' => 'Beverages',
                'price' => 120,
                'cost' => 40,
                'is_available' => '1',
                'replace_ingredients' => '1',
                'ingredients' => [0 => ['stock_id' => $stock->id, 'quantity' => '0.3', 'unit' => 'L']],
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('product_ingredients', [
            'product_id' => $product->id,
            'stock_item_id' => $stock->id,
            'quantity' => 300,
        ]);
        Storage::disk('public')->assertExists($product->fresh()->image);
    }

    public function test_owner_cannot_attach_another_business_stock_item_to_a_recipe(): void
    {
        $owner = User::factory()->owner()->create(['business_id' => $this->business('Recipe Owner')->id]);
        $foreignStock = StockItem::create([
            'business_id' => $this->business('Other Recipe Shop')->id,
            'name' => 'Private ingredient',
            'unit' => 'g',
            'active' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('products.store'), [
                'name' => 'Test drink',
                'category' => 'Beverages',
                'price' => 100,
                'replace_ingredients' => '1',
                'ingredients' => [0 => ['stock_id' => $foreignStock->id, 'quantity' => '1']],
            ])
            ->assertSessionHasErrors('ingredients.0.stock_id');
    }

    public function test_owner_edits_volume_recipe_in_ml_while_stock_remains_in_liters(): void
    {
        $business = $this->business('Unit Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $water = StockItem::create([
            'business_id' => $business->id, 'name' => 'Water', 'unit' => 'L',
            'current_quantity' => 1, 'minimum_quantity' => 0, 'unit_cost' => 10, 'active' => true,
        ]);
        $product = [
            'name' => 'Mountain Dew', 'category' => 'Drinks', 'price' => 85,
            'replace_ingredients' => '1',
            'ingredients' => [['stock_id' => $water->id, 'quantity' => 0.5, 'unit' => 'L']],
        ];

        $this->actingAs($owner)->post(route('products.store'), $product)
            ->assertRedirect(route('products.index'));
        $savedProduct = Product::where('business_id', $business->id)->firstOrFail();
        $this->assertDatabaseHas('product_ingredients', [
            'product_id' => $savedProduct->id, 'stock_item_id' => $water->id, 'quantity' => 0.5,
        ]);

        $product['ingredients'][0]['quantity'] = 250;
        $product['ingredients'][0]['unit'] = 'ml';
        $this->actingAs($owner)->put(route('products.update', $savedProduct), $product)
            ->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('product_ingredients', [
            'product_id' => $savedProduct->id, 'stock_item_id' => $water->id, 'quantity' => 0.25,
        ]);

        $this->actingAs($owner)->put(route('stock.update', $water), [
            'name' => 'Water', 'unit' => 'ml', 'minimum_quantity' => 0,
            'unit_cost' => 10, 'active' => 1,
        ])->assertSessionHasErrors('unit');
        $this->assertSame('L', $water->fresh()->unit);
        $this->actingAs($owner)->put(route('stock.update', $water), [
            'name' => 'Water', 'unit' => 'L', 'minimum_quantity' => 0,
            'unit_cost' => 10, 'active' => 1,
        ])->assertRedirect(route('stock.index'));

        $product['ingredients'][0]['unit'] = 'g';
        $this->actingAs($owner)->put(route('products.update', $savedProduct), $product)
            ->assertSessionHasErrors('ingredients.0.unit');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_owner_can_create_new_stock_items_in_metric_volume_units(): void
    {
        $business = $this->business('Volume Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);

        $this->actingAs($owner)->post(route('stock.store'), [
            'name' => 'Milk', 'unit' => 'ml', 'current_quantity' => 2000,
            'minimum_quantity' => 0, 'unit_cost' => 0.1,
        ])->assertRedirect(route('stock.index'));
        $this->actingAs($owner)->post(route('stock.store'), [
            'name' => 'Water', 'unit' => 'L', 'current_quantity' => 10,
            'minimum_quantity' => 0, 'unit_cost' => 50,
        ])->assertRedirect(route('stock.index'));
        $this->assertDatabaseHas('stock_items', ['business_id' => $business->id, 'name' => 'Milk', 'unit' => 'ml']);
        $this->assertDatabaseHas('stock_items', ['business_id' => $business->id, 'name' => 'Water', 'unit' => 'L']);
    }

    public function test_owner_adds_stock_in_its_saved_unit_without_changing_that_unit(): void
    {
        $business = $this->business('Stock Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);

        $this->actingAs($owner)->post(route('stock.store'), [
            'name' => 'Flour', 'unit' => 'kg', 'current_quantity' => 10,
            'minimum_quantity' => 2, 'unit_cost' => 50,
        ])->assertRedirect(route('stock.index'));

        $stock = StockItem::where('business_id', $business->id)->firstOrFail();
        $this->actingAs($owner)->postJson(route('stock.adjust', $stock), [
            'type' => 'add', 'quantity' => 5,
        ])->assertOk()->assertJsonPath('new_quantity', '15.0000');
        $this->assertSame('kg', $stock->fresh()->unit);

        $this->actingAs($owner)->put(route('stock.update', $stock), [
            'name' => 'Flour', 'unit' => 'g', 'minimum_quantity' => 2,
            'unit_cost' => 50, 'active' => 1,
        ])->assertSessionHasErrors('unit');
        $this->assertSame('kg', $stock->fresh()->unit);
    }

    public function test_owner_can_enter_adjustments_in_compatible_units(): void
    {
        $business = $this->business('Unit Conversion Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $flour = StockItem::create([
            'business_id' => $business->id, 'name' => 'Flour', 'unit' => 'kg',
            'current_quantity' => 2, 'minimum_quantity' => 0, 'unit_cost' => 50,
        ]);
        $milk = StockItem::create([
            'business_id' => $business->id, 'name' => 'Milk', 'unit' => 'ml',
            'current_quantity' => 2000, 'minimum_quantity' => 0, 'unit_cost' => 0.1,
        ]);

        $this->actingAs($owner)->postJson(route('stock.adjust', $flour), [
            'type' => 'add', 'quantity' => 500, 'unit' => 'g',
        ])->assertOk()->assertJsonPath('new_quantity', '2.5000');
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $flour->id, 'quantity' => 0.5, 'type' => 'in',
        ]);
        $this->assertSame('kg', $flour->fresh()->unit);

        $this->actingAs($owner)->postJson(route('stock.adjust', $milk), [
            'type' => 'subtract', 'quantity' => 0.5, 'unit' => 'L',
        ])->assertOk()->assertJsonPath('new_quantity', '1500.0000');
        $this->assertSame('ml', $milk->fresh()->unit);

        $this->actingAs($owner)->postJson(route('stock.adjust', $flour), [
            'type' => 'add', 'quantity' => 1, 'unit' => 'ml',
        ])->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->actingAs($owner)->postJson(route('stock.adjust', $flour), [
            'type' => 'add', 'quantity' => 0.0001, 'unit' => 'g',
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame('2.5000', $flour->fresh()->current_quantity);
    }

    private function business(string $name): Business
    {
        return Business::create(['name' => $name, 'status' => 'active', 'active' => true]);
    }
}
