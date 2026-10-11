<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCategoryOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_manage_product_categories_without_changing_financial_categories(): void
    {
        $business = Business::create(['name' => 'Bake House', 'status' => 'active', 'active' => true]);
        Sanctum::actingAs(User::factory()->staff()->create(['business_id' => $business->id]));

        $this->getJson('/api/v1/transaction-options')->assertOk()
            ->assertJsonFragment(['transaction_type' => 'product', 'name' => 'Coffee']);

        $option = $this->postJson('/api/v1/transaction-options', [
            'kind' => 'category', 'transaction_type' => 'product', 'name' => 'Bakery',
        ])->assertCreated()->json('option');
        $this->putJson('/api/v1/transaction-options/'.$option['id'], [
            'name' => 'Bakery', 'transaction_type' => 'expense',
        ])->assertUnprocessable();
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Cinnamon roll',
            'category' => 'Bakery', 'price' => 75,
        ]);

        $this->putJson('/api/v1/transaction-options/'.$option['id'], [
            'name' => 'Pastries', 'transaction_type' => 'product',
        ])->assertOk()->assertJsonPath('option.name', 'Pastries');
        $this->assertSame('Pastries', $product->fresh()->category);
        $this->deleteJson('/api/v1/transaction-options/'.$option['id'])->assertUnprocessable();

        $product->update(['category' => 'Coffee']);
        $this->deleteJson('/api/v1/transaction-options/'.$option['id'])->assertOk();
        $this->getJson('/api/v1/transaction-options')->assertOk()
            ->assertJsonMissing(['transaction_type' => 'product', 'name' => 'Pastries'])
            ->assertJsonFragment(['transaction_type' => 'income', 'name' => 'Sales']);

        $coffee = collect($this->getJson('/api/v1/transaction-options')->json('options'))
            ->first(fn ($item) => $item['transaction_type'] === 'product' && $item['name'] === 'Coffee');
        $this->putJson('/api/v1/transaction-options/'.$coffee['id'], [
            'name' => 'Hot Coffee', 'transaction_type' => 'product',
        ])->assertOk();
        $this->assertSame('Hot Coffee', $product->fresh()->category);
        $this->getJson('/api/v1/transaction-options')->assertOk()
            ->assertJsonMissing(['transaction_type' => 'product', 'name' => 'Coffee'])
            ->assertJsonFragment(['transaction_type' => 'product', 'name' => 'Hot Coffee']);
    }

    public function test_existing_product_categories_are_imported_and_isolated_by_business(): void
    {
        $first = Business::create(['name' => 'First Shop', 'status' => 'active', 'active' => true]);
        $second = Business::create(['name' => 'Second Shop', 'status' => 'active', 'active' => true]);
        Product::create(['business_id' => $first->id, 'name' => 'Seasonal cake', 'category' => 'Seasonal', 'price' => 150]);
        $firstStaff = User::factory()->staff()->create(['business_id' => $first->id]);
        $secondStaff = User::factory()->staff()->create(['business_id' => $second->id]);

        Sanctum::actingAs($firstStaff);
        $this->getJson('/api/v1/transaction-options')->assertOk()
            ->assertJsonFragment(['transaction_type' => 'product', 'name' => 'Seasonal']);
        Sanctum::actingAs($secondStaff);
        $this->getJson('/api/v1/transaction-options')->assertOk()
            ->assertJsonMissing(['transaction_type' => 'product', 'name' => 'Seasonal']);
    }
}
