<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessPlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function tiers(): array
    {
        return [
            'Free' => ['free', 1, 3],
            'Basic' => ['basic', 2, 5],
            'Pro' => ['pro', 5, 15],
            'Enterprise' => ['enterprise', null, null],
        ];
    }

    private function business(string $tier): Business
    {
        $business = Business::create([
            'name' => $tier.' Shop', 'status' => 'active', 'active' => true,
            'plan_tier' => $tier,
            'subscription_ends_at' => $tier === 'free' ? null : now()->addMonth(),
        ]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);

        return $business;
    }

    private function product(Business $business, int $number): Product
    {
        return Product::create([
            'business_id' => $business->id, 'name' => 'Product '.$number,
            'category' => 'Drinks', 'price' => 50,
        ]);
    }

    #[DataProvider('tiers')]
    public function test_web_staff_creation_respects_each_tier(string $tier, ?int $staffLimit, ?int $productLimit): void
    {
        $business = $this->business($tier);
        $existing = $staffLimit ?? 6;
        User::factory()->count($existing)->staff()->create(['business_id' => $business->id]);

        $response = $this->actingAs($business->owner_account)->post(route('staff.store'), [
            'name' => 'New Staff', 'email' => 'new-staff@example.test',
            'password' => 'StrongPassword1!', 'password_confirmation' => 'StrongPassword1!',
            'role' => 'staff',
        ]);

        if ($staffLimit === null) {
            $response->assertRedirect(route('staff.index'));
            $this->assertSame($existing + 1, $business->users()->where('role', 'staff')->count());
        } else {
            $response->assertSessionHasErrors('role');
            $this->assertSame($existing, $business->users()->where('role', 'staff')->count());
        }
    }

    #[DataProvider('tiers')]
    public function test_web_and_api_product_creation_respect_each_tier(string $tier, ?int $staffLimit, ?int $productLimit): void
    {
        $business = $this->business($tier);
        $existing = $productLimit ?? 16;
        for ($number = 1; $number <= $existing; $number++) {
            $this->product($business, $number);
        }

        $webResponse = $this->actingAs($business->owner_account)->post(route('products.store'), [
            'name' => 'Web product', 'category' => 'Drinks', 'price' => 75,
        ]);

        if ($productLimit === null) {
            $webResponse->assertRedirect(route('products.index'));
        } else {
            $webResponse->assertSessionHasErrors('product');
        }

        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('plan-test')->plainTextToken];
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $apiResponse = $this->postJson('/api/v1/products', [
            'name' => 'Mobile product', 'category' => 'Drinks', 'price' => 80,
        ], $headers);

        if ($productLimit === null) {
            $apiResponse->assertCreated();
            $this->assertSame($existing + 2, $business->products()->count());
        } else {
            $apiResponse->assertUnprocessable()->assertJsonValidationErrors('product');
            $this->assertSame($existing, $business->products()->count());
        }
    }

    public function test_api_staff_creation_is_blocked_at_limit_and_allowed_after_upgrade(): void
    {
        $business = $this->business('free');
        User::factory()->staff()->create(['business_id' => $business->id]);
        $headers = ['Authorization' => 'Bearer '.$business->owner_account->createToken('plan-test')->plainTextToken];
        $payload = [
            'name' => 'Second Staff', 'email' => 'second-staff@example.test',
            'password' => 'StrongPassword1!', 'password_confirmation' => 'StrongPassword1!',
        ];

        $this->postJson('/api/v1/staff', $payload, $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        $business->update(['plan_tier' => 'basic', 'subscription_ends_at' => now()->addMonth()]);
        $this->postJson('/api/v1/staff', $payload, $headers)->assertCreated();
        $this->assertSame(2, $business->users()->where('role', 'staff')->count());
    }

    public function test_offline_product_replay_obeys_current_plan_and_can_retry_after_upgrade(): void
    {
        $business = $this->business('free');
        for ($number = 1; $number <= 3; $number++) {
            $this->product($business, $number);
        }
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $headers = ['Authorization' => 'Bearer '.$staff->createToken('offline-plan-test')->plainTextToken];
        $payload = [
            'client_id' => (string) Str::uuid(), 'kind' => 'product_create',
            'data' => ['name' => 'Queued product', 'category' => 'Drinks', 'price' => 85],
        ];

        $this->postJson('/api/v1/offline/mutations', $payload, $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('product');
        $this->assertDatabaseCount('offline_mutation_receipts', 0);

        $business->update(['plan_tier' => 'basic', 'subscription_ends_at' => now()->addMonth()]);
        $first = $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertCreated();
        $second = $this->postJson('/api/v1/offline/mutations', $payload, $headers)->assertCreated();
        $this->assertSame($first->json('product.id'), $second->json('product.id'));
        $this->assertSame(4, $business->products()->count());
    }

    public function test_downgrade_keeps_existing_products_and_staff_but_blocks_new_ones(): void
    {
        $business = $this->business('pro');
        $owner = $business->owner_account;
        User::factory()->count(2)->staff()->create(['business_id' => $business->id]);
        for ($number = 1; $number <= 4; $number++) {
            $this->product($business, $number);
        }
        $business->update(['plan_tier' => 'free', 'subscription_ends_at' => null]);

        $this->actingAs($owner)->get(route('products.index'))->assertOk()->assertSee('4 / 3');
        $this->get(route('staff.index'))->assertOk()->assertSee('2 / 1');
        $this->post(route('products.store'), ['name' => 'Extra', 'category' => 'Drinks', 'price' => 50])
            ->assertSessionHasErrors('product');
        $this->post(route('staff.store'), [
            'name' => 'Extra Staff', 'email' => 'extra@example.test',
            'password' => 'StrongPassword1!', 'password_confirmation' => 'StrongPassword1!', 'role' => 'staff',
        ])->assertSessionHasErrors('role');
        $this->assertSame(4, $business->products()->count());
        $this->assertSame(2, $business->users()->where('role', 'staff')->count());
    }

    public function test_deleted_product_frees_a_slot_without_affecting_other_businesses(): void
    {
        $first = $this->business('free');
        $second = $this->business('free');
        for ($number = 1; $number <= 3; $number++) {
            $this->product($first, $number);
            $this->product($second, $number);
        }

        $first->products()->firstOrFail()->delete();
        $this->actingAs($first->owner_account)->post(route('products.store'), [
            'name' => 'Replacement', 'category' => 'Drinks', 'price' => 50,
        ])->assertRedirect(route('products.index'));
        $this->assertSame(3, $first->products()->count());
        $this->assertSame(3, $second->products()->count());
    }
}
