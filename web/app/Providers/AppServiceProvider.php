<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
