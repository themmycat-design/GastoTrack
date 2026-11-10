<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\StockUnitPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;

class StockController extends Controller
{
    // GET /api/stock
    public function index(Request $request)
    {
        $outOfStockCount = StockItem::where('business_id', $request->user()->business_id)
            ->where('active', true)
            ->where('current_quantity', '<=', 0)
            ->count();
        $stock = StockItem::where('business_id', $request->user()->business_id)
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereColumn('current_quantity', '<=', 'minimum_quantity'))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 20), 100));

        $stock->getCollection()->transform(function ($item) {
            $item->status = $this->getStatus($item->current_quantity, $item->minimum_quantity);
            return $item;
        });

        return response()->json(['stock' => $stock->items(), 'meta' => ['current_page' => $stock->currentPage(), 'last_page' => $stock->lastPage(), 'total' => $stock->total(), 'out_of_stock_count' => $outOfStockCount]]);
    }

    // POST /api/stock
    public function store(Request $request)
    {
        abort_unless($request->user()->isOwner() || $request->user()->isStaff(), 403, 'Only owners and staff can create stock items.');
        $request->validate([
            'name'      => 'required|string|max:255',
            'current_quantity' => 'required|numeric|decimal:0,4|min:0|max:99999999.9999',
            'unit'      => 'required|string|max:50',
            'minimum_quantity' => 'required|numeric|decimal:0,4|min:0|max:99999999.9999',
            'unit_cost' => 'required|numeric|decimal:0,4|min:0|max:99999999.9999',
        ]);

        $item = DB::transaction(function () use ($request): StockItem {
            $item = StockItem::create([
                'business_id' => $request->user()->business_id,
                'name' => $request->name,
                'current_quantity' => $request->current_quantity,
                'unit' => $request->unit,
                'minimum_quantity' => $request->minimum_quantity,
                'unit_cost' => $request->unit_cost,
                'active' => true,
            ]);
            if ((float) $item->current_quantity > 0) {
                StockMovement::create([
                    'stock_item_id' => $item->id,
                    'user_id' => $request->user()->id,
                    'type' => 'in',
                    'quantity' => $item->current_quantity,
                    'previous_quantity' => 0,
                    'new_quantity' => $item->current_quantity,
                    'reason' => 'Opening stock',
                    'movement_category' => 'opening',
                    'unit_cost_at_movement' => $item->unit_cost,
                ]);
            }
            return $item;
        });
        app(NotificationService::class)->notifyLowStock($item);

        $item->status = $this->getStatus($item->current_quantity, $item->minimum_quantity);

        return response()->json([
            'message' => 'Stock item saved',
            'item'    => $item,
        ], 201);
    }

    // GET /api/stock/{id}
    public function show(Request $request, $id)
    {
        $item = StockItem::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $item->status = $this->getStatus($item->current_quantity, $item->minimum_quantity);

        return response()->json(['item' => $item]);
    }

    // PUT /api/stock/{id}
    public function update(Request $request, $id)
    {
        abort_unless($request->user()->isOwner(), 403, 'Only owners can update stock item details.');
        $item = StockItem::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255', 'unit' => 'sometimes|string|max:50',
            'minimum_quantity' => 'sometimes|numeric|decimal:0,4|min:0|max:99999999.9999',
            'unit_cost' => 'sometimes|numeric|decimal:0,4|min:0|max:99999999.9999',
            'active' => 'sometimes|boolean',
        ]);
        if (isset($data['unit'])) {
            StockUnitPolicy::ensureUnchanged($data['unit'], $item->unit);
            unset($data['unit']);
        }
        $item->update($data);
        app(NotificationService::class)->notifyLowStock($item);

        $item->status = $this->getStatus($item->current_quantity, $item->minimum_quantity);

        return response()->json([
            'message' => 'Stock item updated',
            'item'    => $item,
        ]);
    }

    // DELETE /api/stock/{id}
    public function destroy(Request $request, $id)
    {
        abort_unless($request->user()->isOwner(), 403, 'Only owners can delete stock items.');
        $item = StockItem::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $item->delete();
        app(NotificationService::class)->notifyLowStock($item);

        return response()->json(['message' => 'Stock item deleted']);
    }

    public function movements(Request $request, $id)
    {
        $item = StockItem::where('business_id', $request->user()->business_id)->findOrFail($id);
        return response()->json($item->movements()->latest()->paginate(min($request->integer('per_page', 20), 100)));
    }

    // POST /api/stock/{id}/adjust
    public function adjust(Request $request, $id)
    {
        $request->validate([
            'type'     => 'required|in:add,deduct',
            'quantity' => 'required|numeric|decimal:0,4|min:0.0001|max:99999999.9999',
            'reason'   => 'nullable|string|max:500',
            'movement_category' => 'nullable|in:adjustment,waste',
            'client_id' => 'nullable|uuid',
            'offline_recorded_at' => 'nullable|date',
            'expected_revision' => 'nullable|integer|min:0',
        ]);

        $offlineRecordedAt = $request->filled('offline_recorded_at')
            ? \Carbon\CarbonImmutable::parse($request->input('offline_recorded_at'))->utc()
            : null;
        abort_if($offlineRecordedAt && (!$request->filled('client_id')
            || $offlineRecordedAt->greaterThan(now()->addMinutes(2))
            || $offlineRecordedAt->lessThan(now()->subDays(30))), 422,
            'The offline stock adjustment time is invalid or outside the allowed 30-day range.');

        $movementCategory = $request->input('movement_category', 'adjustment');
        abort_if($movementCategory === 'waste' && $request->type !== 'deduct', 422, 'Waste can only be recorded when deducting stock.');

        $clientId = $request->input('client_id');
        $reason = $request->input('reason') ?: 'Manual adjustment';

        try {
            [$item, $created] = DB::transaction(function () use ($request, $id, $movementCategory, $clientId, $reason, $offlineRecordedAt) {
            $item = StockItem::where('business_id', $request->user()->business_id)
                ->lockForUpdate()
                ->findOrFail($id);
            if ($clientId) {
                $existing = StockMovement::where('client_id', $clientId)->lockForUpdate()->first();
                if ($existing) {
                    abort_unless((int) $existing->stock_item_id === (int) $item->id, 409,
                        'This stock adjustment identifier cannot be reused.');
                    abort_unless((int) $existing->user_id === (int) $request->user()->id, 403,
                        'Only the original recorder can retry this adjustment.');
                    $same = $existing->type === ($request->type === 'add' ? 'in' : 'out')
                        && (int) round((float) $existing->quantity * 10000) === (int) round((float) $request->quantity * 10000)
                        && $existing->reason === $reason
                        && $existing->movement_category === $movementCategory
                        && ($existing->offline_recorded_at?->timestamp === $offlineRecordedAt?->timestamp)
                        && $existing->order_id === null && $existing->reversal_of_id === null;
                    abort_unless($same, 409, 'This adjustment identifier was already used with different details.');
                    return [$item, false];
                }
            }
            abort_if($request->has('expected_revision')
                && (int) $request->input('expected_revision') !== (int) $item->revision,
                409, 'This stock item changed on the server while your phone was offline. Refresh it and review your adjustment.');
            $before = (float) $item->current_quantity;
            $quantity = (float) $request->quantity;

            if ($request->type === 'add') {
                $item->current_quantity = round($before + $quantity, 4);
                abort_if($item->current_quantity > 99999999.9999, 422, 'Adjustment would exceed the maximum stock quantity.');
            } else {
                abort_if($quantity > $before, 422, 'Adjustment would make stock negative.');
                $item->current_quantity = round($before - $quantity, 4);
            }

            $item->save();
            StockMovement::create([
                'stock_item_id'   => $item->id,
                'type'            => $request->type === 'add' ? 'in' : 'out',
                'quantity'        => $quantity,
                'previous_quantity' => $before,
                'new_quantity'     => $item->current_quantity,
                'reason'          => $reason,
                'user_id'         => $request->user()->id,
                'movement_category' => $movementCategory,
                'unit_cost_at_movement' => $item->unit_cost,
                'client_id' => $clientId,
                'offline_recorded_at' => $offlineRecordedAt,
                'created_at' => $offlineRecordedAt ?: now(),
            ]);

            return [$item, true];
            });
        } catch (QueryException $exception) {
            abort(409, 'This stock adjustment identifier conflicted with another request. Retry to retrieve its result.');
        }

        if ($created) app(NotificationService::class)->notifyLowStock($item);

        $item->status = $this->getStatus($item->current_quantity, $item->minimum_quantity);

        return response()->json([
            'message' => 'Stock adjusted',
            'item'    => $item,
        ]);
    }

    private function getStatus($quantity, $threshold)
    {
        if ($quantity <= 0) return 'Out';
        if ($quantity <= $threshold) return 'Low';
        return 'OK';
    }
}
