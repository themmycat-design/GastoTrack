<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>{{ $order->order_number ?: 'Order #'.$order->id }} | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php
    $currencyCode = strtoupper((string) (Auth::user()->business?->currency ?: 'PHP'));
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ';
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
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1100px] space-y-5 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <a href="{{ route('orders.index') }}" class="gt-focus inline-flex items-center gap-2 text-sm font-bold text-[#08745D] hover:underline">← Back to orders</a>

            <section class="gt-card p-5 sm:p-7">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Order details</p>
                        <h2 class="mt-1 break-all text-2xl font-extrabold tracking-tight">{{ $order->order_number ?: 'Order #'.$order->id }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ optional($order->created_at)->format('M j, Y · g:i A') }} · {{ $order->creator?->name ?? 'Staff account unavailable' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">Order: {{ \Illuminate\Support\Str::headline($order->status) }}</span>
                        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $paymentTone }}">Payment: {{ $paymentLabel }}</span>
                    </div>
                </div>

                @if($paymentStatus === 'detected')
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        A matching wallet notification was detected, but it is not provider verification. This payment remains unconfirmed until an authorized staff member checks it.
                    </div>
                @elseif($paymentStatus === 'refund_pending')
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        A refund was requested and is awaiting manual confirmation. This page does not claim that money has already been returned.
                    </div>
                @endif

                <dl class="mt-6 grid gap-4 border-t border-[#EAF2EF] pt-5 sm:grid-cols-3">
                    <div><dt class="text-xs font-semibold text-slate-500">Customer</dt><dd class="mt-1 text-sm font-bold">{{ $order->customer_name ?: 'Walk-in Customer' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Payment method</dt><dd class="mt-1 text-sm font-bold">{{ $order->payment_method ? strtoupper($order->payment_method) : 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Currency</dt><dd class="mt-1 text-sm font-bold">{{ $order->payment_currency ?: $currencyCode }}</dd></div>
                    @if($order->payment_confirmed_at)
                        <div><dt class="text-xs font-semibold text-slate-500">Confirmed at</dt><dd class="mt-1 text-sm font-bold">{{ $order->payment_confirmed_at->format('M j, Y · g:i A') }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-500">Confirmed by</dt><dd class="mt-1 text-sm font-bold">{{ $order->paymentConfirmer?->name ?? 'Staff account unavailable' }}</dd></div>
                    @endif
                    @if($order->payment_reference)
                        <div><dt class="text-xs font-semibold text-slate-500">Payment reference</dt><dd class="mt-1 break-all text-sm font-bold">{{ $order->payment_reference }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="gt-card overflow-hidden">
                <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6"><h3 class="font-extrabold">Items</h3></div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($order->items as $item)
                        <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
                            <div class="min-w-0"><p class="truncate text-sm font-bold">{{ $item->product_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->quantity }} × {{ $currencyPrefix }}{{ number_format((float) $item->price, 2) }}</p></div>
                            <p class="shrink-0 text-sm font-bold">{{ $currencyPrefix }}{{ number_format((float) $item->subtotal, 2) }}</p>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-slate-500">No item snapshot was saved for this order.</p>
                    @endforelse
                </div>
                <dl class="space-y-2 border-t border-[#EAF2EF] bg-[#FBFDFC] px-5 py-4 sm:px-6">
                    <div class="flex justify-between gap-4 text-sm"><dt class="text-slate-500">Subtotal</dt><dd class="font-semibold">{{ $currencyPrefix }}{{ number_format((float) $order->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between gap-4 text-sm"><dt class="text-slate-500">Discount</dt><dd class="font-semibold">−{{ $currencyPrefix }}{{ number_format((float) $order->discount, 2) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-[#EAF2EF] pt-3 text-base"><dt class="font-extrabold">Order total</dt><dd class="font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $order->total, 2) }}</dd></div>
                </dl>
            </section>

            @if($order->paymentEvents->isNotEmpty())
                <section class="gt-card overflow-hidden">
                    <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6"><h3 class="font-extrabold">Payment and correction history</h3><p class="mt-1 text-xs text-slate-500">Recorded status changes and the actor where available.</p></div>
                    <div class="divide-y divide-[#EAF2EF]">
                        @foreach($order->paymentEvents as $event)
                            <div class="px-5 py-4 sm:px-6">
                                <p class="text-sm font-bold">{{ \Illuminate\Support\Str::headline($event->event_type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ optional($event->created_at)->format('M j, Y · g:i A') }} · {{ $event->actor?->name ?? 'System or unavailable account' }}</p>
                                @if($event->from_payment_status || $event->to_payment_status)
                                    <p class="mt-1 text-xs text-slate-600">Payment: {{ $event->from_payment_status ? \Illuminate\Support\Str::headline($event->from_payment_status) : '—' }} → {{ $event->to_payment_status ? \Illuminate\Support\Str::headline($event->to_payment_status) : '—' }}</p>
                                @endif
                                @if($event->reason)<p class="mt-1 text-xs leading-5 text-slate-600">{{ $event->reason }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </main>
</div>
</body>
</html>
