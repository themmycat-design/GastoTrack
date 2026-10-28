<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Reports | GastoTrack</title>
    <style>
        .gt-report-print-header { display: none; }

        @page {
            size: landscape;
            margin: 12mm;
        }

        @media print {
            body.gt-owner-reports-page {
                min-height: 0 !important;
                background: #fff !important;
                color: #0A2E2A !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body.gt-owner-reports-page > div.min-h-screen {
                display: block !important;
                min-height: 0 !important;
            }

            body.gt-owner-reports-page [data-owner-navigation],
            body.gt-owner-reports-page main > header,
            body.gt-owner-reports-page .gt-report-screen-controls {
                display: none !important;
            }

            body.gt-owner-reports-page main {
                width: 100% !important;
                min-width: 0 !important;
            }

            body.gt-owner-reports-page main > .mx-auto {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .gt-report-print-header {
                display: block !important;
                margin-bottom: 16pt;
                border-bottom: 1px solid #DDE9E5;
                padding-bottom: 10pt;
            }

            .gt-report-print-header h1 {
                margin: 0;
                font-size: 20pt;
                font-weight: 800;
            }

            .gt-report-print-header p {
                margin: 4pt 0 0;
                color: #475569;
                font-size: 10pt;
            }

            .gt-scroll-reveal,
            .gt-enter {
                opacity: 1 !important;
                transform: none !important;
                animation: none !important;
            }

            .gt-card {
                box-shadow: none !important;
                break-inside: avoid;
            }

            .overflow-x-auto {
                overflow: visible !important;
            }

            table {
                width: 100% !important;
                min-width: 0 !important;
                border-collapse: collapse;
                font-size: 9pt;
            }

            tr {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body class="gt-owner-page gt-owner-reports-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ';
    $rangeLabel = $startDate->format('M j, Y').' – '.$endDate->format('M j, Y');
@endphp
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section class="gt-enter gt-report-screen-controls flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $sectionTitle }}</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-report-print class="gt-focus inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-[#B8DDD0] bg-white px-4 py-3 text-sm font-extrabold text-[#08745D] shadow-sm transition hover:bg-[#EAF8F2]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7zM17 11h.01"/></svg>
                        Print / save PDF
                    </button>
                    <a href="{{ route('owner.reports.export', ['start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}" class="gt-focus inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#08745D] px-4 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-[#065C4B]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v3h16v-3"/></svg>
                        Download CSV
                    </a>
                </div>
            </section>

            <section class="gt-card gt-scroll-reveal gt-report-screen-controls overflow-hidden" data-gt-reveal-once="true" style="--gt-reveal-delay: 70ms" aria-label="Report date range">
                <form method="GET" action="{{ route('owner.reports.show', ['section' => $activeReportRoute, 'reports_open' => 1]) }}" class="grid gap-4 p-5 sm:p-6">
                    <div class="rounded-xl border border-[#EAF2EF] bg-[#F8FBFA] px-4 py-3">
                        <p class="text-sm font-extrabold">Reporting period</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $rangeLabel }} · Choose up to one year.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                        <label for="report-start-date" class="block min-w-0 text-xs font-bold text-slate-600">From
                            <input id="report-start-date" class="gt-focus mt-1.5 block h-11 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 text-sm font-medium text-[#0A2E2A] shadow-sm transition hover:border-[#B8DDD0]" type="date" name="start_date" value="{{ $startDate->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </label>
                        <label for="report-end-date" class="block min-w-0 text-xs font-bold text-slate-600">To
                            <input id="report-end-date" class="gt-focus mt-1.5 block h-11 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 text-sm font-medium text-[#0A2E2A] shadow-sm transition hover:border-[#B8DDD0]" type="date" name="end_date" value="{{ $endDate->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </label>
                        <button class="gt-focus inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#08745D] px-5 text-sm font-extrabold text-white shadow-sm transition hover:bg-[#065C4B] sm:col-span-2 lg:col-span-1 lg:w-auto">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 10 3.5 3.5L16 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Update report
                        </button>
                    </div>
                </form>
                @if($errors->any())
                    <p role="alert" class="mx-5 mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 sm:mx-6">{{ $errors->first() }}</p>
                @endif
            </section>

            <header class="gt-report-print-header" aria-hidden="true">
                <h1>{{ $sectionTitle }}</h1>
                <p>{{ $business->name }} · {{ $rangeLabel }} · GastoTrack</p>
            </header>

            @if($activeReportSection === 'sales')
            <section id="report-sales" class="gt-scroll-reveal scroll-mt-5 space-y-4" data-gt-reveal-once="true" style="--gt-reveal-delay: 110ms">
                <div>
                    <p class="mt-1 text-sm text-slate-500">Completed orders with confirmed payment and a committed sale record, {{ $rangeLabel }}.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Confirmed order sales</p><p class="mt-2 text-2xl font-extrabold text-[#08745D]"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $sales['amount'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format($sales['amount'], 2) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Completed paid orders</p><p class="mt-2 text-2xl font-extrabold"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (int) $sales['orderCount'] }}" data-owner-count-up-decimals="0">{{ number_format($sales['orderCount']) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Average order value</p><p class="mt-2 text-2xl font-extrabold"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $sales['averageOrder'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format($sales['averageOrder'], 2) }}</span></p></article>
                </div>
                <div class="grid items-start gap-4 xl:grid-cols-2">
                    <article class="gt-card overflow-hidden">
                        <div class="border-b border-[#EAF2EF] px-5 py-4"><h3 class="font-extrabold">Recent completed orders</h3><p class="mt-1 text-xs text-slate-500">Order sales are shown separately from ledger income.</p></div>
                        @if($orders->isNotEmpty())
                            <div class="overflow-x-auto"><table class="w-full min-w-[620px] text-left text-sm"><thead class="bg-[#F8FBFA] text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Completed</th><th class="px-5 py-3">Staff</th><th class="px-5 py-3">Payment</th><th class="px-5 py-3 text-right">Amount</th></tr></thead><tbody class="divide-y divide-[#EAF2EF]">
                                @foreach($orders as $order)
                                    <tr><td class="px-5 py-3.5">{{ $order->completed_at?->format('M j, Y · g:i A') ?? '—' }}</td><td class="px-5 py-3.5">{{ $order->creator?->name ?? 'Former staff' }}</td><td class="px-5 py-3.5">{{ ucfirst($order->payment_method ?: $order->payment_type) }}</td><td class="px-5 py-3.5 text-right font-extrabold">{{ $currencyPrefix }}{{ number_format((float) $order->total, 2) }}</td></tr>
                                @endforeach
                            </tbody></table></div>
                        @else
                            <p class="px-5 py-8 text-center text-sm text-slate-500">No eligible sales in this period.</p>
                        @endif
                    </article>
                    <article class="gt-card p-5">
                        <h3 class="font-extrabold">Sales by payment method</h3>
                        @if($sales['paymentMethods']->isNotEmpty())
                            <div class="mt-4 divide-y divide-[#EAF2EF]">
                                @foreach($sales['paymentMethods'] as $method)
                                    <div class="flex items-center justify-between gap-4 py-3"><div><p class="text-sm font-bold">{{ ucfirst($method->payment_method ?: $method->payment_type) }}</p><p class="mt-0.5 text-xs text-slate-500">{{ number_format((int) $method->order_count) }} {{ (int) $method->order_count === 1 ? 'order' : 'orders' }}</p></div><p class="text-sm font-extrabold">{{ $currencyPrefix }}{{ number_format((float) $method->sales_amount, 2) }}</p></div>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-4 text-sm text-slate-500">No confirmed payment activity in this period.</p>
                        @endif
                    </article>
                </div>
            </section>
            @endif

            @if($activeReportSection === 'ledger')
            <section id="report-ledger" class="gt-scroll-reveal scroll-mt-5 space-y-4" data-gt-reveal-once="true" style="--gt-reveal-delay: 110ms">
                <div><p class="text-sm text-slate-500">Recorded income can include saved order-sale entries; the separate sales figure is not added to the ledger total a second time.</p></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recorded income</p><p class="mt-2 text-2xl font-extrabold text-[#08745D]"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $ledger['income'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $ledger['income'], 2) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recorded expenses</p><p class="mt-2 text-2xl font-extrabold text-rose-600"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $ledger['expenses'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $ledger['expenses'], 2) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Net recorded movement</p><p class="mt-2 text-2xl font-extrabold {{ $ledger['netRecordedMovement'] < 0 ? 'text-rose-600' : 'text-[#08745D]' }}"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $ledger['netRecordedMovement'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $ledger['netRecordedMovement'], 2) }}</span></p></article>
                </div>
                <div class="grid items-start gap-4 xl:grid-cols-2">
                    <article class="gt-card p-5"><h3 class="font-extrabold">Ledger by category</h3>
                        @if($ledgerCategories->isNotEmpty())
                            <div class="mt-3 divide-y divide-[#EAF2EF]">@foreach($ledgerCategories as $entry)<div class="flex justify-between gap-3 py-2.5"><div><p class="text-sm font-bold">{{ $entry->category }}</p><p class="text-xs text-slate-500">{{ ucfirst($entry->type) }} · {{ number_format((int) $entry->entry_count) }} entries</p></div><p class="text-sm font-extrabold">{{ $currencyPrefix }}{{ number_format((float) $entry->amount, 2) }}</p></div>@endforeach</div>
                        @else<p class="mt-4 text-sm text-slate-500">No ledger entries in this period.</p>@endif
                    </article>
                    <article class="gt-card overflow-hidden"><div class="border-b border-[#EAF2EF] px-5 py-4"><h3 class="font-extrabold">Recent ledger entries</h3></div>
                        @if($transactions->isNotEmpty())<div class="divide-y divide-[#EAF2EF]">@foreach($transactions as $transaction)<div class="flex items-start justify-between gap-4 px-5 py-3.5"><div class="min-w-0"><p class="truncate text-sm font-bold">{{ $transaction->category }} · {{ ucfirst($transaction->type) }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $transaction->transaction_date?->format('M j, Y') }} · {{ $transaction->user?->name ?? 'Former staff' }}</p></div><p class="shrink-0 text-sm font-extrabold {{ $transaction->type === 'expense' ? 'text-rose-600' : 'text-[#08745D]' }}">{{ $currencyPrefix }}{{ number_format((float) $transaction->amount, 2) }}</p></div>@endforeach</div>
                        @else<p class="px-5 py-8 text-center text-sm text-slate-500">No recorded transactions in this period.</p>@endif
                    </article>
                </div>
            </section>
            @endif

            @if($activeReportSection === 'products')
            <section id="report-products" class="gt-scroll-reveal scroll-mt-5 space-y-4" data-gt-reveal-once="true" style="--gt-reveal-delay: 110ms">
                <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-sm text-slate-500">Operational estimates for {{ $rangeLabel }}; not net profit and not added to recorded income or expenses.</p></div><a href="{{ route('analytics.cost-logs', ['start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}" class="gt-focus text-sm font-extrabold text-[#08745D] hover:underline">View ingredient cost logs →</a></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recipe ingredient cost</p><p class="mt-2 text-2xl font-extrabold"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $costInsights['ingredientCost'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $costInsights['ingredientCost'], 2) }}</span></p><p class="mt-2 text-xs text-slate-500">Recipe cost coverage: {{ number_format((float) $costInsights['ingredientCostCoveragePercent'], 1) }}% of eligible sales.</p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recorded waste cost</p><p class="mt-2 text-2xl font-extrabold text-amber-700"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $costInsights['wasteCost'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $costInsights['wasteCost'], 2) }}</span></p><p class="mt-2 text-xs text-slate-500">Only stock reductions marked as waste are counted.</p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Estimated product gross margin</p><p class="mt-2 text-2xl font-extrabold {{ $costInsights['grossMargin'] < 0 ? 'text-rose-600' : 'text-[#08745D]' }}"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $costInsights['grossMargin'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $costInsights['grossMargin'], 2) }}</span></p><p class="mt-2 text-xs text-slate-500">Known product costs cover {{ number_format((float) $costInsights['costCoveragePercent'], 1) }}% of eligible sales; excludes overhead.</p></article>
                </div>
                <article class="gt-card overflow-hidden">
                    <div class="border-b border-[#EAF2EF] px-5 py-4"><h3 class="font-extrabold">Top selling products</h3><p class="mt-1 text-xs text-slate-500">Gross margin is blank when cost data is incomplete.</p></div>
                    @if($topSellingProducts->isNotEmpty())<div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-[#F8FBFA] text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Product</th><th class="px-5 py-3 text-right">Units</th><th class="px-5 py-3 text-right">Sales</th><th class="px-5 py-3 text-right">Ingredient cost</th><th class="px-5 py-3 text-right">Gross margin</th></tr></thead><tbody class="divide-y divide-[#EAF2EF]">@foreach($topSellingProducts as $product)<tr><td class="px-5 py-3.5 font-bold">{{ $product->product_name }}</td><td class="px-5 py-3.5 text-right">{{ number_format((float) $product->units_sold) }}</td><td class="px-5 py-3.5 text-right">{{ $currencyPrefix }}{{ number_format((float) $product->sales_amount, 2) }}</td><td class="px-5 py-3.5 text-right">{{ $product->ingredient_cost === null ? '—' : $currencyPrefix.number_format((float) $product->ingredient_cost, 2) }}</td><td class="px-5 py-3.5 text-right font-extrabold">{{ $product->gross_margin === null ? 'Incomplete cost' : $currencyPrefix.number_format((float) $product->gross_margin, 2).' · '.number_format((float) $product->gross_margin_percent, 1).'%' }}</td></tr>@endforeach</tbody></table></div>
                    @else<p class="px-5 py-8 text-center text-sm text-slate-500">No eligible product sales in this period.</p>@endif
                    @if($costInsights['ingredientCostEstimated'] || $costInsights['wasteCostEstimated'])<p class="border-t border-[#EAF2EF] px-5 py-3 text-xs leading-5 text-slate-500">Some costs use current recipe or stock costs because older sales did not have saved cost snapshots.</p>@endif
                </article>
                @if($costInsights['wasteItems']->isNotEmpty())
                    <article class="gt-card p-5"><h3 class="font-extrabold">Waste by ingredient</h3><div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">@foreach($costInsights['wasteItems'] as $item)<div class="flex justify-between gap-3 rounded-xl bg-amber-50/70 px-4 py-3"><span class="text-sm font-semibold">{{ $item->name }} · {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span><span class="shrink-0 text-sm font-extrabold text-amber-800">{{ $currencyPrefix }}{{ number_format((float) $item->cost, 2) }}</span></div>@endforeach</div></article>
                @endif
            </section>
            @endif

            @if($activeReportSection === 'inventory')
            <section id="report-inventory" class="gt-scroll-reveal scroll-mt-5 space-y-4" data-gt-reveal-once="true" style="--gt-reveal-delay: 110ms">
                <div><p class="text-sm text-slate-500">Current stock is a snapshot as of now; movement activity below follows the selected dates.</p></div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Active stock items</p><p class="mt-2 text-2xl font-extrabold"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (int) $inventory['itemCount'] }}" data-owner-count-up-decimals="0">{{ number_format($inventory['itemCount']) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Low stock</p><p class="mt-2 text-2xl font-extrabold text-amber-700"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (int) $inventory['lowStock'] }}" data-owner-count-up-decimals="0">{{ number_format($inventory['lowStock']) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Out of stock</p><p class="mt-2 text-2xl font-extrabold text-rose-600"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (int) $inventory['outOfStock'] }}" data-owner-count-up-decimals="0">{{ number_format($inventory['outOfStock']) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Estimated stock value</p><p class="mt-2 text-2xl font-extrabold"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $inventory['estimatedValue'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $inventory['estimatedValue'], 2) }}</span></p></article>
                </div>
                <div class="grid items-start gap-4 xl:grid-cols-2">
                    <article class="gt-card p-5"><h3 class="font-extrabold">Items needing restock</h3>
                        @if($lowStockItems->isNotEmpty())<div class="mt-3 divide-y divide-[#EAF2EF]">@foreach($lowStockItems as $item)<div class="flex items-center justify-between gap-4 py-2.5"><div><p class="text-sm font-bold">{{ $item->name }}</p><p class="text-xs text-slate-500">Minimum {{ number_format((float) $item->minimum_quantity, 2) }} {{ $item->unit }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-extrabold {{ (float) $item->current_quantity <= 0 ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800' }}">{{ (float) $item->current_quantity <= 0 ? 'Out' : number_format((float) $item->current_quantity, 2).' '.$item->unit }}</span></div>@endforeach</div>
                        @else<p class="mt-4 text-sm text-slate-500">No active stock items are at or below their minimum level.</p>@endif
                    </article>
                    <article class="gt-card p-5"><h3 class="font-extrabold">Stock movement in selected period</h3>
                        @if($stockMovementSummary->isNotEmpty())<div class="mt-3 divide-y divide-[#EAF2EF]">@foreach($stockMovementSummary as $movement)<div class="flex justify-between gap-3 py-2.5"><div><p class="text-sm font-bold">{{ ucfirst($movement->movement_category ?: $movement->type) }}</p><p class="text-xs text-slate-500">{{ ucfirst($movement->type) }} · {{ number_format((int) $movement->entry_count) }} entries</p></div><p class="text-sm font-extrabold">{{ number_format((float) $movement->quantity, 2) }}</p></div>@endforeach</div>
                        @else<p class="mt-4 text-sm text-slate-500">No stock movements in this period.</p>@endif
                    </article>
                </div>
                @if($recentStockMovements->isNotEmpty())
                    <article class="gt-card overflow-hidden"><div class="border-b border-[#EAF2EF] px-5 py-4"><h3 class="font-extrabold">Recent stock changes</h3></div><div class="divide-y divide-[#EAF2EF]">@foreach($recentStockMovements as $movement)<div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3"><div><p class="text-sm font-bold">{{ $movement->stock_name }} · {{ ucfirst($movement->movement_category ?: $movement->type) }}</p><p class="mt-1 text-xs text-slate-500">{{ $movement->reason ?: ucfirst($movement->type) }} · {{ $movement->recorded_by ?: 'Former staff' }}</p></div><p class="text-sm font-extrabold">{{ number_format((float) $movement->quantity, 2) }} {{ $movement->unit }}</p></div>@endforeach</div></article>
                @endif
            </section>
            @endif

            @if($activeReportSection === 'payments')
            <section id="report-payments" class="gt-scroll-reveal scroll-mt-5 space-y-4" data-gt-reveal-once="true" style="--gt-reveal-delay: 110ms">
                <div><p class="text-sm text-slate-500">Pending records and raw wallet notifications are review signals, not confirmed payments.</p></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Cashless orders awaiting payment</p><p class="mt-2 text-2xl font-extrabold text-amber-700"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (int) $payments['awaitingCount'] }}" data-owner-count-up-decimals="0">{{ number_format($payments['awaitingCount']) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Refunds awaiting review</p><p class="mt-2 text-2xl font-extrabold text-rose-600"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (int) $payments['refundCount'] }}" data-owner-count-up-decimals="0">{{ number_format($payments['refundCount']) }}</span></p></article>
                    <article class="gt-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Wallet alerts captured</p><p class="mt-2 text-2xl font-extrabold"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (int) $payments['walletCount'] }}" data-owner-count-up-decimals="0">{{ number_format($payments['walletCount']) }}</span></p><p class="mt-2 text-xs text-slate-500">Unverified notification signals</p></article>
                </div>
                <article class="gt-card overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#EAF2EF] px-5 py-4"><div><h3 class="font-extrabold">Raw GCash & Maya notifications</h3><p class="mt-1 text-xs text-slate-500">These alerts never change recorded sales or confirm an order by themselves.</p></div>
                        @if($payments['walletSources']->isNotEmpty())<div class="flex flex-wrap gap-2">@foreach($payments['walletSources'] as $source)<span class="rounded-full bg-[#F4F8F7] px-3 py-1.5 text-xs font-bold text-slate-600">{{ strtoupper($source->source) }} · {{ number_format((int) $source->alert_count) }}</span>@endforeach</div>@endif
                    </div>
                    @if($walletCaptures->isNotEmpty())<div class="divide-y divide-[#EAF2EF]">@foreach($walletCaptures as $capture)<div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5"><div><p class="text-sm font-bold">{{ strtoupper($capture->source) }} · {{ $capture->merchant ?: ucfirst($capture->direction).' notification' }}</p><p class="mt-1 text-xs text-slate-500">{{ $capture->captured_at?->format('M j, Y · g:i A') }} · {{ $capture->capturedBy?->name ?? 'Staff device' }}</p></div><div class="text-right"><p class="text-sm font-extrabold">{{ $capture->amount === null ? 'Amount unavailable' : strtoupper($capture->currency ?: $currencyCode).' '.number_format((float) $capture->amount, 2) }}</p><p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Unverified</p></div></div>@endforeach</div>
                    @else<p class="px-5 py-8 text-center text-sm text-slate-500">No wallet notifications were captured in this period.</p>@endif
                </article>
            </section>
            @endif

            @if($activeReportSection === 'staff')
            <section id="report-staff" class="gt-scroll-reveal scroll-mt-5 space-y-4" data-gt-reveal-once="true" style="--gt-reveal-delay: 110ms">
                <div><p class="text-sm text-slate-500">Counts for completed paid orders, saved ledger entries, and recorded stock movements during {{ $rangeLabel }}.</p></div>
                <article class="gt-card overflow-hidden">
                    @if(count($staffActivity))<div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm"><thead class="bg-[#F8FBFA] text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Staff member</th><th class="px-5 py-3 text-right">Completed orders</th><th class="px-5 py-3 text-right">Confirmed order sales</th><th class="px-5 py-3 text-right">Ledger entries</th><th class="px-5 py-3 text-right">Stock changes</th></tr></thead><tbody class="divide-y divide-[#EAF2EF]">@foreach($staffActivity as $staff)<tr><td class="px-5 py-3.5 font-bold">{{ $staff['name'] }}</td><td class="px-5 py-3.5 text-right">{{ number_format($staff['orders']) }}</td><td class="px-5 py-3.5 text-right font-extrabold">{{ $currencyPrefix }}{{ number_format($staff['confirmedSales'], 2) }}</td><td class="px-5 py-3.5 text-right">{{ number_format($staff['ledgerEntries']) }}</td><td class="px-5 py-3.5 text-right">{{ number_format($staff['stockChanges']) }}</td></tr>@endforeach</tbody></table></div>
                    @else<p class="px-5 py-8 text-center text-sm text-slate-500">No staff accounts are registered for this business.</p>@endif
                    <p class="border-t border-[#EAF2EF] px-5 py-3 text-xs leading-5 text-slate-500">Staff order sales follow the same confirmed-sale rule as the sales section. Ledger entries include linked order entries and are activity counts only; ledger and sales totals are not combined.</p>
                </article>
            </section>
            @endif
        </div>
    </main>
</div>
<script>
    document.querySelector('[data-report-print]')?.addEventListener('click', () => window.print());
</script>
</body>
</html>
