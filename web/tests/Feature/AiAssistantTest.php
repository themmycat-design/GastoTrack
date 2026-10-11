<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\StockItem;
use App\Models\TransactionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_api_activity_and_stock_checks_do_not_consume_chat_allowance(): void
    {
        Cache::flush();
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'test-model']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Sales are available in your transaction records.']]]]],
        ])]);
        $business = Business::create(['name' => 'Rate Limit Cafe', 'status' => 'active', 'active' => true]);
        Sanctum::actingAs(User::factory()->staff()->create(['business_id' => $business->id]));

        try {
            for ($request = 0; $request < 25; $request++) {
                $this->getJson('/api/v1/products')->assertOk();
            }
            for ($request = 0; $request < 10; $request++) {
                $this->getJson('/api/v1/ai/stock-alternatives')
                    ->assertOk()->assertJsonPath('suggestion_status', 'not_needed');
            }
            $this->getJson('/api/v1/ai/stock-alternatives')->assertStatus(429);

            $this->postJson('/api/v1/ai/chat', [
                'messages' => [['role' => 'user', 'text' => 'How are sales today?']],
            ])->assertOk()->assertJsonPath('reply', 'Sales are available in your transaction records.');
            Http::assertSentCount(1);
        } finally {
            Cache::flush();
        }
    }

    public function test_out_of_stock_suggestions_are_scoped_and_only_include_available_ingredients(): void
    {
        Cache::flush();
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'test-model']);
        $business = Business::create(['name' => 'Cafe One', 'status' => 'active', 'active' => true]);
        $other = Business::create(['name' => 'Cafe Two', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $milk = StockItem::create(['business_id' => $business->id, 'name' => 'Whole Milk', 'unit' => 'ml', 'current_quantity' => 0, 'minimum_quantity' => 100, 'unit_cost' => 1, 'active' => true]);
        $oat = StockItem::create(['business_id' => $business->id, 'name' => 'Oat Milk', 'unit' => 'ml', 'current_quantity' => 1000, 'minimum_quantity' => 100, 'unit_cost' => 1, 'active' => true]);
        $unavailable = StockItem::create(['business_id' => $business->id, 'name' => 'Soy Milk', 'unit' => 'ml', 'current_quantity' => 0, 'minimum_quantity' => 100, 'unit_cost' => 1, 'active' => true]);
        $foreign = StockItem::create(['business_id' => $other->id, 'name' => 'Private Milk', 'unit' => 'ml', 'current_quantity' => 1000, 'minimum_quantity' => 100, 'unit_cost' => 1, 'active' => true]);
        $product = Product::create(['business_id' => $business->id, 'name' => 'Cafe Latte', 'category' => 'Coffee', 'price' => 120, 'active' => true, 'is_available' => true]);
        ProductIngredient::create(['product_id' => $product->id, 'stock_item_id' => $milk->id, 'quantity' => 200]);
        Sanctum::actingAs($staff);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['items' => [[
                'stock_id' => $milk->id, 'alternative_ids' => [$oat->id, $unavailable->id, $foreign->id],
            ]]])]]]]],
        ])]);

        $this->getJson('/api/v1/ai/stock-alternatives')
            ->assertOk()
            ->assertJsonPath('suggestion_status', 'ready')
            ->assertJsonPath('items.0.ingredient', 'Whole Milk')
            ->assertJsonPath('items.0.products.0.name', 'Cafe Latte')
            ->assertJsonPath('items.0.alternatives.0.name', 'Oat Milk')
            ->assertJsonCount(1, 'items.0.alternatives')
            ->assertDontSee('Private Milk');
        $this->getJson('/api/v1/ai/stock-alternatives')->assertOk()->assertJsonPath('suggestion_status', 'ready');
        Http::assertSentCount(1);
        Cache::flush();
    }

    public function test_stock_shortages_remain_visible_when_ai_key_is_missing(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();
        $business = Business::create(['name' => 'Cafe One', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $milk = StockItem::create(['business_id' => $business->id, 'name' => 'Whole Milk', 'unit' => 'ml', 'current_quantity' => 0, 'minimum_quantity' => 100, 'unit_cost' => 1, 'active' => true]);
        $product = Product::create(['business_id' => $business->id, 'name' => 'Cafe Latte', 'category' => 'Coffee', 'price' => 120, 'active' => true, 'is_available' => true]);
        ProductIngredient::create(['product_id' => $product->id, 'stock_item_id' => $milk->id, 'quantity' => 200]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/ai/stock-alternatives')
            ->assertOk()
            ->assertJsonPath('suggestion_status', 'unavailable')
            ->assertJsonPath('items.0.ingredient', 'Whole Milk')
            ->assertJsonCount(0, 'items.0.alternatives');
        Http::assertNothingSent();
    }

    public function test_staff_receives_only_a_configured_business_category_suggestion(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'test-model']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Ingredients']]]]],
        ])]);
        $business = Business::create(['name' => 'Cafe One', 'status' => 'active', 'active' => true]);
        $other = Business::create(['name' => 'Cafe Two', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        TransactionOption::create(['business_id' => $business->id, 'kind' => 'source', 'transaction_type' => 'all', 'name' => 'Cash']);
        TransactionOption::create(['business_id' => $business->id, 'kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Ingredients']);
        TransactionOption::create(['business_id' => $other->id, 'kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Private rent']);
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/ai/transaction-category', [
            'type' => 'expense', 'source' => 'Cash', 'description' => 'Bought milk and sugar',
        ])->assertOk()->assertJsonPath('category', 'Ingredients');

        Http::assertSent(function (Request $request) {
            $body = json_encode($request->data());
            return str_contains($body, 'Ingredients') && !str_contains($body, 'Private rent');
        });
    }

    public function test_ai_category_suggestion_rejects_unknown_model_category_and_missing_key(): void
    {
        $business = Business::create(['name' => 'Cafe One', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        TransactionOption::create(['business_id' => $business->id, 'kind' => 'source', 'transaction_type' => 'all', 'name' => 'Cash']);
        TransactionOption::create(['business_id' => $business->id, 'kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Ingredients']);
        Sanctum::actingAs($staff);
        $payload = ['type' => 'expense', 'source' => 'Cash', 'description' => 'Bought milk and sugar'];

        config(['services.gemini.key' => null]);
        $this->postJson('/api/v1/ai/transaction-category', $payload)->assertStatus(503);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Made-up category']]]]],
        ])]);
        config(['services.gemini.key' => 'test-key']);
        $this->postJson('/api/v1/ai/transaction-category', $payload)->assertOk()->assertJsonPath('category', null);
    }

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

    public function test_missing_server_key_returns_a_safe_configuration_message(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();
        $business = Business::create(['name' => 'Harbor Coffee', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/ai/chat', [
            'messages' => [['role' => 'user', 'text' => 'Summarize today.']],
        ])->assertStatus(503)->assertJsonPath('message', 'AI Assistant is not configured. Ask the administrator to add GEMINI_API_KEY.');

        Http::assertNothingSent();
    }

    public function test_provider_rate_limit_does_not_expose_provider_body_or_credentials(): void
    {
        config(['services.gemini.key' => 'server-only-test-credential']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['message' => 'provider-internal-secret-detail'],
        ], 429)]);
        $business = Business::create(['name' => 'Harbor Coffee', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/ai/chat', [
            'messages' => [['role' => 'user', 'text' => 'Summarize today.']],
        ])->assertStatus(503)
            ->assertJsonPath('message', 'The AI service is busy. Please wait a moment and try again.')
            ->assertDontSee('provider-internal-secret-detail')
            ->assertDontSee('server-only-test-credential');
    }

    public function test_unavailable_provider_model_returns_safe_configuration_guidance(): void
    {
        config(['services.gemini.key' => 'server-only-test-credential']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['message' => 'provider-internal-secret-detail'],
        ], 404)]);
        $business = Business::create(['name' => 'Harbor Coffee', 'status' => 'active', 'active' => true]);
        Sanctum::actingAs(User::factory()->staff()->create(['business_id' => $business->id]));

        $this->postJson('/api/v1/ai/chat', [
            'messages' => [['role' => 'user', 'text' => 'Hello']],
        ])->assertStatus(502)
            ->assertJsonPath('message', 'The configured AI model is unavailable for this server key. Ask the administrator to check GEMINI_MODEL and model access.')
            ->assertDontSee('provider-internal-secret-detail')
            ->assertDontSee('server-only-test-credential');
    }
}
