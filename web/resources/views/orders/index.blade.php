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
        <div class="mx-auto max-w-[1400px] space-y-5 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section>
                <p class="text-sm font-semibold text-[#00A87E]">{{ Auth::user()->business?->name }} · order records</p>
                <h2 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Orders</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Read-only owner view. Order and payment statuses are separate; a detected wallet notification is not confirmation.</p>
            </section>

            <form method="GET" action="{{ route('orders.index') }}" class="gt-card grid gap-3 p-4 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                <div>
                    <label for="status" class="mb-1 block text-xs font-bold text-slate-600">Order status</label>
                    <select id="status" name="status" class="w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                        <option value="">All statuses</option>
                        @foreach(['pending', 'preparing', 'ready', 'completed', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected($statusFilter === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment_status" class="mb-1 block text-xs font-bold text-slate-600">Payment status</label>
                    <select id="payment_status" name="payment_status" class="w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                        <option value="">All payment statuses</option>
                        @foreach(['pending', 'detected', 'confirmed', 'failed', 'cancelled', 'refund_pending', 'refunded'] as $status)
                            <option value="{{ $status }}" @selected($paymentStatusFilter === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#08745D]">Apply filters</button>
                <a href="{{ route('orders.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-center text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Clear</a>
            </form>

            <section class="gt-card overflow-hidden" aria-label="Orders">
                <div class="divide-y divide-[#EAF2EF]">
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
                        @endphp
                        <a href="{{ route('orders.show', $order->id) }}" class="gt-focus flex flex-col gap-3 px-5 py-4 transition hover:bg-[#FBFDFC] sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-extrabold text-[#08745D]">{{ $order->order_number ?: 'Order #'.$order->id }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ optional($order->created_at)->format('M j, Y · g:i A') }} · {{ $order->creator?->name ?? 'Staff account unavailable' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::headline($order->status) }} · {{ $order->payment_method ? strtoupper($order->payment_method) : 'Payment method unavailable' }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $paymentTone }}">{{ $paymentLabel }}</span>
                                <span class="text-sm font-extrabold text-[#0A2E2A]">{{ $currencyCode === 'PHP' ? '₱' : $currencyCode.' ' }}{{ number_format((float) $order->total, 2) }}</span>
                                <span class="text-sm font-bold text-[#08745D]" aria-hidden="true">→</span>
                            </div>
                        </a>
                    @empty
                        <div class="px-6 py-14 text-center">
                            <p class="text-sm font-bold text-[#0A2E2A]">No orders match these filters</p>
                            <p class="mt-1 text-sm text-slate-500">Try clearing one or both filters.</p>
                        </div>
                    @endforelse
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
