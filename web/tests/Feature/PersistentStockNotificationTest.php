<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Notification;
use App\Models\StockItem;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersistentStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_notifications_clears_the_bell_but_inventory_badge_stays_until_restocked(): void
    {
        [$owner, $item] = $this->lowStockFixture();
        $otherNotification = Notification::create([
            'user_id' => $owner->id,
            'business_id' => $owner->business_id,
            'type' => 'general',
            'title' => 'General notice',
            'message' => 'This notice can be dismissed.',
        ]);
        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications, 2 unread')
            ->assertSee('Inventory, 0 out of stock and 1 low stock');

        $this->post(route('owner.notifications.read-all'))->assertRedirect();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications, none unread')
            ->assertSee('Inventory, 0 out of stock and 1 low stock');
        $this->assertSame(2.0, (float) $item->fresh()->current_quantity);
        $this->assertSame(2, Notification::where('user_id', $owner->id)->whereNotNull('read_at')->count());
        $this->assertNotNull($otherNotification->fresh()->read_at);
    }

    public function test_api_mark_all_read_clears_low_stock_and_other_notification_unread_counts(): void
    {
        [$owner] = $this->lowStockFixture();
        $otherNotification = Notification::create([
            'user_id' => $owner->id,
            'business_id' => $owner->business_id,
            'type' => 'general',
            'title' => 'General notice',
            'message' => 'This can be dismissed.',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read');

        $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->assertNotNull($otherNotification->fresh()->read_at);
    }

    public function test_owner_can_delete_a_notification_without_clearing_the_inventory_warning(): void
    {
        [$owner, $item, $notification] = $this->lowStockFixture();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Delete notification: Low stock');

        $this->deleteJson(route('owner.notifications.destroy', $notification->id))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications, none unread')
            ->assertDontSee('Delete notification: Low stock')
            ->assertSee('Inventory, 0 out of stock and 1 low stock');

        $this->assertSame(1, Notification::where('user_id', $owner->id)->count());
        $this->assertNotNull($notification->fresh()->data['dismissed_at'] ?? null);
        $this->assertSame(2.0, (float) $item->fresh()->current_quantity);
    }

    public function test_owner_cannot_delete_another_business_notification(): void
    {
        [$owner] = $this->lowStockFixture();
        [$otherOwner, , $otherNotification] = $this->lowStockFixture();

        $this->actingAs($owner)
            ->deleteJson(route('owner.notifications.destroy', $otherNotification->id))
            ->assertNotFound();

        $this->assertNull($otherNotification->fresh()->data['dismissed_at'] ?? null);
        $this->assertNotSame($owner->business_id, $otherOwner->business_id);
    }

    public function test_restocking_allows_a_new_alert_after_a_deleted_stock_notification(): void
    {
        [$owner, $item, $notification] = $this->lowStockFixture();
        $this->actingAs($owner)->delete(route('owner.notifications.destroy', $notification->id))->assertRedirect();

        $item->update(['current_quantity' => 6]);
        app(NotificationService::class)->notifyLowStock($item->fresh());
        $item->update(['current_quantity' => 2]);
        app(NotificationService::class)->notifyLowStock($item->fresh());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications, 1 unread')
            ->assertSee('Delete notification: Low stock');
        $this->assertSame(2, Notification::where('user_id', $owner->id)->count());
    }

    public function test_restocking_resolves_unread_alert_and_a_later_low_stock_cycle_creates_a_new_one(): void
    {
        [$owner, $item, $notification] = $this->lowStockFixture();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/stock/'.$item->id.'/adjust', [
            'type' => 'add',
            'quantity' => 4,
        ])->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
        $this->postJson('/api/v1/stock/'.$item->id.'/adjust', [
            'type' => 'deduct',
            'quantity' => 4,
        ])->assertOk();

        $this->assertDatabaseCount('notifications', 2);
        $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    private function lowStockFixture(): array
    {
        $business = Business::create(['name' => 'Persistent Alerts Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $item = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Milk',
            'unit' => 'L',
            'current_quantity' => 2,
            'minimum_quantity' => 5,
            'unit_cost' => 80,
            'active' => true,
        ]);
        app(NotificationService::class)->notifyLowStock($item);

        return [$owner, $item, Notification::where('user_id', $owner->id)->latest()->firstOrFail()];
    }
}
