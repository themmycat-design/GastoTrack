<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Goal;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SalesGoals;
use App\Services\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlySalesGoalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-11 12:00:00');
        CarbonImmutable::setTestNow('2026-10-11 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_only_owner_can_set_one_monthly_target_per_business_and_repeated_save_updates_it(): void
    {
        [$business, $owner, $staff] = $this->accounts();

        $this->actingAs($staff)->post(route('goals.store'), [
            'month' => '2026-10', 'target_amount' => 1000,
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('goals.store'), [
            'goal_type' => SalesGoals::MONTHLY, 'month' => '2026-10', 'target_amount' => 1000,
        ])->assertRedirect(route('goals.index'));
        $this->actingAs($owner)->post(route('goals.store'), [
            'goal_type' => SalesGoals::MONTHLY, 'month' => '2026-10', 'target_amount' => 1200,
        ])->assertRedirect(route('goals.index'));

        $this->assertDatabaseCount('goals', 1);
        $this->assertDatabaseHas('goals', [
            'business_id' => $business->id,
            'goal_type' => 'monthly_confirmed_sales',
            'period_start' => '2026-10-01',
            'target_amount' => 1200,
            'current_amount' => 0,
        ]);

        $this->actingAs($owner)->post(route('goals.store'), [
            'goal_type' => SalesGoals::MONTHLY, 'month' => '2026-09', 'target_amount' => 500,
        ])->assertSessionHasErrors('month');
        $this->assertDatabaseCount('goals', 1);
    }

    public function test_owner_can_set_and_update_weekly_targets_and_cannot_change_a_past_week(): void
    {
        [$business, $owner, $staff] = $this->accounts();

        $this->actingAs($staff)->post(route('goals.store'), [
            'goal_type' => SalesGoals::WEEKLY, 'week_date' => '2026-10-11', 'target_amount' => 1000,
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('goals.store'), [
            'goal_type' => SalesGoals::WEEKLY, 'week_date' => '2026-10-11', 'target_amount' => 1000,
        ])->assertRedirect(route('goals.index'));
        $this->actingAs($owner)->post(route('goals.store'), [
            'goal_type' => SalesGoals::WEEKLY, 'week_date' => '2026-10-05', 'target_amount' => 1200,
        ])->assertRedirect(route('goals.index'));

        $this->assertDatabaseCount('goals', 1);
        $this->assertDatabaseHas('goals', [
            'business_id' => $business->id,
            'goal_type' => SalesGoals::WEEKLY,
            'period_start' => '2026-10-05',
            'deadline' => '2026-10-11',
            'target_amount' => 1200,
            'current_amount' => 0,
        ]);

        $this->actingAs($owner)->get(route('goals.index'))
            ->assertOk()
            ->assertViewHas('currentWeekGoal', fn (?array $value) => $value['target_amount'] === '1200.00')
            ->assertSee('Weekly target');

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Weekly sales goal')
            ->assertSee('1,200.00');

        $this->actingAs($owner)->post(route('goals.store'), [
            'goal_type' => SalesGoals::WEEKLY, 'week_date' => '2026-10-04', 'target_amount' => 500,
        ])->assertSessionHasErrors('week_date');
        $this->assertDatabaseCount('goals', 1);
    }

    public function test_weekly_progress_uses_monday_to_sunday_confirmed_sales_in_shop_timezone(): void
    {
        [$business, , $staff] = $this->accounts();
        $goal = Goal::create([
            'business_id' => $business->id,
            'goal_type' => SalesGoals::WEEKLY,
            'period_start' => '2026-10-05',
            'name' => 'Weekly confirmed sales',
            'target_amount' => 190,
        ]);

        $this->order($business, $staff, 100, 'confirmed', ['completed_at' => '2026-10-04 16:00:00']);
        $this->order($business, $staff, 50, 'confirmed', ['completed_at' => '2026-10-11 15:59:59']);
        $pendingRefund = $this->order($business, $staff, 40, 'refund_pending');
        $this->order($business, $staff, 500, 'confirmed', ['completed_at' => '2026-10-04 15:59:59']);
        $this->order($business, $staff, 600, 'confirmed', ['completed_at' => '2026-10-11 16:00:00']);
        $this->order($business, $staff, 700, 'pending');
        $this->order($business, $staff, 800, 'confirmed', ['status' => 'cancelled']);

        $salesGoals = app(SalesGoals::class);
        $this->assertSame(190.0, $salesGoals->recordedSales($goal));
        $this->assertSame('2026-10-11', $salesGoals->present($goal)['period_end']);
        $this->assertTrue($goal->isCompleted());

        $pendingRefund->update(['payment_status' => 'refunded']);
        $this->assertSame(150.0, $salesGoals->recordedSales($goal));
        $this->assertFalse($goal->isCompleted());
    }

    public function test_progress_counts_only_completed_confirmed_committed_sales_and_adjusts_after_refund(): void
    {
        [$business, $owner, $staff] = $this->accounts();
        $goal = Goal::create([
            'business_id' => $business->id,
            'goal_type' => 'monthly_confirmed_sales',
            'period_start' => '2026-10-01',
            'name' => 'October confirmed sales',
            'target_amount' => 150,
        ]);

        $paid = $this->order($business, $staff, 100, 'confirmed');
        // A duplicate sale row cannot inflate an order-based goal.
        Transaction::create([
            'business_id' => $business->id, 'user_id' => $staff->id,
            'order_id' => $paid->id, 'type' => 'income', 'source' => 'cash',
            'category' => 'Sales', 'amount' => 100,
            'transaction_date' => '2026-10-09', 'entry_method' => 'order_system',
        ]);
        $pendingRefund = $this->order($business, $staff, 50, 'refund_pending');
        // Older order sales may retain the link only inside metadata.
        Transaction::where('order_id', $pendingRefund->id)->firstOrFail()->update([
            'order_id' => null,
            'metadata' => ['order_id' => $pendingRefund->id],
        ]);
        $this->order($business, $staff, 70, 'refunded');
        $this->order($business, $staff, 60, 'detected');
        $this->order($business, $staff, 40, 'pending');
        $this->order($business, $staff, 80, 'confirmed', ['status' => 'cancelled']);
        $this->order($business, $staff, 90, 'confirmed', ['financials_committed_at' => null]);
        $this->order($business, $staff, 45, 'confirmed', ['record_sale' => false]);
        $this->order($business, $staff, 25, 'confirmed', ['completed_at' => '2026-09-30 12:00:00']);
        $this->order($business, $staff, 10, 'confirmed', ['completed_at' => '2026-10-31 16:30:00']);
        [$otherBusiness, $otherOwner, $otherStaff] = $this->accounts();
        $this->order($otherBusiness, $otherStaff, 10000, 'confirmed');

        $salesGoals = app(SalesGoals::class);
        $this->assertSame(150.0, $salesGoals->recordedSales($goal));
        $this->assertTrue($salesGoals->present($goal)['target_reached']);
        $this->assertTrue($goal->isCompleted());
        $this->assertSame(100.0, $goal->progress_percentage);
        $this->assertSame('150.00', $salesGoals->present($goal)['current_amount']);

        $this->actingAs($owner)->get(route('goals.index'))
            ->assertOk()
            ->assertViewHas('currentMonthGoal', fn (?array $value) => $value['current_amount'] === '150.00');

        $this->actingAs($owner)->getJson('/api/v1/goals/'.$goal->id)
            ->assertOk()
            ->assertJsonPath('goal.current_amount', '150.00')
            ->assertJsonPath('goal.target_reached', true);

        $this->actingAs($otherStaff)->getJson('/api/v1/goals/'.$goal->id)->assertForbidden();
        $this->actingAs($otherOwner)->getJson('/api/v1/goals/'.$goal->id)->assertNotFound();

        $pendingRefund->update(['payment_status' => 'refunded']);
        $this->assertSame(100.0, $salesGoals->recordedSales($goal));
        $this->assertFalse($salesGoals->present($goal)['target_reached']);
        $this->assertFalse($goal->isCompleted());
        $paid->update(['status' => 'cancelled']);
        $this->assertSame(0.0, $salesGoals->recordedSales($goal));
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'current_amount' => 0]);
    }

    public function test_automatic_sales_targets_cannot_be_edited_completed_or_deleted_through_api(): void
    {
        [$business, $owner] = $this->accounts();
        $goal = Goal::create([
            'business_id' => $business->id,
            'goal_type' => 'monthly_confirmed_sales',
            'period_start' => '2026-10-01',
            'name' => 'October confirmed sales',
            'target_amount' => 1000,
        ]);

        $this->actingAs($owner)->putJson('/api/v1/goals/'.$goal->id, [
            'target_amount' => 1000,
            'current_amount' => 1000,
        ])->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/v1/goals/'.$goal->id.'/complete')->assertStatus(409);
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'current_amount' => 0, 'status' => 'active']);

        $weeklyGoal = Goal::create([
            'business_id' => $business->id,
            'goal_type' => SalesGoals::WEEKLY,
            'period_start' => '2026-10-05',
            'name' => 'Weekly confirmed sales',
            'target_amount' => 800,
        ]);
        $this->actingAs($owner)->putJson('/api/v1/goals/'.$weeklyGoal->id, [
            'target_amount' => 800,
            'current_amount' => 800,
        ])->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/v1/goals/'.$weeklyGoal->id.'/complete')->assertStatus(409);
        $this->actingAs($owner)->deleteJson('/api/v1/goals/'.$weeklyGoal->id)->assertStatus(409);
        $this->assertDatabaseHas('goals', ['id' => $weeklyGoal->id, 'current_amount' => 0, 'status' => 'active']);
    }

    public function test_existing_manual_goals_remain_separate_and_can_still_be_completed(): void
    {
        [$business, $owner] = $this->accounts();
        $manual = Goal::create([
            'business_id' => $business->id,
            'name' => 'Equipment savings',
            'target_amount' => 500,
            'current_amount' => 100,
        ]);

        $this->actingAs($owner)->postJson('/api/v1/goals/'.$manual->id.'/complete')
            ->assertOk()
            ->assertJsonPath('goal.current_amount', '500.00');
        $this->assertNotNull($manual->fresh()->completed_at);
    }

    public function test_reached_monthly_goal_does_not_send_an_unnecessary_deadline_alert(): void
    {
        Carbon::setTestNow('2026-10-29 12:00:00');
        CarbonImmutable::setTestNow('2026-10-29 12:00:00');
        [$business, , $staff] = $this->accounts();
        $goal = Goal::create([
            'business_id' => $business->id,
            'goal_type' => 'monthly_confirmed_sales',
            'period_start' => '2026-10-01',
            'name' => 'October sales',
            'target_amount' => 50,
            'deadline' => '2026-10-31',
        ]);
        $this->order($business, $staff, 100, 'confirmed');

        app(NotificationService::class)->notifyGoalDeadlines();
        $this->assertDatabaseMissing('notifications', ['business_id' => $business->id, 'type' => 'goal_deadline']);
        $this->assertTrue($goal->isCompleted());
    }

    private function accounts(): array
    {
        $business = Business::create(['name' => 'Goal Shop '.uniqid(), 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);

        return [$business, $owner, $staff];
    }

    private function order(Business $business, User $staff, float $total, string $paymentStatus, array $overrides = []): Order
    {
        $recordSale = $overrides['record_sale'] ?? true;
        unset($overrides['record_sale']);
        $order = Order::create(array_merge([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => 'cash',
            'status' => 'completed',
            'payment_status' => $paymentStatus,
            'payment_confirmed_at' => '2026-10-09 08:00:00',
            'financials_committed_at' => '2026-10-09 08:00:00',
            'completed_at' => '2026-10-09 08:00:00',
        ], $overrides));

        if ($recordSale) {
            Transaction::create([
                'business_id' => $business->id,
                'user_id' => $staff->id,
                'order_id' => $order->id,
                'type' => 'income',
                'source' => 'cash',
                'category' => 'Sales',
                'amount' => $total,
                'transaction_date' => '2026-10-09',
                'entry_method' => 'order_system',
            ]);
        }

        return $order;
    }
}
