<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TransactionOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionOptionController extends Controller
{
    public function index(Request $request)
    {
        $this->seedDefaults($request->user()->business_id);

        $options = TransactionOption::where('business_id', $request->user()->business_id)
            ->orderBy('kind')
            ->orderBy('transaction_type')
            ->orderBy('name')
            ->get();

        return response()->json(['options' => $options]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['category', 'source'])],
            'transaction_type' => ['nullable', Rule::in(['income', 'expense', 'all'])],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($validated['name']);
        abort_if($name === '', 422, 'The option name is required.');

        $transactionType = $validated['kind'] === 'source'
            ? 'all'
            : ($validated['transaction_type'] ?? 'expense');
        abort_if($validated['kind'] === 'category' && $transactionType === 'all', 422, 'A category must be assigned to income or expense.');

        $existing = TransactionOption::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->where('kind', $validated['kind'])
            ->where('transaction_type', $transactionType)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing?->trashed()) {
            $existing->restore();
            return response()->json(['message' => 'Option restored', 'option' => $existing->fresh()], 201);
        }
        abort_if($existing, 422, 'This option already exists.');

        $option = TransactionOption::create([
            'business_id' => $request->user()->business_id,
            'kind' => $validated['kind'],
            'transaction_type' => $transactionType,
            'name' => $name,
        ]);

        return response()->json(['message' => 'Option added', 'option' => $option], 201);
    }

    public function update(Request $request, TransactionOption $transactionOption)
    {
        abort_unless($transactionOption->business_id === $request->user()->business_id, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'transaction_type' => ['nullable', Rule::in(['income', 'expense', 'all'])],
        ]);

        $name = trim($validated['name']);
        abort_if($name === '', 422, 'The option name is required.');
        $transactionType = $transactionOption->kind === 'source'
            ? 'all'
            : ($validated['transaction_type'] ?? $transactionOption->transaction_type);
        abort_if($transactionOption->kind === 'category' && $transactionType === 'all', 422, 'A category must be assigned to income or expense.');

        $duplicate = TransactionOption::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->where('kind', $transactionOption->kind)
            ->where('transaction_type', $transactionType)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $transactionOption->id)
            ->exists();
        abort_if($duplicate, 422, 'This option already exists.');

        $transactionOption->update(['name' => $name, 'transaction_type' => $transactionType]);

        return response()->json(['message' => 'Option updated', 'option' => $transactionOption->fresh()]);
    }

    public function destroy(Request $request, TransactionOption $transactionOption)
    {
        abort_unless($transactionOption->business_id === $request->user()->business_id, 403);
        $transactionOption->delete();

        return response()->json(['message' => 'Option removed']);
    }

    private function seedDefaults(int $businessId): void
    {
        $defaults = [
            ['kind' => 'category', 'transaction_type' => 'income', 'name' => 'Sales'],
            ['kind' => 'category', 'transaction_type' => 'income', 'name' => 'Tips'],
            ['kind' => 'category', 'transaction_type' => 'income', 'name' => 'Other Income'],
            ['kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Ingredients'],
            ['kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Rent'],
            ['kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Salaries'],
            ['kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Utilities'],
            ['kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Supplies'],
            ['kind' => 'category', 'transaction_type' => 'expense', 'name' => 'Other Expense'],
            ['kind' => 'source', 'transaction_type' => 'all', 'name' => 'Cash'],
            ['kind' => 'source', 'transaction_type' => 'all', 'name' => 'GCash'],
            ['kind' => 'source', 'transaction_type' => 'all', 'name' => 'Maya'],
            ['kind' => 'source', 'transaction_type' => 'all', 'name' => 'Bank Transfer'],
            ['kind' => 'source', 'transaction_type' => 'all', 'name' => 'Credit Card'],
        ];

        foreach ($defaults as $default) {
            $option = TransactionOption::withTrashed()->firstOrCreate(
                ['business_id' => $businessId] + $default,
                ['is_default' => true],
            );
            if (!$option->trashed() && !$option->is_default) $option->update(['is_default' => true]);
        }
    }
}
