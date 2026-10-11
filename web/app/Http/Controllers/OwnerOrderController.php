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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'preparing', 'ready', 'completed', 'cancelled'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'detected', 'confirmed', 'failed', 'cancelled', 'refund_pending', 'refunded'])],
        ]);

        $businessOrders = Order::query()->where('business_id', $request->user()->business_id);
        $summary = (clone $businessOrders)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'completed' AND payment_status = 'confirmed' THEN 1 ELSE 0 END) as completed_paid")
            ->selectRaw("SUM(CASE WHEN payment_status = 'pending' OR payment_status IS NULL THEN 1 ELSE 0 END) as awaiting_payment")
            ->selectRaw("SUM(CASE WHEN payment_status = 'detected' THEN 1 ELSE 0 END) as awaiting_verification")
            ->first();

        $orders = (clone $businessOrders)
            ->with(['creator:id,name', 'items:id,order_id,product_name,quantity'])
            ->when(isset($validated['search']), function ($query) use ($validated): void {
                $search = trim($validated['search']);
                if ($search === '') return;
                $query->where(function ($matches) use ($search): void {
                    $matches->where('order_number', 'like', '%'.$search.'%')
                        ->orWhere('legacy_order_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhereHas('items', fn ($items) => $items->where('product_name', 'like', '%'.$search.'%'));
                });
            })
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['payment_status']), function ($query) use ($validated): void {
                if ($validated['payment_status'] === 'pending') {
                    $query->where(fn ($pending) => $pending->where('payment_status', 'pending')->orWhereNull('payment_status'));
                } else {
                    $query->where('payment_status', $validated['payment_status']);
                }
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'summary' => $summary,
            'searchFilter' => $validated['search'] ?? '',
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
