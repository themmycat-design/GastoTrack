<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-09 12:00:00');
        CarbonImmutable::setTestNow('2026-10-09 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_overview_shows_business_scoped_finances_sales_and_completed_orders(): void
    {
        $business = $this->business('Owner Dashboard Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $activeStaff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);
        $this->transaction($business, $activeStaff, 'income', 200, '2026-10-09');
        $this->transaction($business, $activeStaff, 'expense', 50, '2026-10-03');
        $this->transaction($business, $activeStaff, 'income', 900, '2026-09-30');
        $otherBusiness = $this->business('Other Shop');
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id]);
        User::factory()->staff()->create(['business_id' => $otherBusiness->id, 'status' => 'active']);
        $this->transaction($otherBusiness, $otherOwner, 'income', 50000, '2026-10-09');
        StockItem::create([
            'business_id' => $otherBusiness->id,
            'name' => 'Other shop inventory', 'unit' => 'kg', 'current_quantity' => 0,
            'minimum_quantity' => 1, 'unit_cost' => 900, 'active' => true,
        ]);

        StockItem::create([
            'business_id' => $business->id,
            'name' => 'Milk', 'unit' => 'L', 'current_quantity' => 0,
            'minimum_quantity' => 5, 'unit_cost' => 80, 'active' => true,
        ]);
        StockItem::create([
            'business_id' => $business->id,
            'name' => 'Coffee beans', 'unit' => 'kg', 'current_quantity' => 2,
            'minimum_quantity' => 5, 'unit_cost' => 400, 'active' => true,
        ]);
        StockItem::create([
            'business_id' => $business->id,
            'name' => 'Inactive supplies', 'unit' => 'pc', 'current_quantity' => 0,
            'minimum_quantity' => 10, 'unit_cost' => 20, 'active' => false,
        ]);
        Product::create([
            'business_id' => $business->id, 'name' => 'Latte', 'category' => 'Coffee',
            'price' => 140, 'cost' => 50, 'active' => true, 'is_available' => true,
        ]);
        Product::create([
            'business_id' => $otherBusiness->id, 'name' => 'Other Shop Item', 'category' => 'Tea',
            'price' => 120, 'cost' => 40, 'active' => true, 'is_available' => true,
        ]);

        $this->order($business, $activeStaff, [
            'status' => 'completed', 'payment_status' => 'confirmed', 'payment_method' => 'cash',
            'total' => 150, 'completed_at' => '2026-10-09 09:00:00',
        ]);
        // A completed order with a detected cashless notification is not
        // treated as confirmed sales until an authorized confirmation occurs.
        $this->order($business, $activeStaff, [
            'status' => 'completed', 'payment_status' => 'detected', 'payment_method' => 'gcash',
            'total' => 700, 'completed_at' => '2026-10-09 10:00:00',
        ]);
        $this->order($business, $activeStaff, [
            'order_number' => 'OLDER-COMPLETED-ORDER', 'status' => 'completed',
            'payment_status' => 'confirmed', 'total' => 80,
            'completed_at' => '2026-09-15 10:00:00',
        ]);
        $this->order($business, $activeStaff, [
            'status' => 'cancelled', 'payment_status' => 'cancelled', 'payment_method' => 'cash',
            'total' => 300, 'completed_at' => null,
        ]);
        $this->order($otherBusiness, $otherOwner, [
            'order_number' => 'OTHER-SHOP-ORDER', 'status' => 'completed',
            'payment_status' => 'confirmed', 'total' => 50000,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('ledger', fn (array $ledger): bool =>
                $ledger['income'] === 200.0
                && $ledger['expenses'] === 50.0
                && $ledger['netRecordedMovement'] === 150.0
            )
            ->assertViewHas('todaySales', 150.0)
            ->assertViewHas('activeProductCount', 1)
            ->assertViewHas('activeStockCount', 2)
            ->assertViewHas('recentCompletedOrders', fn ($orders): bool => $orders->count() === 3
                && $orders->every(fn ($order): bool => (int) $order->business_id === (int) $business->id))
            ->assertSee('Owner Dashboard Cafe')
            ->assertSee('Financial snapshot')
            ->assertSee('Completed orders')
            ->assertSee('OLDER-COMPLETED-ORDER')
            ->assertSee('Payment awaiting verification')
            ->assertSee(route('orders.index', ['status' => 'completed']))
            ->assertDontSee('Payments needing attention')
            ->assertDontSee('OTHER-SHOP-ORDER')
            ->assertSee('Inventory, 1 out of stock and 1 low stock')
            ->assertSee('Stock notifications')
            ->assertSee(route('stock.index', ['low_stock' => 1, 'active' => 1]))
            ->assertSee('Milk')
            ->assertSee('Coffee beans')
            ->assertDontSee('Stock needing attention')
            ->assertDontSee('Other shop inventory')
            ->assertDontSee('Average order value')
            ->assertDontSee('Recent orders')
            ->assertDontSee('50,000.00')
            ->assertDontSee('Other Shop Item');
    }

    public function test_new_business_overview_shows_setup_without_duplicate_empty_panels(): void
    {
        $business = $this->business('New Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Getting started')
            ->assertSee('Set up products')
            ->assertSee('No stock alerts right now.')
            ->assertDontSee('Recent transactions')
            ->assertDontSee('Stock needing attention');
    }

    public function test_inventory_alert_badge_reflects_current_active_stock_on_every_owner_page(): void
    {
        $business = $this->business('Stock Alerts Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $outOfStock = StockItem::create([
            'business_id' => $business->id, 'name' => 'Milk', 'unit' => 'L',
            'current_quantity' => 0, 'minimum_quantity' => 5, 'unit_cost' => 80, 'active' => true,
        ]);
        $lowStock = StockItem::create([
            'business_id' => $business->id, 'name' => 'Beans', 'unit' => 'kg',
            'current_quantity' => 2, 'minimum_quantity' => 5, 'unit_cost' => 400, 'active' => true,
        ]);
        StockItem::create([
            'business_id' => $business->id, 'name' => 'Inactive item', 'unit' => 'pc',
            'current_quantity' => 0, 'minimum_quantity' => 5, 'unit_cost' => 1, 'active' => false,
        ]);
        $deleted = StockItem::create([
            'business_id' => $business->id, 'name' => 'Deleted item', 'unit' => 'pc',
            'current_quantity' => 0, 'minimum_quantity' => 5, 'unit_cost' => 1, 'active' => true,
        ]);
        $deleted->delete();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Inventory, 1 out of stock and 1 low stock')
            ->assertDontSee('Inactive item')
            ->assertDontSee('Deleted item');

        $outOfStock->update(['current_quantity' => 6]);
        $this->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Inventory, 0 out of stock and 1 low stock');

        $lowStock->update(['current_quantity' => 8]);
        $this->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('No stock alerts right now.')
            ->assertDontSee('Inventory, 0 out of stock and 1 low stock');
    }

    public function test_analytics_compares_equivalent_elapsed_period_and_reports_missing_baselines(): void
    {
        $business = $this->business('Analytics Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $this->transaction($business, $owner, 'income', 90, '2026-10-09');
        $this->transaction($business, $owner, 'income', 30, '2026-09-09');
        // A large amount later in the previous month must not contaminate the
        // month-to-date comparison (Oct 1–9 versus Sep 1–9).
        $this->transaction($business, $owner, 'income', 1000, '2026-09-30');
        $otherBusiness = $this->business('Other Analytics Shop');
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id]);
        $this->transaction($otherBusiness, $otherOwner, 'income', 90000, '2026-10-09');

        $this->actingAs($owner)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertViewHas('current', fn (array $current): bool => $current['income'] === 90.0)
            ->assertViewHas('comparison', fn (array $comparison): bool => $comparison['income'] === 30.0)
            ->assertViewHas('growth', fn (array $growth): bool =>
                $growth['income'] === 200.0
                && $growth['expenses'] === null
            )
            ->assertViewHas('comparisonStartDate', fn ($date): bool => $date->toDateString() === '2026-09-01')
            ->assertViewHas('comparisonEndDate', fn ($date): bool => $date->toDateString() === '2026-09-09')
            ->assertSee('Business Insights')
            ->assertSee('Recorded income rose 200.0%')
            ->assertDontSee('What these numbers mean')
            ->assertDontSee('Customer activity is increasing');
    }

    public function test_weekly_analytics_uses_monday_to_today_and_the_matching_previous_week(): void
    {
        $business = $this->business('Weekly Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $this->transaction($business, $owner, 'income', 100, '2026-10-05');
        $this->transaction($business, $owner, 'expense', 25, '2026-10-09');
        $this->transaction($business, $owner, 'income', 500, '2026-10-04');
        $this->transaction($business, $owner, 'income', 50, '2026-09-28');
        $this->transaction($business, $owner, 'expense', 10, '2026-10-02');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'week']))
            ->assertOk()
            ->assertViewHas('period', 'week')
            ->assertViewHas('startDate', fn ($date): bool => $date->toDateString() === '2026-10-05')
            ->assertViewHas('endDate', fn ($date): bool => $date->toDateString() === '2026-10-09')
            ->assertViewHas('comparisonStartDate', fn ($date): bool => $date->toDateString() === '2026-09-28')
            ->assertViewHas('comparisonEndDate', fn ($date): bool => $date->toDateString() === '2026-10-02')
            ->assertViewHas('current', fn (array $current): bool => $current['income'] === 100.0 && $current['expenses'] === 25.0)
            ->assertViewHas('comparison', fn (array $previous): bool => $previous['income'] === 50.0 && $previous['expenses'] === 10.0)
            ->assertViewHas('chartInterval', 'day')
            ->assertViewHas('chart', fn (array $chart): bool => count($chart['labels']) === 5
                && $chart['income'][0] === 100.0
                && $chart['expenses'][4] === 25.0)
            ->assertSee('Recorded expenses rose 150.0%')
            ->assertSee('Daily income and expenses');
    }

    public function test_yearly_analytics_uses_year_to_date_and_monthly_chart_points(): void
    {
        $business = $this->business('Yearly Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $this->transaction($business, $owner, 'income', 100, '2026-01-15');
        $this->transaction($business, $owner, 'expense', 30, '2026-10-09');
        $this->transaction($business, $owner, 'income', 50, '2025-01-15');
        $this->transaction($business, $owner, 'expense', 10, '2025-10-09');
        $this->transaction($business, $owner, 'income', 900, '2025-10-10');
        $otherBusiness = $this->business('Other Yearly Shop');
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id]);
        $this->transaction($otherBusiness, $otherOwner, 'income', 50000, '2026-01-15');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'year']))
            ->assertOk()
            ->assertViewHas('period', 'year')
            ->assertViewHas('startDate', fn ($date): bool => $date->toDateString() === '2026-01-01')
            ->assertViewHas('comparisonStartDate', fn ($date): bool => $date->toDateString() === '2025-01-01')
            ->assertViewHas('comparisonEndDate', fn ($date): bool => $date->toDateString() === '2025-10-09')
            ->assertViewHas('current', fn (array $current): bool => $current['income'] === 100.0 && $current['expenses'] === 30.0)
            ->assertViewHas('comparison', fn (array $previous): bool => $previous['income'] === 50.0 && $previous['expenses'] === 10.0)
            ->assertViewHas('chartInterval', 'month')
            ->assertViewHas('chart', fn (array $chart): bool => count($chart['labels']) === 10
                && $chart['labels'][0] === 'Jan'
                && $chart['labels'][9] === 'Oct'
                && $chart['income'][0] === 100.0
                && $chart['expenses'][9] === 30.0)
            ->assertViewHas('chartLinks', fn (array $links): bool => count($links) === 10
                && $links[0] === route('analytics.index', ['start_date' => '2026-01-01', 'end_date' => '2026-01-31'])
                && $links[9] === route('analytics.index', ['start_date' => '2026-10-01', 'end_date' => '2026-10-09']))
            ->assertSee('Monthly income and expenses')
            ->assertDontSee('50,000.00');
    }

    public function test_daily_analytics_drilldown_shows_only_the_owners_transactions_orders_and_order_ingredient_use(): void
    {
        $business = $this->business('Daily Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $this->transaction($business, $staff, 'income', 140, '2026-10-09')->update(['description' => 'Daily latte sale']);
        $this->transaction($business, $staff, 'expense', 30, '2026-10-09')->update(['description' => 'Daily supplies']);
        $this->transaction($business, $staff, 'income', 800, '2026-10-08');

        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Latte', 'category' => 'Coffee',
            'price' => 140, 'cost' => 50, 'active' => true, 'is_available' => true,
        ]);
        $milk = StockItem::create([
            'business_id' => $business->id, 'name' => 'Milk', 'unit' => 'ml',
            'current_quantity' => 500, 'minimum_quantity' => 100, 'unit_cost' => 1, 'active' => true,
        ]);
        $order = $this->order($business, $staff, [
            'order_number' => 'DAILY-LATTE-ORDER', 'status' => 'completed',
            'payment_status' => 'confirmed', 'total' => 140,
            'completed_at' => '2026-10-09 10:00:00',
            'financials_committed_at' => '2026-10-09 10:00:00',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Latte', 'quantity' => 1,
            'price' => 140, 'subtotal' => 140,
        ]);
        $this->stockMovement($milk, $staff, $order, 'out', 100);
        $this->stockMovement($milk, $staff, null, 'out', 20); // Manual adjustment is not ingredient use.

        $otherBusiness = $this->business('Other Daily Shop');
        $otherStaff = User::factory()->staff()->create(['business_id' => $otherBusiness->id]);
        $this->transaction($otherBusiness, $otherStaff, 'income', 90000, '2026-10-09')->update(['description' => 'Private sale']);
        $otherOrder = $this->order($otherBusiness, $otherStaff, ['order_number' => 'PRIVATE-DAILY-ORDER']);
        $otherStock = StockItem::create([
            'business_id' => $otherBusiness->id, 'name' => 'Private beans', 'unit' => 'g',
            'current_quantity' => 20, 'minimum_quantity' => 1, 'unit_cost' => 1, 'active' => true,
        ]);
        $this->stockMovement($otherStock, $otherStaff, $otherOrder, 'out', 10);

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'week']))
            ->assertOk()
            ->assertSee(route('analytics.day', ['date' => '2026-10-09']));

        $this->get(route('analytics.day', ['date' => '2026-10-09']))
            ->assertOk()
            ->assertViewHas('income', 140.0)
            ->assertViewHas('expenses', 30.0)
            ->assertViewHas('ingredientsUsed', fn ($used): bool => $used->count() === 1
                && $used->first()->name === 'Milk'
                && (float) $used->first()->quantity_used === 100.0)
            ->assertSee('Daily latte sale')
            ->assertSee('Daily supplies')
            ->assertSee('DAILY-LATTE-ORDER')
            ->assertSee('100.00 ml')
            ->assertDontSee('Private sale')
            ->assertDontSee('PRIVATE-DAILY-ORDER')
            ->assertDontSee('Private beans')
            ->assertDontSee('90,000.00');
    }

    public function test_daily_analytics_rejects_invalid_future_and_unowned_requests(): void
    {
        $business = $this->business('Date Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);

        $this->get(route('analytics.day', ['date' => '2026-10-09']))->assertRedirect();
        $this->actingAs($staff)->get(route('analytics.day', ['date' => '2026-10-09']))->assertForbidden();
        $this->actingAs($owner)->get(route('analytics.day', ['date' => '2026-02-30']))->assertNotFound();
        $this->actingAs($owner)->get(route('analytics.day', ['date' => '2026-10-10']))->assertNotFound();
        $this->actingAs($owner)
            ->get(route('analytics.day', ['date' => '2026-10-08']))
            ->assertOk()
            ->assertSee('No transactions were recorded for this date.')
            ->assertSee('No order-linked ingredient deductions were recorded for this date.');
    }

    public function test_yearly_comparison_clamps_leap_day_to_february_28(): void
    {
        Carbon::setTestNow('2028-02-29 12:00:00');
        CarbonImmutable::setTestNow('2028-02-29 12:00:00');
        $business = $this->business('Leap Year Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'year']))
            ->assertOk()
            ->assertViewHas('comparisonEndDate', fn ($date): bool => $date->toDateString() === '2027-02-28');
    }

    public function test_analytics_rejects_invalid_and_overlong_date_filters(): void
    {
        $business = $this->business('Filter Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->get(route('analytics.index', ['start_date' => '2026-10-08', 'end_date' => '2026-10-01']))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['start_date' => '2025-01-01', 'end_date' => '2026-10-01']))
            ->assertSessionHasErrors('start_date');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['period' => 'quarter']))
            ->assertSessionHasErrors('period');
    }

    public function test_analytics_handles_a_zero_baseline_and_an_empty_period_without_division_errors(): void
    {
        $business = $this->business('Quiet Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $this->transaction($business, $owner, 'income', 25, '2026-10-09');

        $this->actingAs($owner)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertViewHas('growth', fn (array $growth): bool => $growth['income'] === null)
            ->assertSee('No previous-period baseline');

        $this->actingAs($owner)
            ->get(route('analytics.index', ['start_date' => '2026-08-01', 'end_date' => '2026-08-05']))
            ->assertOk()
            ->assertViewHas('current', fn (array $current): bool =>
                $current['income'] === 0.0
                && $current['expenses'] === 0.0
                && $current['transactionCount'] === 0
            )
            ->assertViewHas('period', 'custom')
            ->assertViewHas('growth', fn (array $growth): bool => $growth['income'] === null);
    }

    public function test_month_to_date_comparison_clamps_to_the_previous_month_length(): void
    {
        Carbon::setTestNow('2026-03-31 12:00:00');
        CarbonImmutable::setTestNow('2026-03-31 12:00:00');
        $business = $this->business('Month-end Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $this->transaction($business, $owner, 'income', 70, '2026-03-31');
        $this->transaction($business, $owner, 'income', 50, '2026-02-28');

        $this->actingAs($owner)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertViewHas('comparisonStartDate', fn ($date): bool => $date->toDateString() === '2026-02-01')
            ->assertViewHas('comparisonEndDate', fn ($date): bool => $date->toDateString() === '2026-02-28')
            ->assertViewHas('comparison', fn (array $comparison): bool => $comparison['income'] === 50.0);
    }

    public function test_owner_order_list_and_details_are_scoped_to_the_authenticated_business(): void
    {
        $business = $this->business('Private Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'name' => 'Cafe Staff']);
        $ownOrder = $this->order($business, $staff, [
            'status' => 'pending', 'payment_status' => 'detected', 'payment_method' => 'gcash',
            'order_number' => 'CAFE-ORDER-REFERENCE', 'total' => 125,
        ]);
        $otherBusiness = $this->business('Private Bakery');
        $otherStaff = User::factory()->staff()->create(['business_id' => $otherBusiness->id]);
        $otherOrder = $this->order($otherBusiness, $otherStaff, [
            'status' => 'completed', 'payment_status' => 'confirmed', 'payment_method' => 'cash',
            'order_number' => 'PRIVATE-ORDER-REFERENCE', 'total' => 25,
        ]);

        $orderList = $this->actingAs($owner)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary): bool => (int) $summary->total_orders === 1
                && (int) $summary->awaiting_verification === 1
                && (int) $summary->completed_paid === 0)
            ->assertSee('CAFE-ORDER-REFERENCE')
            ->assertDontSee('PRIVATE-ORDER-REFERENCE');
        preg_match('/<select id="status"[^>]*>(.*?)<\/select>/s', $orderList->getContent(), $statusSelect);
        $this->assertNotEmpty($statusSelect);
        $this->assertStringNotContainsString('value="pending"', $statusSelect[1]);
        $this->assertStringNotContainsString('value="preparing"', $statusSelect[1]);
        $this->assertStringNotContainsString('value="ready"', $statusSelect[1]);
        preg_match('/<select id="payment_status"[^>]*>(.*?)<\/select>/s', $orderList->getContent(), $paymentStatusSelect);
        $this->assertNotEmpty($paymentStatusSelect);
        $this->assertStringNotContainsString('value="pending"', $paymentStatusSelect[1]);
        $this->assertStringNotContainsString('value="detected"', $paymentStatusSelect[1]);
        $this->assertStringNotContainsString('value="refund_pending"', $paymentStatusSelect[1]);

        $this->actingAs($owner)
            ->get(route('orders.index', ['search' => 'CAFE-ORDER', 'payment_status' => 'detected']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->total() === 1);

        $this->actingAs($owner)
            ->get(route('orders.index', ['search' => 'PRIVATE-ORDER']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->total() === 0);

        $this->actingAs($owner)
            ->get(route('orders.show', $ownOrder))
            ->assertOk()
            ->assertSee('Cafe Staff')
            ->assertSee('Awaiting verification');

        $this->actingAs($owner)->get(route('orders.show', $otherOrder))->assertNotFound();
    }

    public function test_owner_can_search_an_order_by_its_previous_reference_after_renumbering(): void
    {
        $business = $this->business('Old Reference Cafe');
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $order = $this->order($business, $staff, ['order_number' => '0001']);
        $order->forceFill(['legacy_order_number' => 'ORD-OLD-REFERENCE'])->save();

        $this->actingAs($owner)
            ->get(route('orders.index', ['search' => 'ORD-OLD-REFERENCE']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->total() === 1
                && $orders->first()->id === $order->id)
            ->assertSee('0001');
    }

    public function test_transaction_and_staff_pages_do_not_expose_another_business_records(): void
    {
        $ownerBusiness = $this->business('First Shop');
        $owner = User::factory()->owner()->create(['business_id' => $ownerBusiness->id]);
        $otherBusiness = $this->business('Second Shop');
        $otherStaff = User::factory()->staff()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Second Shop Private Staff',
        ]);
        $privateTransaction = $this->transaction($otherBusiness, $otherStaff, 'income', 50000, '2026-10-09');
        $privateTransaction->update(['category' => 'Second Shop Private Income']);

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertDontSee('Second Shop Private Income')
            ->assertDontSee('Second Shop Private Staff');

        $this->actingAs($owner)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertDontSee('Second Shop Private Staff');
    }

    private function business(string $name): Business
    {
        return Business::create(['name' => $name, 'status' => 'active', 'active' => true]);
    }

    private function transaction(Business $business, User $user, string $type, float $amount, string $date): Transaction
    {
        return Transaction::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'type' => $type,
            'category' => $type === 'income' ? 'Sales' : 'Supplies',
            'amount' => $amount,
            'source' => 'cash',
            'transaction_date' => $date,
        ]);
    }

    private function order(Business $business, User $creator, array $attributes): Order
    {
        return Order::create(array_merge([
            'business_id' => $business->id,
            'user_id' => $creator->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal' => $attributes['total'] ?? 100,
            'total' => 100,
            'payment_type' => ($attributes['payment_method'] ?? 'cash') === 'cash' ? 'cash' : 'cashless',
            'payment_method' => 'cash',
            'status' => 'pending',
            'payment_status' => 'pending',
            'completed_at' => null,
        ], $attributes));
    }

    private function stockMovement(StockItem $stock, User $staff, ?Order $order, string $type, float $quantity): StockMovement
    {
        return StockMovement::create([
            'stock_item_id' => $stock->id,
            'user_id' => $staff->id,
            'order_id' => $order?->id,
            'type' => $type,
            'quantity' => $quantity,
            'previous_quantity' => 500,
            'new_quantity' => 500 - $quantity,
        ]);
    }
}
