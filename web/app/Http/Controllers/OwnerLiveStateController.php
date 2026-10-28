<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerLiveStateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $businessId = (int) $request->user()->business_id;
        $ownerId = (int) $request->user()->id;

        // Only timestamps and IDs leave the database; tenant records never enter
        // this small change signal. The current page supplies its own fresh data.
        $revision = hash('sha256', json_encode([
            DB::table('activity_logs')->where('business_id', $businessId)->max('id'),
            DB::table('stock_movements')->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
                ->where('stock_items.business_id', $businessId)->max('stock_movements.id'),
            DB::table('stock_items')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('orders')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('transactions')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('products')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('notifications')->where('business_id', $businessId)->where('user_id', $ownerId)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread, MAX(updated_at) as changed_at')->first(),
            DB::table('wallet_notification_captures')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('bug_reports')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('users')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('goals')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
            DB::table('transaction_options')->where('business_id', $businessId)
                ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at')->first(),
        ]));

        return response()->json(['revision' => $revision])
            ->header('Cache-Control', 'private, no-store');
    }
}
