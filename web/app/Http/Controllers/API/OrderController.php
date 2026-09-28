<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // GET /api/orders
    public function index(Request $request)
    {
        $orders = Order::where('business_id', $request->user()->business_id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    // POST /api/orders — creates order, deducts stock, records income transaction
    public function store(Request $request)
    {
        $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.product_name'  => 'required|string',
            'items.*.product_price' => 'required|numeric',
            'items.*.quantity'      => 'required|integer|min:1',
            'payment_method' => 'required|string',
            'total'          => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        $businessId = $request->user()->business_id;

        // Create order
        $order = Order::create([
            'business_id'    => $businessId,
            'order_number'   => 'ORD-' . strtoupper(Str::random(8)),
            'customer_name'  => 'Walk-in Customer',
            'subtotal'       => $request->total,
            'total'          => $request->total,
            'payment_method' => $request->payment_method,
            'status'         => 'completed',
            'created_by'     => $request->user()->id,
            'completed_at'   => now(),
            'notes'          => $request->notes,
        ]);

        // Save order items + deduct stock
        foreach ($request->items as $item) {
            OrderItem::create([
                'order_id'      => $order->id,
                'product_id'    => $item['product_id'],
                'product_name'  => $item['product_name'],
                'product_price' => $item['product_price'],
                'quantity'      => $item['quantity'],
                'subtotal'      => $item['product_price'] * $item['quantity'],
            ]);

            // Deduct stock ingredients for each product
            $this->deductStock(
                $item['product_id'],
                $item['quantity'],
                $businessId,
                $request->user()->id,
                $order->id
            );
        }

        // Record income transaction automatically
        Transaction::create([
            'business_id'  => $businessId,
            'user_id'      => $request->user()->id,
            'recorded_by'  => $request->user()->id,
            'amount'       => $request->total,
            'type'         => 'income',
            'source'       => $request->payment_method,
            'category'     => 'Sales',
            'date'         => now()->toDateString(),
            'notes'        => 'Order #' . $order->order_number,
            'entry_method' => 'order_system',
        ]);

        return response()->json([
            'message' => 'Order placed successfully',
            'order'   => $order->load('items'),
        ], 201);
    }

    // GET /api/orders/{id}
    public function show(Request $request, $id)
    {
        $order = Order::where('business_id', $request->user()->business_id)
            ->with('items')
            ->findOrFail($id);

        return response()->json(['order' => $order]);
    }

    // PUT /api/orders/{id} — update status
    public function update(Request $request, $id)
    {
        $order = Order::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $order->update($request->only(['status', 'notes']));

        return response()->json([
            'message' => 'Order updated',
            'order'   => $order,
        ]);
    }

    // DELETE /api/orders/{id} — cancel
    public function destroy(Request $request, $id)
    {
        $order = Order::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $order->update([
            'status'       => 'cancelled',
            'cancelled_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Order cancelled']);
    }

    // Deduct stock ingredients per product
    private function deductStock($productId, $qty, $businessId, $userId, $orderId)
    {
        $ingredients = \App\Models\ProductIngredient::where('product_id', $productId)->get();

        foreach ($ingredients as $ingredient) {
            $stockItem = StockItem::where('id', $ingredient->stock_id)
                ->where('business_id', $businessId)
                ->first();

            if (!$stockItem) continue;

            $deductAmount = $ingredient->quantity * $qty;
            $before = $stockItem->quantity;
            $stockItem->quantity = max(0, $stockItem->quantity - $deductAmount);
            $stockItem->save();

            StockMovement::create([
                'stock_id'        => $stockItem->id,
                'business_id'     => $businessId,
                'type'            => 'out',
                'quantity'        => $deductAmount,
                'quantity_before' => $before,
                'quantity_after'  => $stockItem->quantity,
                'reason'          => 'Order #' . $orderId,
                'order_id'        => $orderId,
                'user_id'         => $userId,
            ]);
        }
    }
}