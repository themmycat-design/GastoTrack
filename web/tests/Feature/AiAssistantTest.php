<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_chat_with_business_scoped_ai_context(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'test-model']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'You have no low-stock items today.']]],
                ]],
            ]),
        ]);

        $business = Business::create(['name' => 'Scoped Cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $milk = StockItem::create([
            'business_id' => $business->id, 'name' => 'Whole Milk', 'unit' => 'ml',
            'current_quantity' => 0, 'minimum_quantity' => 500, 'unit_cost' => 0.08, 'active' => true,
        ]);
        StockItem::create([
            'business_id' => $business->id, 'name' => 'Oat Milk', 'unit' => 'ml',
            'current_quantity' => 3000, 'minimum_quantity' => 500, 'unit_cost' => 0.12, 'active' => true,
        ]);
        $latte = Product::create([
            'business_id' => $business->id, 'name' => 'Cafe Latte', 'category' => 'Coffee',
            'price' => 120, 'active' => true, 'is_available' => true,
        ]);
        ProductIngredient::create([
            'product_id' => $latte->id, 'stock_item_id' => $milk->id, 'quantity' => 200,
        ]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/ai/chat', [
            'messages' => [['role' => 'user', 'text' => 'What is low in stock?']],
        ])->assertOk()->assertJsonPath('reply', 'You have no low-stock items today.');

        Http::assertSent(function (Request $request) {
            $systemPrompt = data_get($request->data(), 'systemInstruction.parts.0.text', '');

            return str_contains($request->url(), 'models/test-model:generateContent')
                && str_contains($systemPrompt, 'Scoped Cafe')
                && str_contains($systemPrompt, 'Whole Milk')
                && str_contains($systemPrompt, 'Oat Milk')
                && str_contains($systemPrompt, 'Cafe Latte')
                && str_contains($systemPrompt, 'possible_alternatives')
                && str_contains($systemPrompt, 'staff must confirm');
        });
    }

    public function test_owner_cannot_use_staff_ai_assistant(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake();
        $business = Business::create(['name' => 'Owner Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/ai/chat', [
            'messages' => [['role' => 'user', 'text' => 'Hello']],
        ])->assertForbidden();

        Http::assertNothingSent();
    }
}
