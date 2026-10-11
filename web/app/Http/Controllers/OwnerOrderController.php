<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerOrderController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'preparing', 'ready', 'completed', 'cancelled'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'detected', 'confirmed', 'failed', 'cancelled', 'refund_pending', 'refunded'])],
        ]);

        $orders = Order::query()
            ->where('business_id', $request->user()->business_id)
            ->with('creator:id,name')
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['payment_status']), fn ($query) => $query->where('payment_status', $validated['payment_status']))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'statusFilter' => $validated['status'] ?? '',
            'paymentStatusFilter' => $validated['payment_status'] ?? '',
            'currencyCode' => strtoupper((string) ($request->user()->business?->currency ?: 'PHP')),
        ]);
    }

    public function show(Request $request, int $order)
    {
        $record = Order::query()
            ->where('business_id', $request->user()->business_id)
            ->with([
                'creator:id,name',
                'items:id,order_id,product_id,product_name,quantity,price,subtotal',
                'paymentConfirmer:id,name',
                'paymentEvents.actor:id,name',
            ])
            ->findOrFail($order);

        return view('orders.show', ['order' => $record]);
    }
}
