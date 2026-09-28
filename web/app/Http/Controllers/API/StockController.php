<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockController extends Controller
{
    // GET /api/stock
    public function index(Request $request)
    {
        $stock = StockItem::where('business_id', $request->user()->business_id)
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                $item->status = $this->getStatus($item->quantity, $item->threshold);
                return $item;
            });

        return response()->json(['stock' => $stock]);
    }

    // POST /api/stock
    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'quantity'  => 'required|numeric|min:0',
            'unit'      => 'required|string',
            'threshold' => 'required|numeric|min:0',
        ]);

        $item = StockItem::create([
            'business_id' => $request->user()->business_id,
            'name'        => $request->name,
            'quantity'    => $request->quantity,
            'unit'        => $request->unit,
            'threshold'   => $request->threshold,
        ]);

        $item->status = $this->getStatus($item->quantity, $item->threshold);

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

        $item->status = $this->getStatus($item->quantity, $item->threshold);

        return response()->json(['item' => $item]);
    }

    // PUT /api/stock/{id}
    public function update(Request $request, $id)
    {
        $item = StockItem::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $item->update($request->only([
            'name', 'quantity', 'unit', 'threshold'
        ]));

        $item->status = $this->getStatus($item->quantity, $item->threshold);

        return response()->json([
            'message' => 'Stock item updated',
            'item'    => $item,
        ]);
    }

    // DELETE /api/stock/{id}
    public function destroy(Request $request, $id)
    {
        $item = StockItem::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $item->delete();

        return response()->json(['message' => 'Stock item deleted']);
    }

    // POST /api/stock/{id}/adjust
    public function adjust(Request $request, $id)
    {
        $request->validate([
            'type'     => 'required|in:add,deduct',
            'quantity' => 'required|numeric|min:0.01',
            'reason'   => 'nullable|string',
        ]);

        $item = StockItem::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $before = $item->quantity;

        if ($request->type === 'add') {
            $item->quantity += $request->quantity;
        } else {
            $item->quantity = max(0, $item->quantity - $request->quantity);
        }

        $item->save();

        // Log the movement
        StockMovement::create([
            'stock_id'        => $item->id,
            'business_id'     => $request->user()->business_id,
            'type'            => $request->type === 'add' ? 'in' : 'out',
            'quantity'        => $request->quantity,
            'quantity_before' => $before,
            'quantity_after'  => $item->quantity,
            'reason'          => $request->reason ?? 'Manual adjustment',
            'user_id'         => $request->user()->id,
        ]);

        $item->status = $this->getStatus($item->quantity, $item->threshold);

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