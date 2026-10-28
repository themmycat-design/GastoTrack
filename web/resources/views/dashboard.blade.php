<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @if(session('owner.dashboard_loading'))
        <script>document.documentElement.classList.add('gt-owner-dashboard-loading', 'gt-owner-overview-waiting');</script>
    @endif
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $business->name }} overview | GastoTrack</title>
    @include('layouts.owner-fonts')
    <style>
        .gt-owner-dashboard-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at 50% 43%, rgba(0, 200, 151, .13), transparent 18rem),
                linear-gradient(145deg, #fff 0%, #f4fbf8 65%, #eaf8f2 100%);
            opacity: 0;
            visibility: hidden;
            transition: opacity 220ms ease, visibility 220ms ease;
        }

        html.gt-owner-dashboard-loading,
        html.gt-owner-dashboard-loading body { overflow: hidden; }

        html.gt-owner-dashboard-loading .gt-owner-dashboard-loader {
            opacity: 1;
            visibility: visible;
        }

        .gt-owner-dashboard-loader-content {
            display: flex;
            width: min(90vw, 360px);
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .gt-owner-dashboard-loader-logo {
            width: min(48vw, 156px);
            height: min(48vw, 156px);
            object-fit: contain;
            animation: gt-owner-logo-breathe 1.5s ease-in-out infinite;
            filter: drop-shadow(0 12px 20px rgba(0, 128, 98, .15));
        }

        .gt-owner-dashboard-loader-title {
            margin-top: 1.1rem;
            color: #0a6653;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .gt-owner-dashboard-loader-caption {
            margin-top: .35rem;
            color: #647b75;
            font-size: .9rem;
        }

        .gt-owner-dashboard-loader-track {
            width: 132px;
            height: 4px;
            margin-top: 1.4rem;
            overflow: hidden;
            border-radius: 999px;
            background: #dceee8;
        }

        .gt-owner-dashboard-loader-progress {
            width: 45%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #00a87e, #00c897);
            transform-origin: left center;
            animation: gt-owner-loader-progress 1.15s ease-in-out infinite;
        }

        @keyframes gt-owner-logo-breathe {
            0%, 100% { transform: translateY(0) scale(.97); }
            50% { transform: translateY(-5px) scale(1.03); }
        }

        @keyframes gt-owner-loader-progress {
            0% { transform: translateX(-110%) scaleX(.55); }
            55% { transform: translateX(75%) scaleX(1.35); }
            100% { transform: translateX(250%) scaleX(.65); }
        }

        @media (prefers-reduced-motion: reduce) {
            .gt-owner-dashboard-loader,
            .gt-owner-dashboard-loader-logo,
            .gt-owner-dashboard-loader-progress { animation: none; transition-duration: 1ms; }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page gt-owner-overview min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@if(session('owner.dashboard_loading'))
<div class="gt-owner-dashboard-loader" role="status" aria-live="polite" aria-label="Loading owner dashboard">
    <div class="gt-owner-dashboard-loader-content">
        <img class="gt-owner-dashboard-loader-logo" src="{{ asset('images/gastotrack-logo.png') }}" alt="GastoTrack logo">
        <p class="gt-owner-dashboard-loader-title">GastoTrack</p>
        <p class="gt-owner-dashboard-loader-caption">Preparing your dashboard</p>
        <div class="gt-owner-dashboard-loader-track" aria-hidden="true">
            <div class="gt-owner-dashboard-loader-progress"></div>
        </div>
    </div>
</div>
@endif
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
            <section class="gt-owner-overview-banner gt-enter space-y-5 p-5 sm:p-6">
                <div data-overview-reveal data-overview-step="0" class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div data-owner-motion-child>
                    <p class="text-sm font-semibold text-[#00A87E]">{{ $periodLabel }}</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Overview</h2>
                    <p class="mt-2 text-sm text-slate-500">Your finances and priorities in one place.</p>
                </div>
                @unless($needsSetup)
                <a data-owner-motion-child href="{{ route('products.index') }}" class="gt-focus inline-flex items-center gap-2 rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#08745D]">Manage products</a>
                @endunless
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <a data-overview-reveal data-overview-step="1" href="{{ route('goals.index') }}" aria-label="Monthly sales completed, {{ $currencyPrefix.number_format((float) ($monthlyGoal['current_amount'] ?? 0), 2) }}" class="gt-owner-goal-link gt-focus">
                        <span data-owner-motion-child>Monthly sales goal</span>
                        <span data-owner-motion-child class="text-right">
                            <strong class="block"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) ($monthlyGoal['current_amount'] ?? 0) }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) ($monthlyGoal['current_amount'] ?? 0), 2) }}</span></strong>
                            <span class="mt-0.5 block text-xs font-medium text-slate-500">of <span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) ($monthlyGoal['target_amount'] ?? 0) }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) ($monthlyGoal['target_amount'] ?? 0), 2) }}</span> goal</span>
                        </span>
                    </a>
                    <a data-overview-reveal data-overview-step="2" href="{{ route('goals.index') }}" aria-label="Weekly sales completed, {{ $currencyPrefix.number_format((float) ($weeklyGoal['current_amount'] ?? 0), 2) }}" class="gt-owner-goal-link gt-focus">
                        <span data-owner-motion-child>Weekly sales goal</span>
                        <span data-owner-motion-child class="text-right">
                            <strong class="block"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) ($weeklyGoal['current_amount'] ?? 0) }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) ($weeklyGoal['current_amount'] ?? 0), 2) }}</span></strong>
                            <span class="mt-0.5 block text-xs font-medium text-slate-500">of <span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) ($weeklyGoal['target_amount'] ?? 0) }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) ($weeklyGoal['target_amount'] ?? 0), 2) }}</span> goal</span>
                        </span>
                    </a>
                </div>
            </section>
            <div class="{{ $needsSetup ? '' : 'grid items-start gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]' }}">
                <div class="min-w-0 space-y-6">
            <section aria-labelledby="financial-summary-heading">
                <div data-overview-reveal data-overview-step="2" class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div data-owner-motion-child>
                        <h3 id="financial-summary-heading" class="text-lg font-extrabold text-[#0A2E2A]">Financial snapshot</h3>
                        <p class="mt-1 text-xs text-slate-500">Ledger month to date · order sales today · {{ $currencyCode }}</p>
                    </div>
                    <a href="{{ route('analytics.index') }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">Open analytics <span aria-hidden="true">→</span></a>
                </div>
                @php
                    $snapshotIncome = max(0, (float) ($ledger['income'] ?? 0));
                    $snapshotExpenses = max(0, (float) ($ledger['expenses'] ?? 0));
                    $snapshotActivity = $snapshotIncome + $snapshotExpenses;
                    $snapshotNet = (float) ($ledger['netRecordedMovement'] ?? ($snapshotIncome - $snapshotExpenses));
                    $monthlySalesTarget = max(0, (float) ($monthlyGoal['target_amount'] ?? 0));
                    $todaySalesValue = max(0, (float) $todaySales);
                    $ledgerIncomeShare = $snapshotActivity > 0 ? $snapshotIncome / $snapshotActivity * 100 : 0;
                    $ledgerExpenseShare = $snapshotActivity > 0 ? $snapshotExpenses / $snapshotActivity * 100 : 0;
                    $ledgerNetShare = $snapshotActivity > 0 ? abs($snapshotNet) / $snapshotActivity * 100 : 0;
                    $todayGoalShare = $monthlySalesTarget > 0 ? min(100, $todaySalesValue / $monthlySalesTarget * 100) : null;
                @endphp
                <div class="grid gap-4 sm:grid-cols-2">
                    @php
                        $financialCards = [
                            ['label' => 'Recorded income', 'value' => $ledger['income'], 'caption' => 'All income entered this month', 'tone' => 'green', 'donut' => $ledgerIncomeShare, 'donutLabel' => 'of monthly ledger activity'],
                            ['label' => 'Recorded expenses', 'value' => $ledger['expenses'], 'caption' => 'All expenses entered this month', 'tone' => 'rose', 'donut' => $ledgerExpenseShare, 'donutLabel' => 'of monthly ledger activity'],
                            ['label' => 'Net movement', 'value' => $ledger['netRecordedMovement'], 'caption' => 'Income minus expenses; not profit', 'tone' => $ledger['netRecordedMovement'] < 0 ? 'rose' : 'green', 'donut' => $ledgerNetShare, 'donutLabel' => 'of monthly ledger activity'],
                            ['label' => "Today's confirmed sales", 'value' => $todaySales, 'caption' => 'Completed and payment-confirmed orders today', 'tone' => 'green', 'donut' => $todayGoalShare, 'donutLabel' => $monthlySalesTarget > 0 ? 'of monthly sales goal' : 'monthly sales goal not set'],
                        ];
                    @endphp
                    @foreach($financialCards as $index => $card)
                        <article data-overview-reveal data-overview-step="{{ min($index + 3, 6) }}" class="gt-card gt-lift gt-enter gt-enter-{{ min($index + 1, 4) }} relative overflow-hidden p-4 sm:p-5">
                            <span class="absolute inset-x-0 top-0 h-1 {{ $card['tone'] === 'rose' ? 'bg-rose-400' : 'bg-[#00C897]' }}"></span>
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p data-owner-motion-child class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</p>
                                    <p data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $card['value'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2" class="mt-2 break-words text-xl font-extrabold tracking-tight {{ $card['tone'] === 'rose' ? 'text-rose-600' : 'text-[#08745D]' }} sm:text-2xl">{{ $currencyPrefix }}{{ number_format((float) $card['value'], 2) }}</p>
                                    <p data-owner-motion-child class="mt-1 text-xs leading-5 text-slate-400">{{ $card['caption'] }}</p>
                                </div>
                                <div data-owner-motion-child class="gt-snapshot-donut relative h-16 w-16 shrink-0" role="img" aria-label="{{ $card['label'] }}: {{ $currencyPrefix }}{{ number_format(abs((float) $card['value']), 2) }}; {{ $card['donutLabel'] }}{{ $card['donut'] === null ? '' : ', '.number_format($card['donut'], 1).' percent' }}">
                                    <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                                        <circle class="gt-snapshot-donut-track" cx="50" cy="50" r="40" fill="none" stroke-width="10" />
                                        @if($card['donut'] !== null && $card['donut'] > 0)
                                            <circle class="gt-snapshot-donut-value {{ $card['tone'] === 'rose' ? 'gt-snapshot-donut-negative' : '' }}" cx="50" cy="50" r="40" fill="none" stroke-width="10" pathLength="100" stroke-dasharray="{{ number_format($card['donut'], 2, '.', '') }} 100" />
                                        @endif
                                    </svg>
                                    <span class="absolute inset-0 flex items-center justify-center text-[10px] font-extrabold text-[#0A2E2A]">{{ $card['donut'] === null ? '—' : number_format($card['donut'], 0).'%' }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            <section data-overview-reveal data-overview-step="6" data-overview-static-value class="gt-card overflow-hidden" aria-labelledby="completed-orders-heading">
                <div data-owner-motion-child class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EAF2EF] px-5 py-4 sm:px-6">
                    <h3 data-owner-motion-child id="completed-orders-heading" class="font-extrabold text-[#0A2E2A]">Completed orders</h3>
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
                        <a data-owner-motion-child href="{{ route('orders.show', $order->id) }}" class="gt-focus flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-[#F8FCFA] sm:px-6">
                            <span data-owner-motion-child class="min-w-0">
                                <span class="block text-sm font-bold text-[#0A2E2A]">{{ $order->customer_name ?: 'Walk-in order' }} <span class="text-xs font-bold text-[#08745D]">· {{ $order->order_number }}</span></span>
                                <span class="mt-1 block text-xs text-slate-500">{{ $order->completed_at?->format('M j, Y · g:i A') ?: $order->created_at?->format('M j, Y · g:i A') }} · {{ $paymentLabel }}</span>
                            </span>
                            <span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $order->total }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2" class="shrink-0 text-sm font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $order->total, 2) }}</span>
                        </a>
                    @empty
                        <p data-owner-motion-child class="px-5 py-6 text-sm text-slate-500 sm:px-6">No completed orders yet.</p>
                    @endforelse
                </div>
            </section>

                </div>

            @if(!$needsSetup)
            <section data-overview-reveal data-overview-step="6" data-overview-static-value class="gt-card gt-enter min-w-0 overflow-hidden">
                <div data-owner-motion-child class="flex items-center justify-between gap-4 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
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
                        <div data-owner-motion-child class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-[#FBFDFC] sm:px-6 xl:flex-col xl:items-stretch xl:gap-2 xl:[&>p]:text-right">
                            <div data-owner-motion-child class="min-w-0">
                                <p class="truncate text-sm font-bold text-[#0A2E2A]">{{ $transaction->category ?: 'Uncategorized' }}{{ $isReversal ? ' · Reversal' : '' }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ optional($transaction->transaction_date ?? $transaction->created_at)->format('M j, Y') }} · {{ ucfirst($transaction->source ?? 'Unknown source') }} · {{ $transaction->user?->name ?? 'Staff account unavailable' }}</p>
                                @if($transaction->description)
                                    <p class="mt-1 truncate text-xs text-slate-400">{{ $transaction->description }}</p>
                                @endif
                            </div>
                            <p data-owner-motion-child class="shrink-0 text-sm font-extrabold {{ $isIncome ? 'text-[#08745D]' : 'text-rose-600' }}">{{ $isIncome ? '+' : '−' }}<span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ abs((float) $transaction->amount) }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format(abs((float) $transaction->amount), 2) }}</span></p>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <p data-owner-motion-child class="text-sm font-bold text-[#0A2E2A]">No transactions yet</p>
                            <p data-owner-motion-child class="mt-1 text-sm text-slate-500">Income and expenses recorded by staff will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </section>
            @endif

            </div>

            @if($needsSetup)
                <section data-overview-reveal data-overview-step="6" class="gt-card gt-enter p-6 sm:p-8">
                    <p data-owner-motion-child class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Getting started</p>
                    <h3 data-owner-motion-child class="mt-2 text-xl font-extrabold text-[#0A2E2A]">Set up your shop's daily workflow</h3>
                    <p data-owner-motion-child class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Add your menu products and stock items, invite staff, then use real orders and recorded expenses to build a useful business overview.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a data-owner-motion-child href="{{ route('products.index') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-3 text-sm font-bold text-white hover:bg-[#08745D]">Set up products</a>
                        <a data-owner-motion-child href="{{ route('stock.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-3 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Set up inventory</a>
                        <a data-owner-motion-child href="{{ route('staff.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-3 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Manage staff</a>
                    </div>
                </section>
            @endif
        </div>
    </main>
</div>
@if(session('owner.dashboard_loading'))
<script>
    (() => {
        const root = document.documentElement;
        let dismissalScheduled = false;

        const dismissLoader = () => {
            if (dismissalScheduled) return;
            dismissalScheduled = true;

            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            root.classList.remove('gt-owner-dashboard-loading');
            window.setTimeout(() => {
                root.classList.remove('gt-owner-overview-waiting');
                window.dispatchEvent(new Event('gastotrack:overview-ready'));
            }, reducedMotion ? 0 : 220);
        };

        if (document.readyState === 'complete') dismissLoader();
        else window.addEventListener('load', dismissLoader, { once: true });

        window.setTimeout(dismissLoader, 4500);
    })();
</script>
@endif
</body>
</html>
