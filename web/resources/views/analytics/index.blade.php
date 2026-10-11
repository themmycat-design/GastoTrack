<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <title>Analytics | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ';
    $current = $current ?? ['income' => 0, 'expenses' => 0, 'netRecordedMovement' => 0, 'transactionCount' => 0];
    $comparison = $comparison ?? ['income' => 0, 'expenses' => 0, 'netRecordedMovement' => 0, 'transactionCount' => 0];
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
            <section class="gt-enter">
                <div>
                    <p class="text-sm font-semibold text-[#00A87E]">{{ Auth::user()->business?->name }} · business performance</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Analytics</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">This report summarizes saved income and expense transactions; it does not estimate accounting profit or customer counts.</p>
                </div>
            </section>

            <section class="gt-card p-4 sm:p-5" aria-label="Reporting period">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[.14em] text-slate-500">View income and expenses</p>
                        <nav class="mt-2 inline-flex flex-wrap gap-1 rounded-xl bg-[#F4F8F7] p-1" aria-label="Analytics period">
                            @foreach(['week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'] as $option => $label)
                                <a href="{{ route('analytics.index', ['period' => $option]) }}" aria-current="{{ $period === $option ? 'page' : 'false' }}" class="gt-focus rounded-lg px-4 py-2 text-sm font-bold transition {{ $period === $option ? 'bg-[#00A87E] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-[#08745D]' }}">{{ $label }}</a>
                            @endforeach
                        </nav>
                    </div>
                    <p class="text-sm font-semibold text-[#08745D]">{{ $periodNames[$period] }} · {{ $rangeLabel }}</p>
                </div>
                <form method="GET" action="{{ route('analytics.index') }}" class="mt-4 grid gap-3 border-t border-[#EAF2EF] pt-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <input type="hidden" name="period" value="custom">
                    <div>
                        <label for="start_date" class="mb-1 block text-xs font-bold text-slate-600">From</label>
                        <input id="start_date" type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" max="{{ now()->toDateString() }}" required class="w-full px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="end_date" class="mb-1 block text-xs font-bold text-slate-600">To</label>
                        <input id="end_date" type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" max="{{ now()->toDateString() }}" required class="w-full px-3 py-2 text-sm">
                    </div>
                    <button class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Apply custom dates</button>
                </form>
            </section>

            <section class="rounded-2xl border border-[#C8DED7] bg-white px-5 py-4 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-extrabold text-[#0A2E2A]">{{ $periodNames[$period] }}: {{ $rangeLabel }}</p>
                    <p class="mt-1 text-xs text-slate-500">Equivalent comparison period: {{ $comparisonLabel }}</p>
                </div>
                <p class="mt-2 text-xs text-slate-500 sm:mt-0">Amounts in {{ $currencyCode }} · transactions dated in the selected range</p>
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
                        <p class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</p>
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

            <section class="grid gap-5 xl:grid-cols-2">
                <article class="gt-card gt-enter p-5 sm:p-6">
                    <h3 class="text-lg font-extrabold">{{ $chartPeriodName }} income and expenses</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $chartPeriodName }} totals from transaction records in {{ $rangeLabel }}.</p>
                    @if($hasCurrentData)
                        <p class="mt-2 text-xs text-[#08745D]">Select a chart point to {{ $chartInterval === 'day' ? 'view its transactions, orders, and ingredients used' : 'see the days in that month' }}.</p>
                        <div class="relative mt-5 h-72"><canvas id="incomeExpenseChart" aria-label="{{ $chartPeriodName }} income and expenses chart"></canvas></div>
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
                <article class="gt-card gt-enter p-5 sm:p-6">
                    <h3 class="text-lg font-extrabold">Period comparison</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $rangeLabel }} compared with {{ $comparisonLabel }}.</p>
                    @if($hasLedgerData)
                        <div class="relative mt-5 h-72"><canvas id="periodComparisonChart" aria-label="Equivalent-period comparison chart"></canvas></div>
                    @else
                        <div class="mt-5 flex h-72 items-center justify-center rounded-xl bg-[#F4F8F7] text-center text-sm text-slate-500">There is no recorded activity to compare yet.</div>
                    @endif
                    <div class="mt-4 grid gap-3 border-t border-[#EAF2EF] pt-4 text-xs sm:grid-cols-2">
                        <div><p class="font-bold text-slate-600">{{ $comparisonLabel }}</p><p class="mt-1 text-slate-500">Income {{ $currencyPrefix }}{{ number_format($comparison['income'], 2) }} · Expenses {{ $currencyPrefix }}{{ number_format($comparison['expenses'], 2) }} · Net movement {{ $currencyPrefix }}{{ number_format($comparison['netRecordedMovement'], 2) }}</p></div>
                        <div><p class="font-bold text-slate-600">{{ $rangeLabel }}</p><p class="mt-1 text-slate-500">Income {{ $currencyPrefix }}{{ number_format($current['income'], 2) }} · Expenses {{ $currencyPrefix }}{{ number_format($current['expenses'], 2) }} · Net movement {{ $currencyPrefix }}{{ number_format($current['netRecordedMovement'], 2) }}</p></div>
                    </div>
                </article>
            </section>

            <section class="grid gap-5 lg:grid-cols-2">
                <article class="gt-card p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div><h3 class="text-lg font-extrabold">Top inventory value</h3><p class="mt-1 text-xs text-slate-500">Current quantity × recorded unit cost; excludes inactive/deleted stock.</p></div>
                        <a href="{{ route('stock.index') }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">Inventory →</a>
                    </div>
                    <div class="mt-4 divide-y divide-[#EAF2EF]">
                        @forelse($topStockItems as $item)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div class="min-w-0"><p class="truncate text-sm font-bold">{{ $item->name }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format((float) $item->current_quantity, 2) }} {{ $item->unit }} × {{ $currencyPrefix }}{{ number_format((float) $item->unit_cost, 2) }}/{{ $item->unit }}</p></div>
                                <p class="shrink-0 text-sm font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $item->stock_value, 2) }}</p>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-slate-500">No active stock items to value.</p>
                        @endforelse
                    </div>
                </article>

                <article class="gt-card p-5 sm:p-6">
                    <h3 class="text-lg font-extrabold">What these numbers mean</h3>
                    <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <p><span class="font-bold text-[#0A2E2A]">Recorded income and expenses</span> include non-deleted transactions for this business, including saved order-sale and reversal entries. Order sales are not added to these amounts a second time.</p>
                        <p>Older cashless entries awaiting review and original sales with a pending refund may remain in recorded income until they are reconciled or reversed. Check payment status before treating the ledger total as collected money.</p>
                        <p><span class="font-bold text-[#0A2E2A]">Net recorded movement</span> is income minus expenses in the transaction ledger. Product-level cost of goods sold, tax, and full accounting adjustments are not represented, so this is not profit.</p>
                        <p><span class="font-bold text-[#0A2E2A]">Growth</span> compares equivalent date ranges. When the comparison has no positive baseline, the page says so instead of showing a false 0% change.</p>
                        <p><span class="font-bold text-[#0A2E2A]">Transaction count</span> measures saved ledger entries only; it is not a customer or order count.</p>
                    </div>
                    <div class="mt-5 border-t border-[#EAF2EF] pt-4">
                        <a href="{{ route('transactions.index') }}" class="gt-focus rounded-xl bg-[#EAF8F2] px-4 py-2.5 text-sm font-bold text-[#08745D] hover:bg-[#DDF4EA]">View transaction records →</a>
                    </div>
                </article>
            </section>
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
