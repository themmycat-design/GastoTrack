<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PlatformReportController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\ViolationReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', OwnerDashboardController::class)
    ->middleware(['auth', 'verified', 'owner'])
    ->name('dashboard');

Route::get('/business-status', function () {
    abort_unless(auth()->user()?->isOwner(), 403);

    $business = auth()->user()->business;

    if (auth()->user()->status === 'active' && $business?->status === 'active' && $business->active) {
        return redirect()->route('dashboard');
    }

    return view('auth.business-status', compact('business'));
})->middleware('auth')->name('business.status');

// Owner Dashboard routes
Route::middleware(['auth', 'owner'])->group(function () {
    // Transactions (Read-only for owners)
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    
    // Stock CRUD
    Route::resource('stock', StockController::class)->except(['show', 'create', 'edit']);
    Route::post('/stock/{stock}/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    
    // Staff CRUD
    Route::resource('staff', StaffController::class)->except(['show', 'create', 'edit']);
    
    Route::get('/analytics', function() {
        $user = auth()->user();
        $businessId = $user->business_id;
        
        // Get date ranges
        $today = now();
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->subMonth()->endOfMonth();
        
        // Current month totals
        $currentMonthRevenue = \App\Models\Transaction::where('business_id', $businessId)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        $currentMonthExpenses = \App\Models\Transaction::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        
        // Last month totals
        $lastMonthRevenue = \App\Models\Transaction::where('business_id', $businessId)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');
            
        $lastMonthExpenses = \App\Models\Transaction::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');
        
        // Calculate growth percentages
        $revenueGrowth = $lastMonthRevenue > 0 
            ? (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;
        $expenseGrowth = $lastMonthExpenses > 0 
            ? (($currentMonthExpenses - $lastMonthExpenses) / $lastMonthExpenses) * 100 
            : 0;
        
        // Get daily revenue for current month (for chart)
        $dailyRevenue = \App\Models\Transaction::where('business_id', $businessId)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(transaction_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Get daily expenses for current month (for chart)
        $dailyExpenses = \App\Models\Transaction::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(transaction_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Get top stock items by value
        $topStockItems = \App\Models\StockItem::where('business_id', $businessId)
            ->where('active', true)
            ->orderByRaw('current_quantity * unit_cost DESC')
            ->take(5)
            ->get();
        
        // Get transaction count
        $transactionCount = \App\Models\Transaction::where('business_id', $businessId)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->count();
        
        $lastMonthTransactionCount = \App\Models\Transaction::where('business_id', $businessId)
            ->whereBetween('transaction_date', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        
        $transactionGrowth = $lastMonthTransactionCount > 0 
            ? (($transactionCount - $lastMonthTransactionCount) / $lastMonthTransactionCount) * 100 
            : 0;
        
        return view('analytics.index', compact(
            'currentMonthRevenue',
            'currentMonthExpenses',
            'lastMonthRevenue',
            'lastMonthExpenses',
            'revenueGrowth',
            'expenseGrowth',
            'dailyRevenue',
            'dailyExpenses',
            'topStockItems',
            'transactionCount',
            'transactionGrowth'
        ));
    })->name('analytics.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Super Admin routes
Route::middleware(['auth', 'super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/businesses', [SuperAdminController::class, 'businesses'])->name('businesses');
    Route::get('/businesses/create', [SuperAdminController::class, 'create'])->name('businesses.create');
    Route::post('/businesses', [SuperAdminController::class, 'store'])->name('businesses.store');
    Route::post('/businesses/{business}/restore', [SuperAdminController::class, 'restore'])->name('businesses.restore');
    Route::get('/businesses/{business}', [SuperAdminController::class, 'show'])->name('businesses.show');
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

    Route::resource('violations', ViolationReportController::class);
    Route::get('/reports', [PlatformReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [PlatformReportController::class, 'export'])->name('reports.export');
});

require __DIR__.'/auth.php';
