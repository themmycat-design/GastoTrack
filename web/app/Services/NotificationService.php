<?php
namespace App\Services;

use App\Models\Goal;
use App\Models\Notification;
use App\Models\StockItem;
use App\Models\User;

class NotificationService
{
    public function notifyLowStock(StockItem $item): void
    {
        if (!$item->isLowStock()) return;
        User::where('business_id', $item->business_id)->where('role', 'owner')->each(function (User $user) use ($item) {
            $exists = Notification::where('user_id', $user->id)->where('type', 'low_stock')->whereNull('read_at')->where('data->stock_item_id', $item->id)->exists();
            if (!$exists) Notification::create(['user_id' => $user->id, 'business_id' => $item->business_id, 'type' => 'low_stock', 'title' => 'Low stock', 'message' => "{$item->name} is at {$item->current_quantity} {$item->unit}.", 'data' => ['stock_item_id' => $item->id]]);
        });
    }

    public function notifyGoalDeadlines(): void
    {
        Goal::where('status', 'active')->whereBetween('deadline', [today(), today()->addDays(3)])->each(function (Goal $goal) {
            User::where('business_id', $goal->business_id)->where('role', 'owner')->each(function (User $user) use ($goal) {
                $exists = Notification::where('user_id', $user->id)->where('type', 'goal_deadline')->whereNull('read_at')->where('data->goal_id', $goal->id)->exists();
                if (!$exists) Notification::create(['user_id' => $user->id, 'business_id' => $goal->business_id, 'type' => 'goal_deadline', 'title' => 'Goal deadline approaching', 'message' => "{$goal->name} is due {$goal->deadline->format('M d, Y')}.", 'data' => ['goal_id' => $goal->id]]);
            });
        });
    }
}
