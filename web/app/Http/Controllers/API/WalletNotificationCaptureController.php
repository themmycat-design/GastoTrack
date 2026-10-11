<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WalletNotificationCapture;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WalletNotificationCaptureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $user->isOwner(), 403);
        $filters = $request->validate([
            'source' => ['nullable', Rule::in(['gcash', 'maya'])],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = WalletNotificationCapture::where('business_id', $user->business_id)
            ->with('capturedBy:id,name');
        if ($user->isStaff()) $query->where('user_id', $user->id);
        if (!empty($filters['source'])) $query->where('source', $filters['source']);
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('message', 'like', "%{$search}%")
                ->orWhere('merchant', 'like', "%{$search}%"));
        }
        $timezone = config('business.timezone');
        if (!empty($filters['from'])) {
            $query->where('captured_at', '>=', Carbon::parse($filters['from'], $timezone)->startOfDay()->utc());
        }
        if (!empty($filters['to'])) {
            $query->where('captured_at', '<=', Carbon::parse($filters['to'], $timezone)->endOfDay()->utc());
        }

        $captures = $query->orderByDesc('captured_at')->orderByDesc('id')->paginate(20);

        return response()->json([
            'captures' => $captures->items(),
            'meta' => [
                'current_page' => $captures->currentPage(),
                'last_page' => $captures->lastPage(),
                'total' => $captures->total(),
            ],
        ]);
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff(), 403, 'Only staff may upload captured wallet notifications.');
        $validated = $request->validate([
            'captures' => ['required', 'array', 'min:1', 'max:50'],
            'captures.*.client_id' => ['required', 'uuid'],
            'captures.*.source' => ['required', Rule::in(['gcash', 'maya'])],
            'captures.*.direction' => ['required', Rule::in(['received', 'sent', 'unknown'])],
            'captures.*.amount' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'captures.*.currency' => ['required', Rule::in(['PHP'])],
            'captures.*.merchant' => ['nullable', 'string', 'max:160'],
            'captures.*.message' => ['nullable', 'string', 'max:1000'],
            'captures.*.captured_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated, $user): void {
            foreach ($validated['captures'] as $capture) {
                $id = $capture['client_id'];
                DB::table('wallet_notification_captures')->insertOrIgnore([
                    'client_id' => $id,
                    'business_id' => $user->business_id,
                    'user_id' => $user->id,
                    'source' => $capture['source'],
                    'direction' => $capture['direction'],
                    'amount' => $capture['amount'] ?? null,
                    'currency' => $capture['currency'],
                    'merchant' => $capture['merchant'] ?? null,
                    'message' => $capture['message'] ?? null,
                    'captured_at' => Carbon::parse($capture['captured_at'])->utc(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $saved = WalletNotificationCapture::where('client_id', $id)->firstOrFail();
                abort_unless(
                    (int) $saved->business_id === (int) $user->business_id
                        && (int) $saved->user_id === (int) $user->id,
                    409,
                    'This notification identifier belongs to another account.'
                );
            }
        });

        return response()->json([
            'saved_ids' => array_column($validated['captures'], 'client_id'),
            'message' => 'Wallet notification signals saved. They are not verified payments or income.',
        ]);
    }
}
