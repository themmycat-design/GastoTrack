<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $businessId = $user->business_id;
        
        // Start query
        $query = Transaction::where('business_id', $businessId)
            ->with('user');
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('source')) {
            $source = strtolower($request->source);
            $sourceAliases = match ($source) {
                'bank transfer' => ['bank transfer', 'bank'],
                'credit card' => ['credit card', 'credit_card'],
                default => [$source],
            };
            $query->whereIn(DB::raw('LOWER(source)'), $sourceAliases);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }
        
        // Get transactions with pagination
        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
        $this->addOrderedProductNames($transactions->getCollection(), $businessId);

        if ($request->query('fragment') === 'activity') {
            return view('transactions.partials.activity', compact('transactions'));
        }

        $sourceOptions = ['Cash', 'Maya', 'GCash', 'Bank Transfer', 'Credit Card'];
        $categoryOptions = collect();
        if ($request->filled('source')) {
            $source = strtolower($request->source);
            $sourceAliases = match ($source) {
                'bank transfer' => ['bank transfer', 'bank'],
                'credit card' => ['credit card', 'credit_card'],
                default => [$source],
            };
            $categoryOptions = Transaction::where('business_id', $businessId)
                ->whereIn(DB::raw('LOWER(source)'), $sourceAliases)
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');
        }
        
        // Keep the summary cards business-wide; filters apply to the activity list only.
        $totalIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'income')
            ->sum('amount');
        $totalExpense = Transaction::where('business_id', $businessId)
            ->where('type', 'expense')
            ->sum('amount');
        
        return view('transactions.index', compact('transactions', 'totalIncome', 'totalExpense', 'sourceOptions', 'categoryOptions'));
    }

    public function categories(Request $request)
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:50'],
        ]);

        $sourceAliases = match (strtolower($validated['source'])) {
            'bank transfer' => ['bank transfer', 'bank'],
            'credit card' => ['credit card', 'credit_card'],
            default => [strtolower($validated['source'])],
        };

        $categories = Transaction::where('business_id', $request->user()->business_id)
            ->whereIn(DB::raw('LOWER(source)'), $sourceAliases)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json(['categories' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'transaction_date' => 'required|date',
        ]);
        
        $user = auth()->user();
        
        Transaction::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'transaction_date' => $validated['transaction_date'],
        ]);
        
        return redirect()->route('transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        // Ensure the transaction belongs to the user's business
        if ($transaction->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'transaction_date' => 'required|date',
        ]);
        
        $transaction->update($validated);
        
        return redirect()->route('transactions.index')
            ->with('success', 'Transaction updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        // Ensure the transaction belongs to the user's business
        if ($transaction->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $transaction->delete();
        
        return response()->json(['success' => true]);
    }

    private function addOrderedProductNames($transactions, int $businessId): void
    {
        $orderIds = $transactions
            ->filter(fn ($transaction) => $transaction->entry_method === 'order_system')
            ->map(fn ($transaction) => data_get($transaction->metadata, 'order_id'))
            ->filter()
            ->unique()
            ->values();

        if ($orderIds->isEmpty()) return;

        $orders = Order::where('business_id', $businessId)
            ->whereIn('id', $orderIds)
            ->with('items')
            ->get()
            ->keyBy('id');

        $transactions->each(function ($transaction) use ($orders) {
            if ($transaction->entry_method !== 'order_system') return;
            $order = $orders->get(data_get($transaction->metadata, 'order_id'));
            if (!$order) return;

            $productNames = $order->items
                ->map(fn ($item) => $item->quantity.'× '.$item->product_name)
                ->implode(', ');

            if ($productNames !== '') $transaction->description = $productNames;
        });
    }
}
