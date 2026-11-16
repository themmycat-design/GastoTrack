<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockUnitOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_added_unit_is_business_scoped_and_visible_to_staff(): void
    {
        $business = Business::create(['name' => 'Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);
        $otherBusiness = Business::create(['name' => 'Other cafe', 'status' => 'active', 'active' => true]);
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id]);
        $otherStaff = User::factory()->staff()->create(['business_id' => $otherBusiness->id, 'status' => 'active']);

        $this->actingAs($owner)->postJson(route('stock.unit-options.store'), ['unit' => 'box'])
            ->assertOk()->assertJsonPath('unit', 'box');
        $this->actingAs($owner)->get(route('stock.index'))
            ->assertOk()->assertViewHas('unitOptions', fn ($units) => in_array('box', $units, true));
        $this->actingAs($otherOwner)->get(route('stock.index'))
            ->assertOk()->assertViewHas('unitOptions', fn ($units) => ! in_array('box', $units, true));

        $this->actingAs($staff)->getJson('/api/v1/stock')
            ->assertOk()->assertJsonPath('unit_options.5', 'box');
        $this->actingAs($otherStaff)->getJson('/api/v1/stock')
            ->assertOk()->assertJsonMissing(['box']);
        $this->actingAs($staff)->postJson(route('stock.unit-options.store'), ['unit' => 'crate'])
            ->assertForbidden();
    }

    public function test_existing_stock_units_remain_selectable_and_duplicates_are_not_added(): void
    {
        $business = Business::create(['name' => 'Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        StockItem::create([
            'business_id' => $business->id, 'name' => 'Syrup', 'unit' => 'bottle',
            'current_quantity' => 2, 'minimum_quantity' => 1, 'unit_cost' => 20,
        ]);

        $this->actingAs($owner)->get(route('stock.index'))
            ->assertOk()->assertViewHas('unitOptions', fn ($units) => in_array('bottle', $units, true));
        $this->actingAs($owner)->postJson(route('stock.unit-options.store'), ['unit' => 'Bottle'])
            ->assertOk()->assertJsonPath('unit', 'bottle');
        $this->assertDatabaseMissing('stock_unit_options', ['business_id' => $business->id, 'unit' => 'Bottle']);
        $this->actingAs($owner)->postJson(route('stock.unit-options.store'), ['unit' => '<script>'])
            ->assertUnprocessable();
    }
}
