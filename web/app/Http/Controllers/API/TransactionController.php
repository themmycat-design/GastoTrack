<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // GET /api/transactions
    public function index(Request $request)
    {
        $query = Transaction::where('business_id', $request->user()->business_id)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('date', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('date', '<=', $request->to);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('category', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $transactions = $query->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    // POST /api/transactions
    public function store(Request $request)
    {
        $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'type'         => 'required|in:income,expense',
            'source'       => 'required|string',
            'category'     => 'required|string',
            'date'         => 'required|date',
            'notes'        => 'nullable|string',
            'entry_method' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'business_id'  => $request->user()->business_id,
            'user_id'      => $request->user()->id,
            'recorded_by'  => $request->user()->id,
            'amount'       => $request->amount,
            'type'         => $request->type,
            'source'       => $request->source,
            'category'     => $request->category,
            'date'         => $request->date,
            'notes'        => $request->notes,
            'entry_method' => $request->entry_method ?? 'manual',
        ]);

        return response()->json([
            'message'     => 'Transaction saved',
            'transaction' => $transaction,
        ], 201);
    }

    // GET /api/transactions/{id}
    public function show(Request $request, $id)
    {
        $transaction = Transaction::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        return response()->json(['transaction' => $transaction]);
    }

    // PUT /api/transactions/{id}
    public function update(Request $request, $id)
    {
        $transaction = Transaction::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $request->validate([
            'amount'   => 'sometimes|numeric|min:0.01',
            'type'     => 'sometimes|in:income,expense',
            'source'   => 'sometimes|string',
            'category' => 'sometimes|string',
            'date'     => 'sometimes|date',
            'notes'    => 'nullable|string',
        ]);

        $transaction->update($request->only([
            'amount', 'type', 'source', 'category', 'date', 'notes'
        ]));

        return response()->json([
            'message'     => 'Transaction updated',
            'transaction' => $transaction,
        ]);
    }

    // DELETE /api/transactions/{id}
    public function destroy(Request $request, $id)
    {
        $transaction = Transaction::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted']);
    }

    // GET /api/transactions-summary
    public function summary(Request $request)
    {
        $businessId = $request->user()->business_id;

        $income = Transaction::where('business_id', $businessId)
            ->where('type', 'income')
            ->sum('amount');

        $expense = Transaction::where('business_id', $businessId)
            ->where('type', 'expense')
            ->sum('amount');

        return response()->json([
            'income'  => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ]);
    }
}