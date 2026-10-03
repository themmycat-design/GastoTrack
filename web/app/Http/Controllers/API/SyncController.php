<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function sync(Request $request)
    {
        $data = $request->validate([
            'transactions' => 'sometimes|array|max:100',
            'transactions.*.client_id' => 'required|uuid',
            'transactions.*.amount' => 'required|numeric|min:0.01',
            'transactions.*.type' => 'required|in:income,expense',
            'transactions.*.category' => 'required|string|max:255',
            'transactions.*.source' => 'nullable|string|max:255',
            'transactions.*.date' => 'required|date',
            'transactions.*.notes' => 'nullable|string',
            'last_sync_timestamp' => 'nullable|date',
        ]);
        $businessId = $request->user()->business_id;
        $mappings = DB::transaction(function () use ($data, $request, $businessId) {
            return collect($data['transactions'] ?? [])->map(function ($item) use ($request, $businessId) {
                $record = Transaction::updateOrCreate(
                    ['client_id' => $item['client_id'], 'business_id' => $businessId],
                    ['user_id' => $request->user()->id, 'amount' => $item['amount'], 'type' => $item['type'], 'category' => $item['category'], 'source' => $item['source'] ?? null, 'transaction_date' => $item['date'], 'description' => $item['notes'] ?? null, 'entry_method' => 'offline_sync', 'synced' => true]
                );
                return ['client_id' => $item['client_id'], 'server_id' => $record->id];
            });
        });
        $changes = Transaction::where('business_id', $businessId)
            ->when($data['last_sync_timestamp'] ?? null, fn ($q, $since) => $q->where('updated_at', '>', $since))->get();
        return response()->json(['mappings' => ['transactions' => $mappings], 'server_changes' => ['transactions' => $changes], 'conflicts' => [], 'sync_timestamp' => now()->toISOString()]);
    }
}
