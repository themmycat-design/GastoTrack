<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Idempotent, account-scoped replay for staff edits made while offline. */
class OfflineMutationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        $input = $request->validate([
            'client_id' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['product_create', 'product_update', 'product_delete', 'stock_create', 'transaction_update', 'transaction_delete'])],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'expected_updated_at' => ['nullable', 'date'],
            'expected_revision' => ['nullable', 'integer', 'min:0'],
            'data' => ['present', 'array'],
        ]);
        $kind = $input['kind'];
        $editing = in_array($kind, ['product_update', 'product_delete', 'transaction_update', 'transaction_delete'], true);
        Validator::make($input, [
            'target_id' => $editing ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'expected_updated_at' => $editing ? ['required', 'date'] : ['prohibited'],
            'expected_revision' => $editing ? ['required', 'integer', 'min:0'] : ['prohibited'],
        ])->validate();
        $businessId = (int) $request->user()->business_id;
        $userId = (int) $request->user()->id;
        $hash = hash('sha256', json_encode([$businessId, $userId, $kind, $input['target_id'] ?? null,
            $input['expected_updated_at'] ?? null, $input['expected_revision'] ?? null,
            $input['data']], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $input, $kind, $editing, $businessId, $userId, $hash): JsonResponse {
            $existing = DB::table('offline_mutation_receipts')->where('client_id', $input['client_id'])->lockForUpdate()->first();
            if ($existing) {
                abort_if((int) $existing->business_id !== $businessId || (int) $existing->user_id !== $userId
                    || $existing->kind !== $kind || $existing->request_hash !== $hash, 409,
                    'This offline action identifier belongs to a different request.');
                return response()->json(json_decode($existing->response_body, true, 512, JSON_THROW_ON_ERROR), $existing->response_status);
            }

            if ($editing) {
                $model = str_starts_with($kind, 'product_') ? Product::class : Transaction::class;
                $target = $model::where('business_id', $businessId)->lockForUpdate()->findOrFail($input['target_id']);
                abort_unless((int) $input['expected_revision'] === (int) $target->revision
                    && Carbon::parse($input['expected_updated_at'])->equalTo($target->updated_at), 409,
                    'This record changed on the server while your phone was offline. Refresh it and review your edit.');
            }

            $subrequest = Request::create('/', str_ends_with($kind, '_delete') ? 'DELETE'
                : ($editing ? 'PUT' : 'POST'), $input['data']);
            $subrequest->setUserResolver(fn () => $request->user());
            $response = match ($kind) {
                'product_create' => app(ProductController::class)->store($subrequest),
                'product_update' => app(ProductController::class)->update($subrequest, $input['target_id']),
                'product_delete' => app(ProductController::class)->destroy($subrequest, $input['target_id']),
                'stock_create' => app(StockController::class)->store($subrequest),
                'transaction_update' => app(TransactionController::class)->update($subrequest, $input['target_id']),
                'transaction_delete' => app(TransactionController::class)->destroy($subrequest, $input['target_id']),
            };
            $body = $response->getData(true);
            DB::table('offline_mutation_receipts')->insert([
                'client_id' => $input['client_id'], 'business_id' => $businessId, 'user_id' => $userId,
                'kind' => $kind, 'request_hash' => $hash, 'response_status' => $response->getStatusCode(),
                'response_body' => json_encode($body, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json($body, $response->getStatusCode());
        });
    }
}
