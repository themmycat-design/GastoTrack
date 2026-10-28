<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ClientTransactionWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class SyncController extends Controller
{
    public function sync(Request $request, ClientTransactionWriter $writer)
    {
        $data = $request->validate([
            'transactions' => 'sometimes|array|max:100',
            'transactions.*.client_id' => 'required|uuid|distinct',
            'transactions.*.amount' => 'required|numeric|decimal:0,2|min:0.01|max:99999999.99',
            'transactions.*.type' => 'required|in:income,expense',
            'transactions.*.category' => 'required|string|max:255',
            'transactions.*.source' => 'nullable|string|max:255',
            'transactions.*.date' => 'required|date_format:Y-m-d|before_or_equal:'.now(config('business.timezone'))->toDateString(),
            'transactions.*.notes' => 'nullable|string|max:5000',
            'last_sync_timestamp' => 'nullable|date',
            'page' => 'sometimes|integer|min:1',
        ]);
        $businessId = $request->user()->business_id;
        try {
            $mappings = DB::transaction(function () use ($data, $request, $writer) {
                return collect($data['transactions'] ?? [])->map(function ($item) use ($request, $writer) {
                    $record = $writer->createOrMatch($request->user(), $item['client_id'], [
                        'amount' => $item['amount'], 'type' => $item['type'],
                        'category' => $item['category'], 'source' => $item['source'] ?? null,
                        'transaction_date' => $item['date'], 'description' => $item['notes'] ?? null,
                        'entry_method' => 'offline_sync', 'synced' => true,
                    ]);
                    return ['client_id' => $item['client_id'], 'server_id' => $record->id];
                });
            });
        } catch (QueryException $exception) {
            abort(409, 'A transaction identifier conflicted with another submission. Retry to retrieve its result.');
        }
        $changes = Transaction::where('business_id', $businessId)
            ->when($data['last_sync_timestamp'] ?? null, fn ($q, $since) => $q->where('updated_at', '>', $since))
            ->orderBy('id')->paginate(100, ['*'], 'page', $data['page'] ?? 1);
        return response()->json([
            'mappings' => ['transactions' => $mappings],
            'server_changes' => ['transactions' => $changes->items()],
            'conflicts' => [],
            'sync_timestamp' => now()->toISOString(),
            'changes_page' => $changes->currentPage(),
            'changes_last_page' => $changes->lastPage(),
        ]);
    }
}
