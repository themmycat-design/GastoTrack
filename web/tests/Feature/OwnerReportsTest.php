<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletNotificationCapture;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerReportsTest extends TestCase
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

    public function test_owner_reports_separate_confirmed_sales_ledger_and_unverified_wallet_alerts(): void
    {
        $business = Business::create(['name' => 'Report Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);
        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Iced Coffee',
            'category' => 'Coffee',
            'price' => 125,
            'cost' => 40,
            'active' => true,
            'is_available' => true,
        ]);
        $order = Order::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'order_number' => 'ORD-PRIVATE-REF',
            'subtotal' => 125,
            'total' => 125,
            'payment_type' => 'cashless',
            'payment_method' => 'gcash',
            'status' => 'completed',
            'payment_status' => 'confirmed',
            'payment_confirmed_at' => '2026-10-09 10:00:00',
            'financials_committed_at' => '2026-10-09 10:00:00',
            'completed_at' => '2026-10-09 10:00:00',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 125,
            'quantity' => 1,
            'subtotal' => 125,
        ]);
        Transaction::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'order_id' => $order->id,
            'type' => 'income',
            'category' => 'Sales',
            'amount' => 125,
            'source' => 'GCash',
            'transaction_date' => '2026-10-09',
            'entry_method' => 'order_system',
        ]);
        Transaction::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'type' => 'expense',
            'category' => 'Supplies',
            'amount' => 20,
            'transaction_date' => '2026-10-09',
        ]);
        WalletNotificationCapture::create([
            'client_id' => '147ae7ce-d006-4914-9736-398a855729a2',
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'source' => 'gcash',
            'direction' => 'received',
            'amount' => 125,
            'currency' => 'PHP',
            'merchant' => 'Coffee Shop',
            'message' => 'You received PHP 125.00. Raw private alert detail',
            'captured_at' => '2026-10-09 10:01:00',
        ]);

        $this->actingAs($owner)
            ->get(route('owner.reports.show', ['section' => 'sales-orders', 'start_date' => '2026-10-09', 'end_date' => '2026-10-09']))
            ->assertOk()
            ->assertViewHas('sales', fn (array $sales): bool =>
                $sales['orderCount'] === 1 && $sales['amount'] === 125.0
            )
            ->assertViewHas('ledger', fn (array $ledger): bool =>
                $ledger['income'] === 125.0 && $ledger['expenses'] === 20.0
            )
            ->assertViewHas('payments', fn (array $payments): bool =>
                $payments['walletCount'] === 1 && $payments['awaitingCount'] === 0
            )
            ->assertViewHas('staffActivity', fn (array $activity): bool =>
                count($activity) === 1 && $activity[0]['orders'] === 1 && $activity[0]['ledgerEntries'] === 2
            )
            ->assertSee('Reports')
            ->assertSee('Sales and orders')
            ->assertSee('Income and expenses')
            ->assertSee('Product costs and margins')
            ->assertSee('Inventory and waste')
            ->assertSee('Payment review')
            ->assertSee('Staff activity')
            ->assertSee('Sales and orders</h1>', false)
            ->assertDontSee('id="report-ledger"', false)
            ->assertDontSee('Raw GCash & Maya notifications')
            ->assertDontSee('ORD-PRIVATE-REF')
            ->assertDontSee('Raw private alert detail');

        $this->actingAs($owner)
            ->get(route('owner.reports.export', ['start_date' => '2026-10-09', 'end_date' => '2026-10-09']))
            ->assertDownload('gastotrack-owner-reports-2026-10-09-to-2026-10-09.csv');
    }

    public function test_staff_cannot_access_owner_reports(): void
    {
        $business = Business::create(['name' => 'Staff Report Cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);

        $this->actingAs($staff)
            ->get(route('owner.reports.index'))
            ->assertForbidden();
    }

    public function test_each_report_submenu_opens_its_own_screen(): void
    {
        $business = Business::create(['name' => 'Separate Reports Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $screens = [
            'sales-orders' => 'sales',
            'income-expenses' => 'ledger',
            'product-costs' => 'products',
            'inventory-waste' => 'inventory',
            'payment-review' => 'payments',
            'staff-activity' => 'staff',
        ];

        foreach ($screens as $route => $sectionId) {
            $response = $this->actingAs($owner)
                ->get(route('owner.reports.show', ['section' => $route, 'reports_open' => 1]))
                ->assertOk()
                ->assertViewHas('activeReportSection', fn (string $value): bool => $value === $sectionId)
                ->assertSee('id="report-'.$sectionId.'"', false);

            foreach ($screens as $otherSectionId) {
                if ($otherSectionId !== $sectionId) {
                    $response->assertDontSee('id="report-'.$otherSectionId.'"', false);
                }
            }
        }
    }
}
