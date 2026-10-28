<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BugReport;
use App\Models\Notification;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\User;
use App\Models\WalletNotificationCapture;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerActionableNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_payment_and_order_issue_notifications_are_scoped_to_the_business_owner(): void
    {
        $business = Business::create(['name' => 'Owner Alerts Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $otherBusiness = Business::create(['name' => 'Other Alerts Cafe', 'status' => 'active', 'active' => true]);
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id]);
        $order = $this->order($business, $staff);
        $notifications = app(NotificationService::class);

        $notifications->notifyPaymentNeedsReview($order);
        $notifications->notifyPaymentFailed($order);
        $notifications->notifyOrderCancelled($order);
        $notifications->notifyRefundRequested($order);

        $this->assertSame(4, Notification::where('user_id', $owner->id)->count());
        $this->assertSame(0, Notification::where('user_id', $otherOwner->id)->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'type' => 'order_refund_pending',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Payment needs review')
            ->assertSee('Payment attempt failed')
            ->assertSee('Refund needs review')
            ->assertSee(route('orders.show', $order));
    }

    public function test_unmatched_wallet_receipt_alert_is_resolved_after_order_match(): void
    {
        $business = Business::create(['name' => 'Wallet Alerts Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $capture = WalletNotificationCapture::create([
            'client_id' => '1360f2dd-0c06-4b3a-87d8-1088e7f81a89',
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'source' => 'gcash',
            'direction' => 'received',
            'amount' => 125,
            'currency' => 'PHP',
            'message' => 'You received PHP 125.00',
            'captured_at' => now(),
        ]);
        $order = $this->order($business, $staff);
        $service = app(NotificationService::class);

        $service->notifyWalletCapture($capture);
        $notification = Notification::where('user_id', $owner->id)->where('type', 'wallet_payment_review')->firstOrFail();
        $this->assertStringContainsString('not independently verified', $notification->message);
        $this->assertSame($capture->id, $notification->data['capture_id']);

        $capture->update(['used_order_id' => $order->id]);
        $service->resolveWalletCaptureNotification($capture, $order);

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame('Wallet signal matched to order', $notification->fresh()->title);
        $this->assertStringContainsString('did not verify it with the wallet provider', $notification->fresh()->message);
    }

    public function test_resolving_or_repeating_an_order_issue_does_not_leave_a_stale_unread_badge(): void
    {
        $business = Business::create(['name' => 'Resolved Alerts Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $order = $this->order($business, $staff);
        $service = app(NotificationService::class);

        $service->notifyPaymentFailed($order);
        $notification = Notification::where('user_id', $owner->id)->firstOrFail();
        $notification->update(['read_at' => now()]);
        $service->notifyPaymentFailed($order);

        $this->assertSame(1, Notification::where('user_id', $owner->id)->count());
        $this->assertNull($notification->fresh()->read_at);

        $service->resolveOrderIssueNotifications($order, 'confirmed');

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame('Payment confirmed', $notification->fresh()->title);
    }

    public function test_super_admin_bug_report_updates_create_an_owner_system_notification(): void
    {
        $business = Business::create(['name' => 'Bug Update Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $reporter = User::factory()->staff()->create(['business_id' => $business->id]);
        $report = BugReport::create([
            'business_id' => $business->id,
            'reported_by' => $reporter->id,
            'subject' => 'Checkout screen freezes',
            'category' => 'other',
            'area' => 'orders',
            'description' => 'The order screen stops responding.',
            'priority' => 'medium',
            'status' => 'under_review',
            'is_legacy' => false,
        ]);

        app(NotificationService::class)->notifyBugReportUpdate($report, true);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'type' => 'bug_report_update',
            'title' => 'Bug report updated',
        ]);
    }

    public function test_inventory_badge_keeps_the_persistent_alert_style_and_count(): void
    {
        $business = Business::create(['name' => 'Inventory Badge Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        StockItem::create([
            'business_id' => $business->id,
            'name' => 'Milk',
            'unit' => 'L',
            'current_quantity' => 0,
            'minimum_quantity' => 5,
            'unit_cost' => 80,
            'active' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Inventory, 1 out of stock and 0 low stock')
            ->assertSee('border-rose-200 bg-rose-50 text-rose-800 shadow-sm')
            ->assertSee('rounded-full bg-rose-600');
    }

    private function order(Business $business, User $staff): Order
    {
        return Order::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'order_number' => '1001',
            'customer_name' => 'Walk-in customer',
            'payment_type' => 'cashless',
            'payment_method' => 'gcash',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal' => 125,
            'total' => 125,
        ]);
    }
}
