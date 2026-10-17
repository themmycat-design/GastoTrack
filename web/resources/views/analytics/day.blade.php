<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>{{ $day->format('M j, Y') }} | Analytics | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php($currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ')
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <header class="gt-enter">
                <a href="{{ route('analytics.index') }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">← Back to Analytics</a>
                <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $day->format('l, F j, Y') }}</h1>
                <p class="mt-2 text-sm text-slate-500">Transactions are grouped by their recorded date. Orders are included if placed, completed, or financially committed on this day; ingredient use comes from order-linked stock movements made on this day.</p>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Recorded and operational totals">
                <div class="gt-card p-5 sm:p-6">
                    <p class="text-sm font-semibold text-slate-500">Recorded income</p>
                    <p class="mt-2 text-2xl font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format($income, 2) }}</p>
                </div>
                <div class="gt-card p-5 sm:p-6">
                    <p class="text-sm font-semibold text-slate-500">Recorded expenses</p>
                    <p class="mt-2 text-2xl font-extrabold text-rose-600">{{ $currencyPrefix }}{{ number_format($expenses, 2) }}</p>
                </div>
                <div class="gt-card p-5 sm:p-6">
                    <p class="text-sm font-semibold text-slate-500">Recorded waste cost</p>
                    <p class="mt-2 text-2xl font-extrabold text-amber-700">{{ $currencyPrefix }}{{ number_format((float) $wasteCost, 2) }}</p>
                    <p class="mt-2 text-xs text-slate-400">@if($wasteCostEstimated) Some waste costs use current inventory cost when available @else Explicitly marked waste / spoilage @endif</p>
                </div>
            </section>

            <div class="grid items-start gap-5 xl:grid-cols-3" aria-label="Daily activity details">
            <section class="gt-card min-w-0 overflow-hidden" aria-label="Transactions on this day">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#EAF2EF] px-5 py-4 sm:px-6">
                    <h2 class="text-lg font-extrabold">Transactions</h2>
                    <a href="{{ route('transactions.index', ['date_from' => $day->toDateString(), 'date_to' => $day->toDateString()]) }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">Open transaction history →</a>
                </div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($transactions as $transaction)
                        <div class="flex flex-col items-start gap-2 px-5 py-4 sm:px-6">
                            <div class="min-w-0">
                                <p class="font-bold">{{ $transaction->category ?: 'Uncategorized' }}</p>
                                <p class="mt-1 break-words text-sm text-slate-600">{{ $transaction->description ?: 'No description' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ ucfirst($transaction->source ?: 'Unknown source') }} · {{ $transaction->user?->name ?? 'Staff account unavailable' }} · {{ $transaction->created_at?->format('g:i A') }}</p>
                            </div>
                            <p class="font-extrabold {{ $transaction->type === 'income' ? 'text-[#08745D]' : 'text-rose-600' }}">{{ $transaction->type === 'income' ? '+' : '−' }}{{ $currencyPrefix }}{{ number_format((float) $transaction->amount, 2) }}</p>
                        </div>
                    @empty
                        <p class="px-6 py-10 text-center text-sm text-slate-500">No transactions were recorded for this date.</p>
                    @endforelse
                </div>
                @if($transactions->hasPages())<div class="border-t border-[#EAF2EF] px-5 py-4">{{ $transactions->links() }}</div>@endif
            </section>

            <section class="gt-card min-w-0 overflow-hidden" aria-label="Orders on this day">
                <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6"><h2 class="text-lg font-extrabold">Orders</h2></div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($orders as $order)
                        <a href="{{ route('orders.show', $order->id) }}" class="gt-focus flex items-start justify-between gap-3 px-5 py-4 transition hover:bg-[#FBFDFC] sm:px-6">
                            <div class="min-w-0">
                                <p class="break-words font-bold text-[#08745D]">{{ $order->items->map(fn ($item) => $item->quantity.'× '.$item->product_name)->implode(', ') ?: 'Order details' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ ucfirst($order->status) }} · Payment: {{ str_replace('_', ' ', ucfirst($order->payment_status ?: 'pending')) }} · {{ strtoupper($order->payment_method ?: 'unknown') }}</p>
                            </div>
                            <p class="shrink-0 text-right font-extrabold">{{ $currencyPrefix }}{{ number_format((float) $order->total, 2) }} →</p>
                        </a>
                    @empty
                        <p class="px-6 py-10 text-center text-sm text-slate-500">No orders were placed or completed for this date.</p>
                    @endforelse
                </div>
                @if($orders->hasPages())<div class="border-t border-[#EAF2EF] px-5 py-4">{{ $orders->links() }}</div>@endif
            </section>

            <section class="gt-card min-w-0 overflow-hidden" aria-label="Ingredients used for orders on this day">
                <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6"><h2 class="text-lg font-extrabold">Ingredients reduced</h2></div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($ingredientsUsed as $ingredient)
                        <div class="flex flex-col items-start gap-2 px-5 py-4 sm:px-6">
                            <div><p class="font-bold">{{ $ingredient->name }}</p><p class="mt-1 text-xs text-slate-500">Used in {{ $ingredient->order_count }} {{ $ingredient->order_count === 1 ? 'order' : 'orders' }}</p></div>
                            <p class="font-extrabold text-[#08745D]">−{{ number_format((float) $ingredient->quantity_used, 2) }} {{ $ingredient->unit }}</p>
                        </div>
                    @empty
                        <p class="px-6 py-10 text-center text-sm text-slate-500">No order-linked ingredient deductions were recorded for this date.</p>
                    @endforelse
                </div>
            </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
