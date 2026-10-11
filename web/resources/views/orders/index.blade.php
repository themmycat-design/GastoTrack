<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Orders | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-5 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section class="gt-enter">
                <p class="text-sm font-semibold text-[#00A87E]">{{ Auth::user()->business?->name }} · order records</p>
                <h2 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Orders</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Review staff orders and their payment progress. Wallet notifications remain unverified until confirmed by staff.</p>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Order overview">
                @foreach([
                    ['label' => 'All orders', 'count' => $summary?->total_orders ?? 0, 'url' => route('orders.index'), 'tone' => 'text-[#08745D]', 'badge' => 'bg-[#EAF8F2]'],
                    ['label' => 'Completed & paid', 'count' => $summary?->completed_paid ?? 0, 'url' => route('orders.index', ['status' => 'completed', 'payment_status' => 'confirmed']), 'tone' => 'text-[#08745D]', 'badge' => 'bg-[#EAF8F2]'],
                    ['label' => 'Awaiting payment', 'count' => $summary?->awaiting_payment ?? 0, 'url' => route('orders.index', ['payment_status' => 'pending']), 'tone' => 'text-amber-700', 'badge' => 'bg-amber-50'],
                    ['label' => 'Awaiting verification', 'count' => $summary?->awaiting_verification ?? 0, 'url' => route('orders.index', ['payment_status' => 'detected']), 'tone' => 'text-orange-700', 'badge' => 'bg-orange-50'],
                ] as $index => $card)
                    <a href="{{ $card['url'] }}" class="gt-card gt-lift gt-enter gt-enter-{{ $index + 1 }} gt-focus block p-5 sm:p-6">
                        <span class="flex items-start justify-between gap-3">
                            <span class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</span>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $card['badge'] }} {{ $card['tone'] }}" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M8 8h8M8 12h6"/></svg>
                            </span>
                        </span>
                        <strong class="mt-2 block text-3xl font-extrabold {{ $card['tone'] }}">{{ number_format((int) $card['count']) }}</strong>
                    </a>
                @endforeach
            </section>

            <section class="gt-card gt-enter overflow-hidden" aria-labelledby="orders-heading">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <div>
                        <h3 id="orders-heading" class="text-lg font-extrabold">Order history</h3>
                        <p class="mt-1 text-xs text-slate-500">Order status and payment status are shown separately.</p>
                    </div>
                    <span class="rounded-full bg-[#EAF8F2] px-3 py-1.5 text-xs font-bold text-[#08745D]">{{ number_format($orders->total()) }} {{ $orders->total() === 1 ? 'order' : 'orders' }}</span>
                </div>

                <form method="GET" action="{{ route('orders.index') }}" class="grid gap-3 border-b border-[#EAF2EF] px-5 py-4 sm:grid-cols-2 sm:px-6 xl:grid-cols-[minmax(180px,1.6fr)_minmax(150px,1fr)_minmax(150px,1fr)_auto] xl:items-end" aria-label="Filter orders">
                <div>
                    <label for="order-search" class="mb-2 block text-xs font-bold text-slate-600">Search orders</label>
                    <input id="order-search" name="search" type="search" value="{{ $searchFilter }}" placeholder="Customer or product" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm placeholder:text-slate-400">
                </div>
                <div>
                    <label for="status" class="mb-2 block text-xs font-bold text-slate-600">Order status</label>
                    <select id="status" name="status" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                        <option value="">All statuses</option>
                        @foreach(['completed', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected($statusFilter === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment_status" class="mb-2 block text-xs font-bold text-slate-600">Payment status</label>
                    <select id="payment_status" name="payment_status" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                        <option value="">All payment statuses</option>
                        @foreach(['confirmed', 'failed', 'cancelled', 'refunded'] as $status)
                            <option value="{{ $status }}" @selected($paymentStatusFilter === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
                    <button class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#08745D]">Filter</button>
                    <a href="{{ route('orders.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-center text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Clear</a>
                </div>
            </form>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead class="bg-[#F8FCFA] text-xs font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-3 sm:pl-6">Order</th>
                                <th scope="col" class="px-5 py-3">Items</th>
                                <th scope="col" class="px-5 py-3">Customer / staff</th>
                                <th scope="col" class="px-5 py-3">Order status</th>
                                <th scope="col" class="px-5 py-3">Payment</th>
                                <th scope="col" class="px-5 py-3 text-right sm:pr-6">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EAF2EF]">
                            @forelse($orders as $order)
                                @php
                                    $paymentStatus = $order->payment_status ?: 'pending';
                                    $paymentLabel = match ($paymentStatus) {
                                        'pending' => 'Awaiting payment',
                                        'detected' => 'Awaiting verification',
                                        'confirmed' => 'Confirmed',
                                        'refund_pending' => 'Refund pending',
                                        default => \Illuminate\Support\Str::headline($paymentStatus),
                                    };
                                    $paymentTone = match ($paymentStatus) {
                                        'confirmed' => 'bg-[#EAF8F2] text-[#08745D]',
                                        'pending' => 'bg-amber-50 text-amber-800',
                                        'detected', 'refund_pending' => 'bg-orange-50 text-orange-800',
                                        'failed' => 'bg-rose-50 text-rose-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                    $orderTone = match ($order->status) {
                                        'completed' => 'bg-[#EAF8F2] text-[#08745D]',
                                        'cancelled' => 'bg-rose-50 text-rose-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <tr class="align-top transition hover:bg-[#F8FCFA]">
                                    <td class="px-5 py-4 sm:pl-6">
                                        <a href="{{ route('orders.show', $order->id) }}" class="gt-focus font-extrabold text-[#08745D] hover:underline" aria-label="View order details">View order</a>
                                        <span class="mt-1 block whitespace-nowrap text-xs text-slate-500">{{ $order->created_at?->format('M j, Y · g:i A') ?: 'Date unavailable' }}</span>
                                    </td>
                                    <td class="max-w-[240px] px-5 py-4 text-slate-600">
                                        @forelse($order->items->take(2) as $item)
                                            <span class="block truncate">{{ $item->quantity }} × {{ $item->product_name ?: 'Unnamed product' }}</span>
                                        @empty
                                            <span class="text-slate-400">No item details</span>
                                        @endforelse
                                        @if($order->items->count() > 2)
                                            <span class="mt-1 block text-xs text-slate-500">+{{ $order->items->count() - 2 }} more</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="block font-semibold">{{ $order->customer_name ?: 'Walk-in customer' }}</span>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $order->creator?->name ?? 'Staff unavailable' }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold {{ $orderTone }}">{{ \Illuminate\Support\Str::headline($order->status ?: 'pending') }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="block text-xs font-semibold uppercase text-slate-500">{{ $order->payment_method ?: 'Method unavailable' }}</span>
                                        <span class="mt-1 inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold {{ $paymentTone }}">{{ $paymentLabel }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-extrabold sm:pr-6">{{ $currencyCode === 'PHP' ? '₱' : $currencyCode.' ' }}{{ number_format((float) $order->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-14 text-center">
                                    <p class="text-sm font-bold text-[#0A2E2A]">No orders found</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $searchFilter !== '' || $statusFilter !== '' || $paymentStatusFilter !== '' ? 'Try clearing the filters to see more orders.' : 'Staff orders will appear here after they are placed.' }}</p>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($orders->hasPages())
                    <div class="border-t border-[#EAF2EF] px-5 py-4 sm:px-6">{{ $orders->links() }}</div>
                @endif
            </section>
        </div>
    </main>
</div>
</body>
</html>
