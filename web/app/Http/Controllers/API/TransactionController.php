<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    // GET /api/transactions
    public function index(Request $request)
    {
        $query = Transaction::where('business_id', $request->user()->business_id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('transaction_date', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('transaction_date', '<=', $request->to);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('category', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $transactions = $query->paginate(min($request->integer('per_page', 20), 100));

        return response()->json([
            'transactions' => $transactions->items(),
            'meta' => ['current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(), 'total' => $transactions->total()],
        ]);
    }

    // POST /api/transactions
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'type'         => 'required|in:income,expense',
            'source'       => 'required|string',
            'category'     => 'required|string',
            'date'         => 'required|date|before_or_equal:today',
            'notes'        => 'nullable|string',
            'entry_method' => ['nullable', Rule::in(['manual', 'ocr', 'notification_capture'])],
            'metadata' => 'nullable|array',
            'metadata.ocr_confidence' => 'nullable|numeric|min:0|max:100',
            'metadata.ocr_provider' => 'nullable|string|max:100',
        ]);

        $transaction = Transaction::create([
            'business_id'  => $request->user()->business_id,
            'user_id'      => $request->user()->id,
            'amount'       => $validated['amount'],
            'type'         => $validated['type'],
            'source'       => $validated['source'],
            'category'     => $validated['category'],
            'transaction_date' => $validated['date'],
            'description'  => $validated['notes'] ?? null,
            'entry_method' => $validated['entry_method'] ?? 'manual',
            'metadata'     => $validated['metadata'] ?? null,
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

        $this->ensureMutable($transaction);
        abort_if(
            $request->user()->isStaff() && $transaction->user_id !== $request->user()->id,
            403,
            'Staff can only edit transactions they recorded.'
        );

        $request->validate([
            'amount'   => 'sometimes|numeric|min:0.01',
            'type'     => 'sometimes|in:income,expense',
            'source'   => 'sometimes|string',
            'category' => 'sometimes|string',
            'date'     => 'sometimes|date|before_or_equal:today',
            'notes'    => 'nullable|string',
        ]);

        $data = $request->only(['amount', 'type', 'source', 'category']);
        if ($request->has('date')) $data['transaction_date'] = $request->date;
        if ($request->has('notes')) $data['description'] = $request->notes;
        $transaction->update($data);

        return response()->json([
            'message'     => 'Transaction updated',
            'transaction' => $transaction,
        ]);
    }

    // DELETE /api/transactions/{id}
    public function destroy(Request $request, $id)
    {
        abort_unless($request->user()->isOwner(), 403, 'Only owners can delete transactions.');
        $transaction = Transaction::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $this->ensureMutable($transaction);

        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted']);
    }

    // GET /api/transactions-summary
    public function summary(Request $request)
    {
        $businessId = $request->user()->business_id;

        $base = Transaction::where('business_id', $businessId);
        if ($request->filled('from')) $base->whereDate('transaction_date', '>=', $request->from);
        if ($request->filled('to')) $base->whereDate('transaction_date', '<=', $request->to);

        $income = (clone $base)
            ->where('type', 'income')
            ->sum('amount');

        $expense = (clone $base)
            ->where('type', 'expense')
            ->sum('amount');

        return response()->json([
            'income'  => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ]);
    }

    public function batch(Request $request)
    {
        $data = $request->validate([
            'transactions' => 'required|array|min:1|max:100',
            'transactions.*.amount' => 'required|numeric|min:0.01',
            'transactions.*.type' => 'required|in:income,expense',
            'transactions.*.source' => 'nullable|string|max:255',
            'transactions.*.category' => 'required|string|max:255',
            'transactions.*.date' => 'required|date|before_or_equal:today',
            'transactions.*.notes' => 'nullable|string',
            'transactions.*.client_id' => 'nullable|uuid',
        ]);
        $created = DB::transaction(fn () => collect($data['transactions'])->map(function ($item) use ($request) {
            $values = ['user_id' => $request->user()->id, 'amount' => $item['amount'], 'type' => $item['type'], 'source' => $item['source'] ?? null, 'category' => $item['category'], 'transaction_date' => $item['date'], 'description' => $item['notes'] ?? null, 'entry_method' => 'batch', 'synced' => true];
            return isset($item['client_id'])
                ? Transaction::updateOrCreate(['client_id' => $item['client_id'], 'business_id' => $request->user()->business_id], $values)
                : Transaction::create($values + ['business_id' => $request->user()->business_id]);
        }));
        return response()->json(['message' => 'Transactions saved', 'transactions' => $created], 201);
    }

    private function ensureMutable(Transaction $transaction): void
    {
        abort_if(
            $transaction->entry_method === 'order_system',
            409,
            'Order-generated transactions cannot be edited or deleted. Use an order refund or reversal instead.'
        );
    }
}
