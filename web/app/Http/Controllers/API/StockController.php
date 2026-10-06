<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class StockController extends Controller
{
    // GET /api/stock
    public function index(Request $request)
    {
        $stock = StockItem::where('business_id', $request->user()->business_id)
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereColumn('current_quantity', '<=', 'minimum_quantity'))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 20), 100));

        $stock->getCollection()->transform(function ($item) {
            $item->status = $this->getStatus($item->current_quantity, $item->minimum_quantity);
            return $item;
        });

        return response()->json(['stock' => $stock->items(), 'meta' => ['current_page' => $stock->currentPage(), 'last_page' => $stock->lastPage(), 'total' => $stock->total()]]);
    }

    // POST /api/stock
    public function store(Request $request)
    {
        abort_unless($request->user()->isOwner() || $request->user()->isStaff(), 403, 'Only owners and staff can create stock items.');
        $request->validate([
            'name'      => 'required|string|max:255',
            'current_quantity' => 'required|numeric|min:0',
            'unit'      => 'required|string',
            'minimum_quantity' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
        ]);

        $item = StockItem::create([
            'business_id' => $request->user()->business_id,
            'name'        => $request->name,
            'current_quantity' => $request->current_quantity,
            'unit'        => $request->unit,
            'minimum_quantity' => $request->minimum_quantity,
            'unit_cost'   => $request->unit_cost,
            'active'      => true,
        ]);

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
            'minimum_quantity' => 'sometimes|numeric|min:0', 'unit_cost' => 'sometimes|numeric|min:0',
            'active' => 'sometimes|boolean',
        ]);
        $item->update($data);

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
            'quantity' => 'required|numeric|min:0.01',
            'reason'   => 'nullable|string|max:500',
        ]);

        $item = DB::transaction(function () use ($request, $id) {
            $item = StockItem::where('business_id', $request->user()->business_id)
                ->lockForUpdate()
                ->findOrFail($id);
            $before = (float) $item->current_quantity;
            $quantity = (float) $request->quantity;

            if ($request->type === 'add') {
                $item->current_quantity = $before + $quantity;
            } else {
                abort_if($quantity > $before, 422, 'Adjustment would make stock negative.');
                $item->current_quantity = $before - $quantity;
            }

            $item->save();
            StockMovement::create([
                'stock_item_id'   => $item->id,
                'type'            => $request->type === 'add' ? 'in' : 'out',
                'quantity'        => $quantity,
                'previous_quantity' => $before,
                'new_quantity'     => $item->current_quantity,
                'reason'          => $request->reason ?? 'Manual adjustment',
                'user_id'         => $request->user()->id,
            ]);

            return $item;
        });

        app(NotificationService::class)->notifyLowStock($item);

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
