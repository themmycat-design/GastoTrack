<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <title>Analytics | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ';
    $current = $current ?? ['income' => 0, 'expenses' => 0, 'netRecordedMovement' => 0, 'transactionCount' => 0];
    $comparison = $comparison ?? ['income' => 0, 'expenses' => 0, 'netRecordedMovement' => 0, 'transactionCount' => 0];
    $costInsights = $costInsights ?? ['ingredientCost' => 0, 'ingredientCostCoveragePercent' => 0, 'wasteCost' => 0, 'grossMargin' => 0, 'costedSales' => 0, 'costCoveragePercent' => 0, 'ingredientCostEstimated' => false, 'wasteCostEstimated' => false, 'wasteItems' => collect()];
    $metricCards = [
        ['key' => 'income', 'label' => 'Recorded income', 'amount' => $current['income'], 'growth' => $growth['income'] ?? null, 'goodUp' => true],
        ['key' => 'expenses', 'label' => 'Recorded expenses', 'amount' => $current['expenses'], 'growth' => $growth['expenses'] ?? null, 'goodUp' => false],
        ['key' => 'movement', 'label' => 'Net recorded movement', 'amount' => $current['netRecordedMovement'], 'growth' => null, 'goodUp' => true],
        ['key' => 'transactions', 'label' => 'Recorded transactions', 'amount' => $current['transactionCount'], 'growth' => $growth['transactions'] ?? null, 'goodUp' => true],
    ];
    $rangeLabel = $startDate->format('M j, Y').' – '.$endDate->format('M j, Y');
    $comparisonLabel = $comparisonStartDate->format('M j, Y').' – '.$comparisonEndDate->format('M j, Y');
    $comparisonPeriods = [
        'labels' => [$comparisonLabel, $rangeLabel],
        'income' => [$comparison['income'], $current['income']],
        'expenses' => [$comparison['expenses'], $current['expenses']],
        'movement' => [$comparison['netRecordedMovement'], $current['netRecordedMovement']],
    ];
    $hasCurrentData = $current['transactionCount'] > 0;
    $hasLedgerData = $hasCurrentData || $comparison['transactionCount'] > 0;
    $periodNames = ['week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'custom' => 'Custom range'];
    $chartPeriodName = $chartInterval === 'month' ? 'Monthly' : 'Daily';
@endphp
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section class="gt-enter flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                <div>
                    <p class="text-sm font-semibold text-[#00A87E]">{{ Auth::user()->business?->name }} · business performance</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Analytics</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Review recorded finances separately from estimated recipe costs, logged waste, and product margins.</p>
                </div>
                <nav class="inline-flex flex-wrap gap-1 self-start rounded-xl border border-[#DDE9E5] bg-white p-1 shadow-sm lg:self-auto" aria-label="Analytics period">
                    @foreach(['week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'] as $option => $label)
                        <a href="{{ route('analytics.index', ['period' => $option]) }}" aria-current="{{ $period === $option ? 'page' : 'false' }}" class="gt-focus rounded-lg px-4 py-2 text-sm font-bold transition {{ $period === $option ? 'bg-[#00A87E] text-white shadow-sm' : 'text-slate-600 hover:bg-[#EAF8F2] hover:text-[#08745D]' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </section>

            <section class="gt-card px-5 py-4 sm:px-6" aria-label="Reporting period">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-extrabold text-[#0A2E2A]">{{ $periodNames[$period] }} · {{ $rangeLabel }}</p>
                        <p class="mt-1 text-xs text-slate-500">Compared with {{ $comparisonLabel }} · amounts in {{ $currencyCode }}</p>
                    </div>
                    <details class="group relative w-full sm:w-auto">
                        <summary class="gt-focus inline-flex cursor-pointer list-none items-center rounded-xl border border-[#C8DED7] bg-[#F8FCFA] px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Custom dates</summary>
                        <form method="GET" action="{{ route('analytics.index') }}" class="mt-3 grid gap-3 sm:absolute sm:right-0 sm:z-20 sm:w-[420px] sm:grid-cols-2 sm:rounded-2xl sm:border sm:border-[#DDE9E5] sm:bg-white sm:p-4 sm:shadow-xl">
                            <input type="hidden" name="period" value="custom">
                            <div>
                                <label for="start_date" class="mb-1 block text-xs font-bold text-slate-600">From</label>
                                <input id="start_date" type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" max="{{ now()->toDateString() }}" required class="w-full px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label for="end_date" class="mb-1 block text-xs font-bold text-slate-600">To</label>
                                <input id="end_date" type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" max="{{ now()->toDateString() }}" required class="w-full px-3 py-2 text-sm">
                            </div>
                            <button class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#08745D] sm:col-span-2">Apply dates</button>
                        </form>
                    </details>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Financial metrics">
                @foreach($metricCards as $index => $card)
                    @php
                        $growth = $card['growth'];
                        $isFavorable = $growth !== null && ($card['goodUp'] ? $growth >= 0 : $growth <= 0);
                        $amount = $card['key'] === 'transactions'
                            ? number_format((int) $card['amount'])
                            : $currencyPrefix.number_format((float) $card['amount'], 2);
                    @endphp
                    <article class="gt-card gt-lift gt-enter gt-enter-{{ min($index + 1, 4) }} p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</p>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $card['key'] === 'expenses' ? 'bg-rose-50 text-rose-600' : 'bg-[#EAF8F2] text-[#08745D]' }}" aria-hidden="true">
                                @if($card['key'] === 'transactions')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M8 8h8M8 12h8"/></svg>
                                @elseif($card['key'] === 'movement')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 17 10 11l4 3 6-8"/><path d="M15 6h5v5"/></svg>
                                @elseif($card['key'] === 'expenses')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v12H4zM4 10h16M8 15h4"/></svg>
                                @else
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M15.5 9.5c-1-1-5-1-5 1 0 2 5 1 5 3 0 2-4 2-5.5 1M12 6v12"/></svg>
                                @endif
                            </span>
                        </div>
                        <p class="mt-3 break-words text-2xl font-extrabold tracking-tight {{ $card['key'] === 'expenses' || (in_array($card['key'], ['income', 'movement'], true) && $card['amount'] < 0) ? 'text-rose-600' : 'text-[#08745D]' }} sm:text-3xl">{{ $amount }}</p>
                        @if($card['key'] === 'movement')
                            <p class="mt-2 text-xs leading-5 text-slate-400">Recorded income minus recorded expenses; not accounting profit or a reconciled cash balance.</p>
                        @elseif($growth === null && $card['key'] !== 'transactions')
                            <p class="mt-2 text-xs leading-5 text-slate-400">No previous-period baseline</p>
                        @elseif($growth === null)
                            <p class="mt-2 text-xs leading-5 text-slate-400">No previous-period baseline</p>
                        @else
                            <p class="mt-2 text-xs font-semibold {{ $isFavorable ? 'text-[#08745D]' : 'text-rose-600' }}">
                                {{ $growth > 0 ? '↑' : ($growth < 0 ? '↓' : '→') }} {{ number_format(abs($growth), 1) }}% vs comparison period
                            </p>
                        @endif
                    </article>
                @endforeach
            </section>

            <section class="gt-card overflow-hidden" aria-labelledby="cost-insights-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <div>
                        <h3 id="cost-insights-heading" class="text-lg font-extrabold">Ingredient and product cost insights</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Operational estimates for {{ $rangeLabel }}. These do not change recorded income or expenses.</p>
                    </div>
                    <a href="{{ route('analytics.cost-logs', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="gt-focus inline-flex shrink-0 items-center gap-2 rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-sm font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">
                        View cost logs <span aria-hidden="true">→</span>
                    </a>
                </div>
                <div class="grid gap-4 p-5 sm:p-6 xl:grid-cols-3">
                    <article class="rounded-2xl border border-[#DDE9E5] bg-[#F8FCFA] p-4 sm:p-5">
                        <p class="text-sm font-bold text-slate-500">Recipe ingredient cost</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $costInsights['ingredientCost'], 2) }}</p>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Cost of recipe ingredients; recipe costs cover {{ number_format((float) $costInsights['ingredientCostCoveragePercent'], 1) }}% of eligible product sales.
                            @if($costInsights['ingredientCostEstimated']) Some older sales use current inventory costs or recipes. @endif
                        </p>
                    </article>
                    <article class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 sm:p-5">
                        <p class="text-sm font-bold text-slate-500">Recorded waste cost</p>
                        <p class="mt-2 text-2xl font-extrabold text-amber-700">{{ $currencyPrefix }}{{ number_format((float) $costInsights['wasteCost'], 2) }}</p>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Only stock deductions explicitly marked waste or spoilage are counted.
                            @if($costInsights['wasteCostEstimated']) Some waste has no saved unit cost and uses the current inventory cost when available. @endif
                        </p>
                        @if($costInsights['wasteItems']->isNotEmpty())
                            <ul class="mt-3 space-y-1 border-t border-amber-200 pt-3 text-xs">
                                @foreach($costInsights['wasteItems'] as $wasteItem)
                                    <li class="flex justify-between gap-3"><span class="truncate">{{ $wasteItem->name }} · {{ number_format((float) $wasteItem->quantity, 2) }} {{ $wasteItem->unit }}</span><span class="shrink-0 font-bold">{{ $currencyPrefix }}{{ number_format((float) $wasteItem->cost, 2) }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 border-t border-amber-200 pt-3 text-xs text-slate-500">No stock reductions were explicitly recorded as waste in this period.</p>
                        @endif
                    </article>
                    <article class="rounded-2xl border border-[#C8DED7] bg-gradient-to-br from-[#EAF8F2] to-white p-4 sm:p-5">
                        <p class="text-sm font-bold text-slate-500">Estimated product gross margin</p>
                        <p class="mt-2 text-2xl font-extrabold {{ $costInsights['grossMargin'] < 0 ? 'text-rose-600' : 'text-[#08745D]' }}">{{ $currencyPrefix }}{{ number_format((float) $costInsights['grossMargin'], 2) }}</p>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Sales minus known product costs, across {{ number_format((float) $costInsights['costCoveragePercent'], 1) }}% of eligible product sales. Not net profit; excludes labor, rent, and other overhead.</p>
                    </article>
                </div>
            </section>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
                <div class="min-w-0 space-y-5">
                <article class="gt-card gt-enter p-5 sm:p-6">
                    <h3 class="text-lg font-extrabold">{{ $chartPeriodName }} income and expenses</h3>
                    @if($hasCurrentData)
                        <div class="relative mt-5 h-72"><canvas id="incomeExpenseChart" aria-label="{{ $chartPeriodName }} income and expenses chart"></canvas></div>
                        <p class="mt-3 text-xs text-[#08745D]">Select a chart point to {{ $chartInterval === 'day' ? 'view its transactions, orders, and ingredients used' : 'see the days in that month' }}.</p>
                        <details class="mt-4 border-t border-[#EAF2EF] pt-3">
                            <summary class="gt-focus cursor-pointer text-sm font-bold text-[#08745D]">Browse {{ $chartInterval === 'day' ? 'days' : 'months' }}</summary>
                            <div class="mt-3 max-h-56 divide-y divide-[#EAF2EF] overflow-y-auto">
                                @foreach($chart['labels'] as $index => $label)
                                    <a href="{{ $chartLinks[$index] }}" class="gt-focus flex flex-wrap items-center justify-between gap-2 py-2 text-sm hover:text-[#08745D]">
                                        <span class="font-bold">{{ $label }}</span>
                                        <span class="text-xs text-slate-500">Income {{ $currencyPrefix }}{{ number_format($chart['income'][$index], 2) }} · Expenses {{ $currencyPrefix }}{{ number_format($chart['expenses'][$index], 2) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <div class="mt-5 flex h-72 items-center justify-center rounded-xl bg-[#F4F8F7] text-center text-sm text-slate-500">No income or expense transactions in the selected period.</div>
                    @endif
                </article>
            <section class="gt-card gt-enter overflow-hidden" aria-labelledby="top-selling-products-heading">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <div>
                        <h3 id="top-selling-products-heading" class="text-lg font-extrabold">Top selling products</h3>
                    </div>
                    <span class="rounded-full bg-[#EAF8F2] px-3 py-1.5 text-xs font-bold text-[#08745D]">{{ $topSellingProducts->count() }} {{ $topSellingProducts->count() === 1 ? 'product' : 'products' }}</span>
                </div>
                @if($topSellingProducts->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <caption class="sr-only">Top five products by units sold, with known recipe cost and gross margin, for {{ $rangeLabel }}</caption>
                            <thead class="bg-[#F8FBFA] text-[11px] font-extrabold uppercase tracking-[.1em] text-slate-500">
                                <tr>
                                    <th scope="col" class="w-14 px-5 py-3 sm:px-6">#</th>
                                    <th scope="col" class="px-3 py-3">Product</th>
                                    <th scope="col" class="px-3 py-3 text-right">Sold</th>
                                    <th scope="col" class="px-3 py-3 text-right">Sales</th>
                                    <th scope="col" class="px-3 py-3 text-right">Cost used</th>
                                    <th scope="col" class="px-3 py-3 text-right">Gross margin</th>
                                    <th scope="col" class="w-40 px-5 py-3 text-right sm:px-6">Share</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EAF2EF]">
                                @foreach($topSellingProducts as $index => $product)
                                    <tr class="transition hover:bg-[#F8FBFA]">
                                        <td class="px-5 py-3 text-sm font-bold text-slate-400 sm:px-6">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                        <th scope="row" class="px-3 py-3">
                                            <span class="flex items-center gap-3">
                                                <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#EAF8F2] text-sm font-extrabold text-[#08745D]">
                                                    @if($product->image)
                                                        <img src="{{ url('/api/v1/product-images/'.$product->product_id) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                                    @else
                                                        {{ mb_strtoupper(mb_substr($product->product_name ?: 'P', 0, 1)) }}
                                                    @endif
                                                </span>
                                                <span class="min-w-0 text-sm font-bold text-[#0A2E2A]">{{ $product->product_name ?: 'Unnamed product' }}</span>
                                            </span>
                                        </th>
                                        <td class="px-3 py-3 text-right text-sm font-semibold text-[#0A2E2A]">{{ number_format((int) $product->units_sold) }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $product->sales_amount, 2) }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-semibold text-slate-600">
                                            @if($product->cost_amount !== null)
                                                {{ $currencyPrefix }}{{ number_format((float) $product->cost_amount, 2) }}
                                                <span class="mt-1 block text-[10px] text-slate-400">{{ $product->cost_basis }}</span>
                                            @else
                                                <span class="text-slate-400">Not costed</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm font-extrabold {{ $product->gross_margin !== null && $product->gross_margin < 0 ? 'text-rose-600' : 'text-[#08745D]' }}">
                                            @if($product->gross_margin !== null)
                                                {{ $currencyPrefix }}{{ number_format((float) $product->gross_margin, 2) }}
                                                <span class="mt-1 block text-[10px] font-semibold text-slate-400">{{ number_format((float) $product->gross_margin_percent, 1) }}% margin</span>
                                            @else
                                                <span class="text-xs font-semibold text-slate-400">Cost incomplete</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right sm:px-6">
                                            <span class="text-xs font-bold text-[#0A2E2A]">{{ number_format((float) $product->sales_share, 1) }}%</span>
                                            <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-[#EAF2EF]" aria-hidden="true"><span class="block h-full rounded-full bg-[#00C897]" style="width: {{ min(100, max(0, (float) $product->sales_share)) }}%"></span></span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-12 text-center sm:px-6">
                        <p class="text-sm font-bold text-[#0A2E2A]">No product sales in this period</p>
                        <p class="mt-1 text-sm text-slate-500">Completed orders with confirmed payment will appear here after their sale is recorded.</p>
                    </div>
                @endif
            </section>

                </div>
                <aside class="min-w-0 space-y-5" aria-label="Analytics insights and comparison">
                <article class="gt-card border-[#C8DED7] bg-gradient-to-br from-[#F6FCF9] to-white p-5 sm:p-6">
                    <h3 class="text-lg font-extrabold">Business insights</h3>
                    <div class="mt-4 space-y-3 text-sm leading-6">
                        <div class="rounded-xl border border-[#DDE9E5] bg-[#F4F8F7] p-4">
                            <p class="font-bold text-[#0A2E2A]">Income trend</p>
                            <p class="mt-1 text-slate-600">
                                @if(($growth['income'] ?? null) !== null)
                                    Recorded income {{ $growth['income'] > 0 ? 'rose' : ($growth['income'] < 0 ? 'fell' : 'held steady') }}{{ $growth['income'] == 0 ? '' : ' '.number_format(abs($growth['income']), 1).'%' }} versus the comparison period.
                                @elseif($current['income'] > 0)
                                    {{ $currencyPrefix }}{{ number_format($current['income'], 2) }} recorded income; no positive prior-period baseline for a percentage comparison.
                                @else
                                    No income transactions were recorded in this period.
                                @endif
                            </p>
                        </div>
                        <div class="rounded-xl border border-[#DDE9E5] bg-[#F4F8F7] p-4">
                            <p class="font-bold text-[#0A2E2A]">Expense trend</p>
                            <p class="mt-1 text-slate-600">
                                @if(($growth['expenses'] ?? null) !== null)
                                    Recorded expenses {{ $growth['expenses'] > 0 ? 'rose' : ($growth['expenses'] < 0 ? 'fell' : 'held steady') }}{{ $growth['expenses'] == 0 ? '' : ' '.number_format(abs($growth['expenses']), 1).'%' }} versus the comparison period.{{ $growth['expenses'] > 0 ? ' Review expense entries to see what changed.' : '' }}
                                @elseif($current['expenses'] > 0)
                                    {{ $currencyPrefix }}{{ number_format($current['expenses'], 2) }} recorded expenses; no positive prior-period baseline for a percentage comparison.
                                @else
                                    No expense transactions were recorded in this period.
                                @endif
                            </p>
                        </div>
                        <div class="rounded-xl border border-[#DDE9E5] bg-[#F4F8F7] p-4">
                            <p class="font-bold text-[#0A2E2A]">Transaction activity</p>
                            <p class="mt-1 text-slate-600">{{ number_format($current['transactionCount']) }} saved {{ $current['transactionCount'] == 1 ? 'entry' : 'entries' }} this period, compared with {{ number_format($comparison['transactionCount']) }} in the previous period.</p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-3 border-t border-[#EAF2EF] pt-4">
                        <a href="{{ route('transactions.index') }}" class="gt-focus rounded-xl bg-[#EAF8F2] px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#DDF4EA]">View transaction records</a>
                    </div>
                </article>
                <article class="gt-card gt-enter p-5 sm:p-6">
                    <h3 class="text-lg font-extrabold">Period comparison</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $rangeLabel }} compared with {{ $comparisonLabel }}.</p>
                    @if($hasLedgerData)
                        <div class="relative mt-5 h-64"><canvas id="periodComparisonChart" aria-label="Equivalent-period comparison chart"></canvas></div>
                    @else
                        <div class="mt-5 flex h-64 items-center justify-center rounded-xl bg-[#F4F8F7] text-center text-sm text-slate-500">There is no recorded activity to compare yet.</div>
                    @endif
                    <div class="mt-4 grid gap-3 border-t border-[#EAF2EF] pt-4 text-xs">
                        <div><p class="font-bold text-slate-600">{{ $comparisonLabel }}</p><p class="mt-1 text-slate-500">Income {{ $currencyPrefix }}{{ number_format($comparison['income'], 2) }} · Expenses {{ $currencyPrefix }}{{ number_format($comparison['expenses'], 2) }} · Net movement {{ $currencyPrefix }}{{ number_format($comparison['netRecordedMovement'], 2) }}</p></div>
                        <div><p class="font-bold text-slate-600">{{ $rangeLabel }}</p><p class="mt-1 text-slate-500">Income {{ $currencyPrefix }}{{ number_format($current['income'], 2) }} · Expenses {{ $currencyPrefix }}{{ number_format($current['expenses'], 2) }} · Net movement {{ $currencyPrefix }}{{ number_format($current['netRecordedMovement'], 2) }}</p></div>
                    </div>
                </article>
                </aside>
            </div>
        </div>
    </main>
</div>

@if($hasLedgerData)
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;

    const chartData = @json($chart);
    const chartLinks = @json($chartLinks);
    const periods = @json($comparisonPeriods);
    const currencyPrefix = @json($currencyPrefix);
    const moneyTick = value => currencyPrefix + Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 });

    @if($hasCurrentData)
    new Chart(document.getElementById('incomeExpenseChart'), {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                { label: 'Recorded income', data: chartData.income, borderColor: '#00A87E', backgroundColor: 'rgba(0, 200, 151, .12)', tension: .25, fill: true },
                { label: 'Recorded expenses', data: chartData.expenses, borderColor: '#E11D48', backgroundColor: 'rgba(225, 29, 72, .08)', tension: .25, fill: true },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            onHover: (event, elements) => { if (event.native?.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
            onClick: (event, elements) => { if (elements.length && chartLinks[elements[0].index]) window.location.assign(chartLinks[elements[0].index]); },
            scales: { y: { beginAtZero: true, ticks: { callback: moneyTick } } },
        },
    });
    @endif

    new Chart(document.getElementById('periodComparisonChart'), {
        type: 'bar',
        data: {
            labels: periods.labels,
            datasets: [
                { label: 'Recorded income', data: periods.income, backgroundColor: 'rgba(0, 168, 126, .75)' },
                { label: 'Recorded expenses', data: periods.expenses, backgroundColor: 'rgba(225, 29, 72, .65)' },
                { label: 'Net recorded movement', data: periods.movement, backgroundColor: 'rgba(10, 46, 42, .55)' },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { ticks: { callback: moneyTick } } } },
    });
});
</script>
@endif
</body>
</html>
