<?php
namespace App\Services;

use App\Models\Goal;
use App\Models\BugReport;
use App\Models\Notification;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\User;
use App\Models\WalletNotificationCapture;
use Carbon\CarbonImmutable;

class NotificationService
{
    public function notifyWalletCapture(WalletNotificationCapture $capture): void
    {
        if ($capture->direction !== 'received' || $capture->used_order_id !== null) {
            return;
        }

        $source = $this->walletSourceLabel($capture->source);
        $captureId = (int) $capture->id;

        $this->notifyBusinessOwners(
            (int) $capture->business_id,
            'wallet_payment_review',
            "{$source} payment needs review",
            "A {$source} receipt signal was captured by staff. Review it in Raw transactions; it is not independently verified and has not changed sales totals.",
            ['capture_id' => $captureId],
            $captureId,
        );
    }

    public function resolveWalletCaptureNotification(WalletNotificationCapture $capture, Order $order): void
    {
        $source = $this->walletSourceLabel($capture->source);

        Notification::query()
            ->where('business_id', $capture->business_id)
            ->where('type', 'wallet_payment_review')
            ->where('data->capture_id', $capture->id)
            ->whereNull('read_at')
            ->get()
            ->each(function (Notification $notification) use ($source, $order): void {
                $notification->update([
                    'title' => 'Wallet signal matched to order',
                    'message' => "The {$source} receipt signal was matched to order {$order->order_number}. GastoTrack did not verify it with the wallet provider.",
                    'read_at' => now(),
                    'data' => array_merge($notification->data ?? [], [
                        'order_id' => $order->id,
                        'resolved_at' => now()->toIso8601String(),
                    ]),
                ]);
            });
    }

    public function notifyPaymentNeedsReview(Order $order): void
    {
        $this->notifyOrderOwners(
            $order,
            'order_payment_review',
            'Payment needs review',
            "The wallet signal does not match order {$order->order_number}. Verify the wallet and order before treating it as paid; no sale was recorded.",
        );
    }

    public function notifyPaymentFailed(Order $order): void
    {
        $this->notifyOrderOwners(
            $order,
            'order_payment_failed',
            'Payment attempt failed',
            "Payment for order {$order->order_number} was marked as not received. Staff may retry or cancel it; no sale was recorded.",
        );
    }

    public function notifyOrderCancelled(Order $order): void
    {
        $this->notifyOrderOwners(
            $order,
            'order_cancelled',
            'Order cancelled',
            "Order {$order->order_number} was cancelled before payment. No sale was recorded.",
        );
    }

    public function notifyRefundRequested(Order $order): void
    {
        $this->notifyOrderOwners(
            $order,
            'order_refund_pending',
            'Refund needs review',
            "A refund was requested for order {$order->order_number}. Verify the external wallet refund before staff records the reversal.",
        );
    }

    public function resolveOrderIssueNotifications(Order $order, string $resolution): void
    {
        [$title, $message] = match ($resolution) {
            'confirmed' => [
                'Payment confirmed',
                "Payment for order {$order->order_number} was confirmed by staff after checking the wallet. GastoTrack did not verify it with the provider.",
            ],
            'cancelled' => [
                'Payment issue closed',
                "Order {$order->order_number} was cancelled before payment; no sale was recorded.",
            ],
            'refunded' => [
                'Refund recorded',
                "Staff recorded an external refund for order {$order->order_number}. GastoTrack did not initiate the provider refund.",
            ],
            default => throw new \InvalidArgumentException('Unsupported order notification resolution.'),
        };

        Notification::query()
            ->where('business_id', $order->business_id)
            ->whereIn('type', ['order_payment_review', 'order_payment_failed', 'order_refund_pending'])
            ->where('data->order_id', $order->id)
            ->whereNull('read_at')
            ->get()
            ->each(function (Notification $notification) use ($title, $message, $resolution): void {
                $notification->update([
                    'title' => $title,
                    'message' => $message,
                    'read_at' => now(),
                    'data' => array_merge($notification->data ?? [], [
                        'resolved_as' => $resolution,
                        'resolved_at' => now()->toIso8601String(),
                    ]),
                ]);
            });
    }

    public function notifyBugReportUpdate(BugReport $report, bool $statusChanged): void
    {
        $status = str_replace('_', ' ', (string) $report->status);
        $message = $statusChanged
            ? "Super Admin updated bug report \"{$report->subject}\". Its status is now {$status}."
            : "Super Admin posted a public update to bug report \"{$report->subject}\".";

        $this->notifyBusinessOwners(
            (int) $report->business_id,
            'bug_report_update',
            'Bug report updated',
            $message,
            ['bug_report_id' => $report->id, 'status' => $report->status],
        );
    }

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
                    $exists = Notification::where('user_id', $user->id)
                        ->where('business_id', $goal->business_id)
                        ->where('type', 'goal_deadline')
                        ->where('data->goal_id', $goal->id)
                        ->where(fn ($query) => $query->whereNull('read_at')->orWhereNotNull('data->dismissed_at'))
                        ->exists();
                    if (!$exists) Notification::create(['user_id' => $user->id, 'business_id' => $goal->business_id, 'type' => 'goal_deadline', 'title' => 'Goal deadline approaching', 'message' => "{$goal->name} is due {$goal->deadline->format('M d, Y')}.", 'data' => ['goal_id' => $goal->id]]);
                });
            });
    }

    private function notifyOrderOwners(Order $order, string $type, string $title, string $message): void
    {
        $businessId = (int) $order->business_id;
        $orderId = (int) $order->id;

        $this->notifyBusinessOwners(
            $businessId,
            $type,
            $title,
            $message,
            ['order_id' => $orderId],
            $orderId,
        );
    }

    private function walletSourceLabel(string $source): string
    {
        return match (strtolower($source)) {
            'gcash' => 'GCash',
            'maya' => 'Maya',
            default => ucfirst($source),
        };
    }

    private function notifyBusinessOwners(
        int $businessId,
        string $type,
        string $title,
        string $message,
        array $data,
        ?int $dedupeId = null,
    ): void {
        User::query()
            ->where('business_id', $businessId)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->each(function (User $owner) use ($businessId, $type, $title, $message, $data, $dedupeId): void {
                $existing = Notification::query()
                    ->where('user_id', $owner->id)
                    ->where('business_id', $businessId)
                    ->where('type', $type);

                if ($dedupeId !== null) {
                    $key = $type === 'wallet_payment_review' ? 'capture_id' : 'order_id';
                    $existing->where("data->{$key}", $dedupeId);
                }

                if ($dedupeId !== null && ($priorNotification = $existing->latest('id')->first())) {
                    if ($type !== 'wallet_payment_review' && $priorNotification->read_at !== null) {
                        $priorNotification->update([
                            'title' => $title,
                            'message' => $message,
                            'data' => $data,
                            'read_at' => null,
                            'created_at' => now(),
                        ]);
                    }

                    return;
                }

                Notification::create([
                    'user_id' => $owner->id,
                    'business_id' => $businessId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                ]);
            });
    }
}
