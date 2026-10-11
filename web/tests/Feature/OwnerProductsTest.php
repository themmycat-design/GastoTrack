<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'price' => 145,
                'cost' => 55,
                'emoji' => '🍵',
                'is_available' => '1',
            ])
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertTrue($product->is_available);

        $this->actingAs($owner)
            ->put(route('products.update', $product), [
                'name' => 'Iced Matcha',
                'description' => 'Green tea latte',
                'category' => 'Tea',
                'price' => 150,
                'cost' => 60,
                'emoji' => '🍵',
                'is_available' => '0',
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 150,
            'is_available' => 0,
        ]);

        $this->actingAs($owner)
            ->get(route('products.index', ['available' => '0']))
            ->assertOk()
            ->assertSee('Iced Matcha');

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

    private function business(string $name): Business
    {
        return Business::create(['name' => $name, 'status' => 'active', 'active' => true]);
    }
}
