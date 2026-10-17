<?php
namespace App\Services;

use App\Models\Goal;
use App\Models\Notification;
use App\Models\StockItem;
use App\Models\User;
use Carbon\CarbonImmutable;

class NotificationService
{
    public function notifyLowStock(StockItem $item): void
    {
        User::where('business_id', $item->business_id)
            ->where('role', 'owner')
            ->each(function (User $user) use ($item): void {
                $notifications = Notification::query()
                    ->where('user_id', $user->id)
                    ->where('business_id', $item->business_id)
                    ->where('type', 'low_stock')
                    ->where('data->stock_item_id', $item->id);

                if (!$item->trashed() && $item->active && $item->isLowStock()) {
                    $notification = (clone $notifications)
                        ->whereNull('data->resolved_at')
                        ->latest('id')
                        ->first();

                    if ($notification) {
                        if ($notification->read_at === null) {
                            $notification->update([
                                'title' => (float) $item->current_quantity <= 0 ? 'Out of stock' : 'Low stock',
                                'message' => "{$item->name} is at {$item->current_quantity} {$item->unit}.",
                            ]);
                        }

                        return;
                    }

                    Notification::create([
                        'user_id' => $user->id,
                        'business_id' => $item->business_id,
                        'type' => 'low_stock',
                        'title' => (float) $item->current_quantity <= 0 ? 'Out of stock' : 'Low stock',
                        'message' => "{$item->name} is at {$item->current_quantity} {$item->unit}.",
                        'data' => ['stock_item_id' => $item->id],
                    ]);

                    return;
                }

                $notifications->whereNull('data->resolved_at')->get()->each(function (Notification $notification): void {
                    $notification->update([
                        'read_at' => $notification->read_at ?? now(),
                        'data' => array_merge($notification->data ?? [], ['resolved_at' => now()->toIso8601String()]),
                    ]);
                });
            });
    }

    public function ensureCurrentLowStockNotifications(User $user): void
    {
        if (!$user->business_id) {
            return;
        }

        StockItem::query()
            ->where('business_id', $user->business_id)
            ->where('active', true)
            ->whereColumn('current_quantity', '<=', 'minimum_quantity')
            ->each(fn (StockItem $item) => $this->notifyLowStock($item));
    }

    public function notifyGoalDeadlines(): void
    {
        $today = CarbonImmutable::now(config('business.timezone'))->startOfDay();
        Goal::where('status', 'active')
            ->whereBetween('deadline', [$today->toDateString(), $today->addDays(3)->toDateString()])
            ->each(function (Goal $goal) {
                if ($goal->isCompleted()) {
                    return;
                }
                User::where('business_id', $goal->business_id)->where('role', 'owner')->each(function (User $user) use ($goal) {
                    $exists = Notification::where('user_id', $user->id)->where('type', 'goal_deadline')->whereNull('read_at')->where('data->goal_id', $goal->id)->exists();
                    if (!$exists) Notification::create(['user_id' => $user->id, 'business_id' => $goal->business_id, 'type' => 'goal_deadline', 'title' => 'Goal deadline approaching', 'message' => "{$goal->name} is due {$goal->deadline->format('M d, Y')}.", 'data' => ['goal_id' => $goal->id]]);
                });
            });
    }
}
