<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Notification;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerLiveStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_changes_for_own_staff_stock_activity_but_not_another_business(): void
    {
        $business = Business::create(['name' => 'Owner live cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $otherBusiness = Business::create(['name' => 'Other live cafe', 'status' => 'active', 'active' => true]);

        $this->actingAs($owner);
        $initial = $this->getJson(route('owner.live-state'))->assertOk()->json('revision');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $initial);

        $otherStock = StockItem::create([
            'business_id' => $otherBusiness->id, 'name' => 'Other beans', 'unit' => 'kg',
            'current_quantity' => 10, 'minimum_quantity' => 2, 'active' => true,
        ]);
        StockMovement::create([
            'stock_item_id' => $otherStock->id, 'user_id' => $staff->id,
            'type' => 'out', 'quantity' => 1, 'previous_quantity' => 10,
            'new_quantity' => 9, 'reason' => 'Other shop adjustment',
        ]);
        $this->assertSame($initial, $this->getJson(route('owner.live-state'))->assertOk()->json('revision'));

        $stock = StockItem::create([
            'business_id' => $business->id, 'name' => 'Milk', 'unit' => 'L',
            'current_quantity' => 10, 'minimum_quantity' => 2, 'active' => true,
        ]);
        StockMovement::create([
            'stock_item_id' => $stock->id, 'user_id' => $staff->id,
            'type' => 'out', 'quantity' => 1, 'previous_quantity' => 10,
            'new_quantity' => 9, 'reason' => 'Staff adjustment',
        ]);
        $afterStock = $this->getJson(route('owner.live-state'))->assertOk()->json('revision');
        $this->assertNotSame($initial, $afterStock);

        Transaction::create([
            'business_id' => $business->id, 'user_id' => $staff->id,
            'type' => 'income', 'category' => 'Sales', 'amount' => 120,
            'source' => 'cash', 'transaction_date' => today(),
        ]);
        $afterSale = $this->getJson(route('owner.live-state'))->assertOk()->json('revision');
        $this->assertNotSame($afterStock, $afterSale);

        Notification::create([
            'business_id' => $business->id, 'user_id' => $owner->id,
            'type' => 'low_stock', 'title' => 'Stock alert', 'message' => 'Milk needs attention.',
        ]);
        $this->assertNotSame($afterSale, $this->getJson(route('owner.live-state'))->assertOk()->json('revision'));
    }

    public function test_staff_cannot_poll_owner_page_state(): void
    {
        $business = Business::create(['name' => 'Owner live cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);

        $this->actingAs($staff)->getJson(route('owner.live-state'))->assertForbidden();
    }
}
