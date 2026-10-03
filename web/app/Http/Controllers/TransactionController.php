<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

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
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
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
        
        // Calculate totals for filtered results
        $totalIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'income')
            ->when($request->filled('date_from'), function($q) use ($request) {
                $q->whereDate('transaction_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function($q) use ($request) {
                $q->whereDate('transaction_date', '<=', $request->date_to);
            })
            ->sum('amount');
            
        $totalExpense = Transaction::where('business_id', $businessId)
            ->where('type', 'expense')
            ->when($request->filled('date_from'), function($q) use ($request) {
                $q->whereDate('transaction_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function($q) use ($request) {
                $q->whereDate('transaction_date', '<=', $request->date_to);
            })
            ->sum('amount');
        
        return view('transactions.index', compact('transactions', 'totalIncome', 'totalExpense'));
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
}
