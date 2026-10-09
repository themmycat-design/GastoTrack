<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Business overview | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
@php
    $monthRevenue = (float) ($totalRevenue ?? 0);
    $monthExpenses = (float) ($totalExpenses ?? 0);
    $flowScale = max($monthRevenue, $monthExpenses, 1);
    $revenueWidth = $monthRevenue > 0 ? max(4, round(($monthRevenue / $flowScale) * 100)) : 0;
    $expensesWidth = $monthExpenses > 0 ? max(4, round(($monthExpenses / $flowScale) * 100)) : 0;
@endphp

<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')

    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')

        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section class="gt-enter flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold text-[#00A87E]">{{ $monthLabel }} · Business performance</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Your shop, at a glance</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">A clear view of money movement, inventory value, and the items that need attention.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('transactions.index') }}" class="gt-focus inline-flex items-center gap-2 rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#08745D]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Transactions
                    </a>
                    <a href="{{ route('stock.index') }}" class="gt-focus inline-flex items-center gap-2 rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-sm font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 12v8.5"/></svg>
                        Inventory
                    </a>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Business metrics">
                @php
                    $metrics = [
                        ['label' => 'Revenue', 'value' => $monthRevenue, 'caption' => 'Income recorded this month', 'tone' => 'income', 'icon' => 'revenue'],
                        ['label' => 'Expenses', 'value' => $monthExpenses, 'caption' => 'Spending recorded this month', 'tone' => 'expense', 'icon' => 'expense'],
                        ['label' => 'Net profit', 'value' => $netProfit ?? ($monthRevenue - $monthExpenses), 'caption' => 'Revenue less expenses', 'tone' => ($netProfit ?? ($monthRevenue - $monthExpenses)) >= 0 ? 'income' : 'expense', 'icon' => 'profit'],
                        ['label' => 'Inventory value', 'value' => $totalStockValue ?? 0, 'caption' => number_format($totalProducts ?? 0).' active products · '.number_format($totalStaff ?? 0).' staff', 'tone' => 'inventory', 'icon' => 'inventory'],
                    ];
                @endphp
                @foreach($metrics as $index => $metric)
                    @php
                        $tone = match ($metric['tone']) {
                            'expense' => ['icon' => 'bg-rose-50 text-rose-600', 'amount' => 'text-rose-600', 'top' => 'bg-rose-400'],
                            'inventory' => ['icon' => 'bg-[#EAF8F2] text-[#08745D]', 'amount' => 'text-[#08745D]', 'top' => 'bg-[#00C897]'],
                            default => ['icon' => 'bg-[#EAF8F2] text-[#08745D]', 'amount' => 'text-[#08745D]', 'top' => 'bg-[#00C897]'],
                        };
                    @endphp
                    <article class="gt-card gt-lift gt-enter gt-enter-{{ min($index + 1, 4) }} relative overflow-hidden p-5 sm:p-6">
                        <span class="absolute inset-x-0 top-0 h-1 {{ $tone['top'] }}"></span>
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-500">{{ $metric['label'] }}</p>
                                <p class="mt-3 break-words text-2xl font-extrabold tracking-tight {{ $tone['amount'] }} sm:text-3xl">&#8369;{{ number_format((float) $metric['value'], 2) }}</p>
                                <p class="mt-2 text-xs leading-5 text-slate-400">{{ $metric['caption'] }}</p>
                            </div>
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $tone['icon'] }}">
                                @if($metric['icon'] === 'revenue')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 16 5-5 4 4 7-8"/><path d="M14 7h6v6"/></svg>
                                @elseif($metric['icon'] === 'expense')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 8 5 5 4-4 7 8"/><path d="M14 17h6v-6"/></svg>
                                @elseif($metric['icon'] === 'profit')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5"/><path d="M7.5 14h3m3-4h3M7 9h.01M17 15h.01"/></svg>
                                @else
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 12v8.5"/></svg>
                                @endif
                            </span>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
                <article class="gt-card gt-enter gt-enter-2 p-5 sm:p-6">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Cash flow</p>
                            <h3 class="mt-1 text-lg font-extrabold text-[#0A2E2A]">Income and spending</h3>
                            <p class="mt-1 text-sm text-slate-500">A side-by-side view for {{ $monthLabel }}.</p>
                        </div>
                        <a href="{{ route('analytics.index') }}" class="gt-focus inline-flex items-center gap-1 self-start text-sm font-bold text-[#08745D] hover:underline">Open analytics <span aria-hidden="true">&#8594;</span></a>
                    </div>

                    <div class="mt-7 space-y-5">
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                <span class="inline-flex items-center gap-2 font-semibold text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-[#00C897]"></span>Revenue</span>
                                <span class="font-extrabold text-[#08745D]">&#8369;{{ number_format($monthRevenue, 2) }}</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-[#EAF2EF]" role="meter" aria-label="Revenue compared with the larger monthly cash-flow total" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $revenueWidth }}">
                                <div class="h-full rounded-full bg-[#00C897] transition-all duration-700" style="width: {{ $revenueWidth }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                <span class="inline-flex items-center gap-2 font-semibold text-slate-600"><span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>Expenses</span>
                                <span class="font-extrabold text-rose-600">&#8369;{{ number_format($monthExpenses, 2) }}</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-[#EAF2EF]" role="meter" aria-label="Expenses compared with the larger monthly cash-flow total" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $expensesWidth }}">
                                <div class="h-full rounded-full bg-rose-400 transition-all duration-700" style="width: {{ $expensesWidth }}%"></div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-5 border-t border-[#EAF2EF] pt-4 text-xs leading-5 text-slate-400">Bars compare each total with the larger of the two monthly amounts.</p>
                </article>

                <article class="gt-card gt-enter gt-enter-3 overflow-hidden">
                    <div class="flex items-start justify-between gap-4 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Inventory watch</p>
                            <h3 class="mt-1 text-lg font-extrabold text-[#0A2E2A]">Needs attention</h3>
                            <p class="mt-1 text-sm text-slate-500">Items at or below their minimum.</p>
                        </div>
                        <span class="shrink-0 rounded-full {{ $lowStockCount > 0 ? 'bg-amber-50 text-amber-700' : 'bg-[#EAF8F2] text-[#08745D]' }} px-3 py-1.5 text-xs font-extrabold">{{ $lowStockCount }} {{ $lowStockCount === 1 ? 'alert' : 'alerts' }}</span>
                    </div>
                    <div class="px-5 py-2 sm:px-6">
                        @forelse($lowStockItems as $item)
                            @php
                                $stockLevel = (float) $item->minimum_quantity > 0
                                    ? min(100, max(5, round(((float) $item->current_quantity / (float) $item->minimum_quantity) * 100)))
                                    : ((float) $item->current_quantity > 0 ? 100 : 0);
                            @endphp
                            <div class="border-b border-[#EAF2EF] py-4 last:border-0">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-[#0A2E2A]">{{ $item->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Minimum {{ number_format($item->minimum_quantity, 2) }} {{ $item->unit }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-lg {{ (float) $item->current_quantity <= 0 ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1.5 text-xs font-bold">{{ number_format($item->current_quantity, 2) }} {{ $item->unit }}</span>
                                </div>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#EAF2EF]"><div class="h-full rounded-full {{ (float) $item->current_quantity <= 0 ? 'bg-rose-400' : 'bg-amber-400' }}" style="width: {{ $stockLevel }}%"></div></div>
                            </div>
                        @empty
                            <div class="py-8 text-center">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/><circle cx="12" cy="12" r="9"/></svg>
                                </span>
                                <p class="mt-3 text-sm font-bold text-[#08745D]">Inventory is healthy</p>
                                <p class="mt-1 text-xs text-slate-500">No items are below their minimum level.</p>
                            </div>
                        @endforelse
                    </div>
                    <div class="px-5 pb-5 sm:px-6"><a href="{{ route('stock.index') }}" class="gt-focus block rounded-xl bg-[#EAF8F2] px-4 py-3 text-center text-sm font-bold text-[#08745D] transition hover:bg-[#DDF4EA]">Review inventory</a></div>
                </article>
            </section>

            <section class="gt-card gt-enter gt-enter-4 overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Latest activity</p>
                        <h3 class="mt-1 text-lg font-extrabold text-[#0A2E2A]">Recent transactions</h3>
                        <p class="mt-1 text-sm text-slate-500">The latest income and expenses recorded by your team.</p>
                    </div>
                    <a href="{{ route('transactions.index') }}" class="gt-focus shrink-0 rounded-lg px-2 py-2 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">View all <span aria-hidden="true">&#8594;</span></a>
                </div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($recentTransactions as $transaction)
                        @php $isIncome = strtolower($transaction->type) === 'income'; @endphp
                        <div class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-[#FBFDFC] sm:px-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isIncome ? 'bg-[#EAF8F2] text-[#08745D]' : 'bg-rose-50 text-rose-600' }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        @if($isIncome)<path d="M12 19V5m0 0L6 11m6-6 6 6"/>@else<path d="M12 5v14m0 0 6-6m-6 6-6-6"/>@endif
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-[#0A2E2A]">{{ $transaction->category ?: 'Uncategorized' }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ optional($transaction->transaction_date ?? $transaction->created_at)->format('M d, Y') }} <span class="px-1 text-slate-300">·</span> {{ ucfirst($transaction->source ?? 'Unknown source') }}</p>
                                </div>
                            </div>
                            <p class="shrink-0 text-sm font-extrabold {{ $isIncome ? 'text-[#08745D]' : 'text-rose-600' }}">{{ $isIncome ? '+' : '-' }}&#8369;{{ number_format($transaction->amount, 2) }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1-1.5Z"/><path d="M14 4v4h4M9 13h6M9 16.5h6"/></svg>
                            </span>
                            <p class="mt-3 text-sm font-bold text-[#0A2E2A]">No transactions yet</p>
                            <p class="mt-1 text-sm text-slate-500">When staff record a sale or expense, it will appear here.</p>
                            <a href="{{ route('transactions.index') }}" class="gt-focus mt-4 inline-flex items-center gap-2 rounded-xl bg-[#EAF8F2] px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#DDF4EA]">Open transactions <span aria-hidden="true">&#8594;</span></a>
                        </div>
                    @endforelse
                </div>
            </section>

            @if($recentTransactions->isEmpty() && (int) ($totalProducts ?? 0) === 0)
                <section class="gt-card gt-enter overflow-hidden p-6 sm:p-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Getting started</p>
                            <h3 class="mt-2 text-xl font-extrabold text-[#0A2E2A]">Set up your shop’s daily workflow</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Add ingredients and products, invite staff, then use real sales and expense records to build a useful business overview.</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a href="{{ route('stock.index') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-3 text-sm font-bold text-white hover:bg-[#08745D]">Set up inventory</a>
                            <a href="{{ route('staff.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-3 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Manage staff</a>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </main>
</div>
</body>
</html>
