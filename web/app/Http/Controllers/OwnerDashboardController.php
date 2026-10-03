<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        $businessId = $user->business_id;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $income = Transaction::where('business_id', $businessId)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $expenses = Transaction::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $lowStockItems = StockItem::where('business_id', $businessId)
            ->where('active', true)
            ->whereColumn('current_quantity', '<=', 'minimum_quantity')
            ->orderBy('current_quantity')
            ->take(6)
            ->get();

        return view('dashboard', [
            'business' => Business::find($businessId),
            'monthLabel' => now()->format('F Y'),
            'totalRevenue' => $income,
            'totalExpenses' => $expenses,
            'netProfit' => $income - $expenses,
            'totalProducts' => Product::where('business_id', $businessId)->where('active', true)->count(),
            'totalStaff' => User::where('business_id', $businessId)->where('role', 'staff')->count(),
            'totalStockValue' => StockItem::where('business_id', $businessId)
                ->where('active', true)
                ->selectRaw('COALESCE(SUM(current_quantity * unit_cost), 0) as total')
                ->value('total'),
            'lowStockCount' => StockItem::where('business_id', $businessId)
                ->where('active', true)
                ->whereColumn('current_quantity', '<=', 'minimum_quantity')
                ->count(),
            'recentTransactions' => Transaction::where('business_id', $businessId)
                ->orderByDesc('created_at')
                ->take(6)
                ->get(),
            'lowStockItems' => $lowStockItems,
        ]);
    }
}
