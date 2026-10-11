<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $business->name }} overview | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
@php
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ';
    $ledger = $ledger ?? ['income' => 0, 'expenses' => 0, 'netRecordedMovement' => 0];
    $periodLabel = $periodStart->format('M j').'–'.$periodEnd->format('M j, Y');
    $needsSetup = $activeProductCount === 0 && $activeStockCount === 0 && $recentTransactions->isEmpty();
@endphp

<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')

    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')

        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section class="gt-enter flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold text-[#00A87E]">{{ $periodLabel }}</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Overview</h2>
                    <p class="mt-2 text-sm text-slate-500">Your finances and priorities in one place.</p>
                </div>
                @unless($needsSetup)
                <a href="{{ route('products.index') }}" class="gt-focus inline-flex items-center gap-2 rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#08745D]">Manage products</a>
                @endunless
            </section>
            <section class="gt-card gt-enter flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6" aria-label="Monthly confirmed sales goal">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Monthly sales goal</p>
                    @if($monthlyGoal)
                        <p class="mt-2 text-xl font-extrabold text-[#0A2E2A]">{{ $currencyPrefix }}{{ number_format((float) $monthlyGoal['current_amount'], 2) }} <span class="text-sm font-medium text-slate-500">of {{ $currencyPrefix }}{{ number_format((float) $monthlyGoal['target_amount'], 2) }}</span></p>
                        <p class="mt-1 text-xs text-slate-500">{{ $monthlyGoal['target_reached'] ? 'Target reached' : number_format($monthlyGoal['progress_percentage'], 1).'% complete' }} · Confirmed, completed orders only</p>
                    @else
                        <p class="mt-2 text-sm text-slate-500">Set a target for {{ $periodStart->format('F') }} and track confirmed sales automatically.</p>
                    @endif
                </div>
                <a href="{{ route('goals.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">{{ $monthlyGoal ? 'View goal' : 'Set target' }} →</a>
            </section>

            <div class="{{ $needsSetup ? '' : 'grid items-start gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]' }}">
                <div class="min-w-0 space-y-6">
            <section aria-labelledby="financial-summary-heading">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 id="financial-summary-heading" class="text-lg font-extrabold text-[#0A2E2A]">Financial snapshot</h3>
                        <p class="mt-1 text-xs text-slate-500">Ledger month to date · order sales today · {{ $currencyCode }}</p>
                    </div>
                    <a href="{{ route('analytics.index') }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">Open analytics <span aria-hidden="true">→</span></a>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @php
                        $financialCards = [
                            ['label' => 'Recorded income', 'value' => $ledger['income'], 'caption' => 'All income entered this month', 'tone' => 'green'],
                            ['label' => 'Recorded expenses', 'value' => $ledger['expenses'], 'caption' => 'All expenses entered this month', 'tone' => 'rose'],
                            ['label' => 'Net movement', 'value' => $ledger['netRecordedMovement'], 'caption' => 'Income minus expenses; not profit', 'tone' => $ledger['netRecordedMovement'] < 0 ? 'rose' : 'green'],
                            ['label' => "Today's confirmed sales", 'value' => $todaySales, 'caption' => 'Completed and payment-confirmed orders today', 'tone' => 'green'],
                        ];
                    @endphp
                    @foreach($financialCards as $index => $card)
                        <article class="gt-card gt-lift gt-enter gt-enter-{{ min($index + 1, 4) }} relative overflow-hidden p-5 sm:p-6">
                            <span class="absolute inset-x-0 top-0 h-1 {{ $card['tone'] === 'rose' ? 'bg-rose-400' : 'bg-[#00C897]' }}"></span>
                            <p class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</p>
                            <p class="mt-3 break-words text-2xl font-extrabold tracking-tight {{ $card['tone'] === 'rose' ? 'text-rose-600' : 'text-[#08745D]' }} sm:text-3xl">{{ $currencyPrefix }}{{ number_format((float) $card['value'], 2) }}</p>
                            <p class="mt-2 text-xs leading-5 text-slate-400">{{ $card['caption'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
            <section class="gt-card overflow-hidden" aria-labelledby="completed-orders-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EAF2EF] px-5 py-4 sm:px-6">
                    <h3 id="completed-orders-heading" class="font-extrabold text-[#0A2E2A]">Completed orders</h3>
                    <a href="{{ route('orders.index', ['status' => 'completed']) }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">View all →</a>
                </div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($recentCompletedOrders as $order)
                        @php
                            $paymentLabel = match ($order->payment_status) {
                                'confirmed' => 'Payment confirmed',
                                'detected' => 'Payment awaiting verification',
                                'refund_pending' => 'Refund pending',
                                'refunded' => 'Refunded',
                                default => 'Payment '.\Illuminate\Support\Str::headline($order->payment_status ?: 'pending'),
                            };
                        @endphp
                        <a href="{{ route('orders.show', $order->id) }}" class="gt-focus flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-[#F8FCFA] sm:px-6">
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-[#0A2E2A]">{{ $order->customer_name ?: 'Walk-in order' }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ $order->completed_at?->format('M j, Y · g:i A') ?: $order->created_at?->format('M j, Y · g:i A') }} · {{ $paymentLabel }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $order->total, 2) }}</span>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">No completed orders yet.</p>
                    @endforelse
                </div>
            </section>

                </div>

            @if(!$needsSetup)
            <section class="gt-card gt-enter min-w-0 overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <div>
                        <h3 class="text-lg font-extrabold text-[#0A2E2A]">Recent transactions</h3>
                    </div>
                    <a href="{{ route('transactions.index') }}" class="gt-focus shrink-0 rounded-lg px-2 py-2 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">View all →</a>
                </div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($recentTransactions as $transaction)
                        @php
                            $isIncome = strtolower($transaction->type) === 'income';
                            $isReversal = $transaction->entry_method === 'order_reversal' || $transaction->reversal_of_id !== null;
                        @endphp
                        <div class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-[#FBFDFC] sm:px-6 xl:flex-col xl:items-stretch xl:gap-2 xl:[&>p]:text-right">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-[#0A2E2A]">{{ $transaction->category ?: 'Uncategorized' }}{{ $isReversal ? ' · Reversal' : '' }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ optional($transaction->transaction_date ?? $transaction->created_at)->format('M j, Y') }} · {{ ucfirst($transaction->source ?? 'Unknown source') }} · {{ $transaction->user?->name ?? 'Staff account unavailable' }}</p>
                                @if($transaction->description)
                                    <p class="mt-1 truncate text-xs text-slate-400">{{ $transaction->description }}</p>
                                @endif
                            </div>
                            <p class="shrink-0 text-sm font-extrabold {{ $isIncome ? 'text-[#08745D]' : 'text-rose-600' }}">{{ $isIncome ? '+' : '−' }}{{ $currencyPrefix }}{{ number_format((float) $transaction->amount, 2) }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <p class="text-sm font-bold text-[#0A2E2A]">No transactions yet</p>
                            <p class="mt-1 text-sm text-slate-500">Income and expenses recorded by staff will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </section>
            @endif

            </div>

            @if($needsSetup)
                <section class="gt-card gt-enter p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Getting started</p>
                    <h3 class="mt-2 text-xl font-extrabold text-[#0A2E2A]">Set up your shop's daily workflow</h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Add your menu products and stock items, invite staff, then use real orders and recorded expenses to build a useful business overview.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('products.index') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-3 text-sm font-bold text-white hover:bg-[#08745D]">Set up products</a>
                        <a href="{{ route('stock.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-3 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Set up inventory</a>
                        <a href="{{ route('staff.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-3 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Manage staff</a>
                    </div>
                </section>
            @endif
        </div>
    </main>
</div>
</body>
</html>
