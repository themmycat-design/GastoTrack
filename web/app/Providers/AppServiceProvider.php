<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\OwnerStockAlerts;
use App\Services\NotificationService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config(['fortify.features' => [
            Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => false]),
        ]]);
        Fortify::twoFactorChallengeView('auth.two-factor-challenge');

        View::composer(['layouts.owner-navigation', 'layouts.owner-topbar'], function (BladeView $view): void {
            $owner = auth()->user();

            if ($owner?->isOwner() && $owner->business_id) {
                $cacheKey = 'owner_stock_alerts_'.$owner->business_id;
                $request = request();
                $alerts = $request->attributes->get($cacheKey);

                if ($alerts === null) {
                    $alerts = app(OwnerStockAlerts::class)->forBusiness((int) $owner->business_id);
                    $request->attributes->set($cacheKey, $alerts);
                }

                $view->with('ownerStockAlerts', $alerts);

                $notificationCacheKey = 'owner_notifications_'.$owner->id;
                $notifications = $request->attributes->get($notificationCacheKey);
                if ($notifications === null) {
                    app(NotificationService::class)->ensureCurrentLowStockNotifications($owner);

                    $unread = Notification::query()
                        ->where('user_id', $owner->id)
                        ->where('business_id', $owner->business_id)
                        ->whereNull('read_at');
                    $notifications = [
                        'count' => (clone $unread)->count(),
                        'items' => Notification::query()
                            ->where('user_id', $owner->id)
                            ->where('business_id', $owner->business_id)
                            ->latest('id')
                            ->limit(8)
                            ->get(),
                    ];
                    $request->attributes->set($notificationCacheKey, $notifications);
                }

                $view->with('ownerUnreadNotificationCount', $notifications['count']);
                $view->with('ownerUnreadNotifications', $notifications['items']);
            }
        });

        foreach ([Transaction::class, Product::class, Order::class] as $model) {
            $model::created(fn ($record) => $this->logActivity('created', $record));
            $model::updated(fn ($record) => $this->logActivity('updated', $record));
            $model::deleted(fn ($record) => $this->logActivity('deleted', $record));
        }
    }

    private function logActivity(string $action, object $record): void
    {
        ActivityLog::create([
            'business_id' => $record->business_id ?? auth()->user()?->business_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $record::class,
            'subject_id' => $record->id,
            'old_values' => $action === 'updated' ? $record->getOriginal() : null,
            'new_values' => $action === 'deleted' ? null : $record->getAttributes(),
            'ip_address' => request()?->ip(),
        ]);
    }
}
