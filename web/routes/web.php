<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\OwnerGoalController;
use App\Http\Controllers\OwnerAnalyticsController;
use App\Http\Controllers\OwnerAnalyticsDayController;
use App\Http\Controllers\OwnerCostLogController;
use App\Http\Controllers\OwnerReportsController;
use App\Http\Controllers\OwnerOrderController;
use App\Http\Controllers\OwnerBugReportController;
use App\Http\Controllers\OwnerNotificationController;
use App\Http\Controllers\OwnerProductEditRequestController;
use App\Http\Controllers\OwnerLiveStateController;
use App\Http\Controllers\PlatformReportController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\BugReportController;
use App\Http\Controllers\PlatformUserController;
use App\Http\Controllers\AdminActivityLogController;
use App\Http\Controllers\BusinessSubscriptionController;
use App\Http\Controllers\OwnerSubscriptionRequestController;
use App\Http\Controllers\PlatformSubscriptionRequestController;
use App\Http\Controllers\BusinessBulkActionController;
use App\Http\Controllers\PublicContactController;
use App\Models\BugReport;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/contact', 'public.contact')->name('contact');
Route::post('/contact', PublicContactController::class)->middleware('throttle:3,1')->name('contact.send');
Route::view('/terms', 'public.terms')->name('terms');
Route::view('/privacy', 'public.privacy')->name('privacy');
Route::view('/download-app', 'public.download-app')->name('download-app');

Route::get('/dashboard', OwnerDashboardController::class)
    ->middleware(['auth', 'verified', 'owner', 'owner.security'])
    ->name('dashboard');

Route::get('/business-status', function () {
    abort_unless(auth()->user()?->isOwner(), 403);

    $business = auth()->user()->business;

    if (auth()->user()->status === 'active' && $business?->status === 'active' && $business->active) {
        return redirect()->route('dashboard');
    }

    return view('auth.business-status', compact('business'));
})->middleware(['auth', 'verified'])->name('business.status');

// Renewal requests stay available even when the current subscription has expired.
Route::middleware(['auth', 'verified', 'owner', 'owner.security'])->prefix('subscription')->name('owner.subscriptions.')->group(function () {
    Route::get('/', [OwnerSubscriptionRequestController::class, 'index'])->name('index');
    Route::post('/', [OwnerSubscriptionRequestController::class, 'store'])->name('store');
    Route::get('/requests/{subscriptionRequest}/proof', [OwnerSubscriptionRequestController::class, 'proof'])->name('proof');
});

// Owner Dashboard routes
Route::middleware(['auth', 'verified', 'owner', 'owner.security', 'owner.subscription.write'])->group(function () {
    Route::get('/owner/live-state', OwnerLiveStateController::class)->name('owner.live-state');
    Route::post('/notifications/read-all', [OwnerNotificationController::class, 'readAll'])
        ->name('owner.notifications.read-all');
    Route::delete('/notifications/{notification}', [OwnerNotificationController::class, 'destroy'])
        ->whereNumber('notification')
        ->name('owner.notifications.destroy');

    Route::get('/goals', [OwnerGoalController::class, 'index'])->name('goals.index');
    Route::post('/goals', [OwnerGoalController::class, 'store'])->name('goals.store');
    // Transactions (Read-only for owners)
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/categories', [TransactionController::class, 'categories'])->name('transactions.categories');
    
    // Stock CRUD
    Route::get('/stock/report', [StockController::class, 'export'])->name('stock.export');
    Route::resource('stock', StockController::class)->except(['show', 'create', 'edit']);
    Route::post('/stock/{stock}/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    
    // Staff CRUD
    Route::resource('staff', StaffController::class)->except(['show', 'create', 'edit']);
    
    // Product management existed in Blade/controller code but was not routed.
    Route::resource('products', ProductController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('/products/edit-requests/{editRequest}/approve', [OwnerProductEditRequestController::class, 'approve'])
        ->name('products.edit-requests.approve');
    Route::post('/products/edit-requests/{editRequest}/reject', [OwnerProductEditRequestController::class, 'reject'])
        ->name('products.edit-requests.reject');
    Route::post('/products/{product}/toggle', [ProductController::class, 'toggleAvailability'])
        ->name('products.toggle');

    // Read-only owner order details; financial/order actions remain in the staff workflow.
    Route::get('/orders', [OwnerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OwnerOrderController::class, 'show'])->name('orders.show');
    Route::get('/analytics', OwnerAnalyticsController::class)->name('analytics.index');
    Route::get('/analytics/cost-logs', OwnerCostLogController::class)->name('analytics.cost-logs');
    Route::get('/reports', [OwnerReportsController::class, 'index'])->name('owner.reports.index');
    Route::get('/reports/export', [OwnerReportsController::class, 'export'])->name('owner.reports.export');
    Route::get('/reports/{section}', [OwnerReportsController::class, 'show'])
        ->where('section', 'sales-orders|income-expenses|product-costs|inventory-waste|payment-review|staff-activity')
        ->name('owner.reports.show');
    Route::get('/analytics/day/{date}', OwnerAnalyticsDayController::class)
        ->where('date', '\\d{4}-\\d{2}-\\d{2}')
        ->name('analytics.day');
    Route::prefix('bug-reports')->name('owner.bug-reports.')->controller(OwnerBugReportController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{bugReport}/screenshot', 'screenshot')->name('screenshot');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->middleware(['verified', 'owner.security'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware(['verified', 'owner.security', 'owner.subscription.write'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware(['verified', 'owner.security', 'owner.subscription.write'])->name('profile.destroy');
});

// Super Admin routes
Route::middleware(['auth', 'super_admin', 'platform.account.active'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
    Route::prefix('subscription-requests')->name('subscription-requests.')->controller(PlatformSubscriptionRequestController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{subscriptionRequest}', 'show')->name('show');
        Route::get('/{subscriptionRequest}/proof', 'proof')->name('proof');
        Route::post('/{subscriptionRequest}/accept', 'accept')->name('accept');
        Route::delete('/{subscriptionRequest}', 'destroy')->name('destroy');
    });
    Route::get('/users', [PlatformUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [PlatformUserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/password-reset', [PlatformUserController::class, 'sendPasswordReset'])->name('users.password-reset');
    Route::post('/users/{user}/lock', [PlatformUserController::class, 'lock'])->name('users.lock');
    Route::post('/users/{user}/unlock', [PlatformUserController::class, 'unlock'])->name('users.unlock');
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::post('/businesses/bulk', BusinessBulkActionController::class)->name('businesses.bulk');
    Route::get('/businesses', [SuperAdminController::class, 'businesses'])->name('businesses');
    Route::get('/businesses/create', [SuperAdminController::class, 'create'])->name('businesses.create');
    Route::post('/businesses', [SuperAdminController::class, 'store'])->name('businesses.store');
    Route::post('/businesses/{business}/restore', [SuperAdminController::class, 'restore'])->name('businesses.restore');
    Route::delete('/businesses/{business}/permanent', [SuperAdminController::class, 'forceDestroy'])->name('businesses.force-destroy');
    Route::delete('/businesses/{business}/pending-registration', [SuperAdminController::class, 'deletePendingRegistration'])->name('businesses.pending-registration.delete');
    Route::get('/businesses/{business}', [SuperAdminController::class, 'show'])->name('businesses.show');
    Route::put('/businesses/{business}/subscription', [BusinessSubscriptionController::class, 'update'])->name('businesses.subscription.update');
    Route::post('/businesses/{business}/subscription/extend', [BusinessSubscriptionController::class, 'extend'])->name('businesses.subscription.extend');
    Route::get('/businesses/{business}/edit', [SuperAdminController::class, 'edit'])->name('businesses.edit');
    Route::put('/businesses/{business}', [SuperAdminController::class, 'update'])->name('businesses.update');
    Route::delete('/businesses/{business}', [SuperAdminController::class, 'destroy'])->name('businesses.destroy');
    Route::post('/businesses/{business}/approve', [SuperAdminController::class, 'approve'])->name('businesses.approve');
    Route::post('/businesses/{business}/suspend', [SuperAdminController::class, 'suspend'])->name('businesses.suspend');
    Route::post('/businesses/{business}/reactivate', [SuperAdminController::class, 'reactivate'])->name('businesses.reactivate');
    Route::post('/businesses/{business}/deactivate', [SuperAdminController::class, 'deactivate'])->name('businesses.deactivate');
    Route::post('/businesses/{business}/owner/activate', [SuperAdminController::class, 'activateOwner'])->name('businesses.owner.activate');
    Route::post('/businesses/{business}/owner/deactivate', [SuperAdminController::class, 'deactivateOwner'])->name('businesses.owner.deactivate');
    Route::post('/businesses/{business}/owner/password-reset', [SuperAdminController::class, 'sendOwnerPasswordReset'])->name('businesses.owner.password-reset');

    Route::get('/bug-reports/{bugReport}/screenshot', [BugReportController::class, 'screenshot'])->name('bug-reports.screenshot');
    Route::resource('bug-reports', BugReportController::class)->except(['create', 'store']);
    Route::get('/violations', fn () => redirect()->route('super-admin.bug-reports.index'));
    Route::get('/violations/create', fn () => redirect()->route('super-admin.bug-reports.index'));
    Route::get('/violations/{violation}/edit', fn (int $violation) => redirect()->route('super-admin.bug-reports.edit', $violation))->whereNumber('violation');
    Route::get('/violations/{violation}', fn (BugReport $violation) => redirect()->route('super-admin.bug-reports.show', $violation))->whereNumber('violation');
    Route::get('/reports', [PlatformReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [PlatformReportController::class, 'export'])->name('reports.export');
});

require __DIR__.'/auth.php';
