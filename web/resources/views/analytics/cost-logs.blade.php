<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Cost logs | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php($currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ')
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <header class="gt-enter">
                <a href="{{ route('analytics.index', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">← Back to Analytics</a>
                <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Ingredient and waste cost logs</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Saved product-sale recipe snapshots and stock reductions explicitly recorded as waste. These costs are separate from recorded income and expenses.</p>
            </header>

            <section class="gt-card px-5 py-4 sm:px-6" aria-label="Cost log date range">
                <form method="GET" action="{{ route('analytics.cost-logs') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="start_date" class="mb-1 block text-xs font-bold text-slate-600">From</label>
                        <input id="start_date" type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" max="{{ now()->toDateString() }}" required class="w-full min-w-40 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="end_date" class="mb-1 block text-xs font-bold text-slate-600">To</label>
                        <input id="end_date" type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" max="{{ now()->toDateString() }}" required class="w-full min-w-40 px-3 py-2 text-sm">
                    </div>
                    <button class="gt-focus rounded-xl bg-[#00A87E] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#08745D]">Show logs</button>
                    <span class="pb-2 text-xs text-slate-500">{{ $startDate->format('M j, Y') }} – {{ $endDate->format('M j, Y') }}</span>
                </form>
                @if($errors->any())
                    <p class="mt-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</p>
                @endif
            </section>

            <section class="gt-card overflow-hidden" aria-labelledby="recipe-cost-logs-heading">
                <div class="border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <h2 id="recipe-cost-logs-heading" class="text-lg font-extrabold">Product ingredient cost and margin</h2>
                    <p class="mt-1 text-sm text-slate-500">Each row is a sold product line. Ingredient quantities are the recipe amount multiplied by units sold.</p>
                </div>
                @if($costEntries->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1050px] text-left">
                            <caption class="sr-only">Product sales with recipe ingredient costs, sales totals, and estimated gross margin for the selected dates.</caption>
                            <thead class="bg-[#F8FBFA] text-[11px] font-extrabold uppercase tracking-[.1em] text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3 sm:px-6">Date / product</th>
                                    <th scope="col" class="px-4 py-3">Ingredients used</th>
                                    <th scope="col" class="px-4 py-3 text-right">Recipe cost</th>
                                    <th scope="col" class="px-4 py-3 text-right">Product cost</th>
                                    <th scope="col" class="px-4 py-3 text-right">Sales</th>
                                    <th scope="col" class="px-5 py-3 text-right sm:px-6">Gross margin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EAF2EF]">
                                @foreach($costEntries as $entry)
                                    <tr class="align-top transition hover:bg-[#FBFDFC]">
                                        <th scope="row" class="min-w-52 px-5 py-4 sm:px-6">
                                            <p class="font-bold text-[#0A2E2A]">{{ $entry['product_name'] }}</p>
                                            <a href="{{ route('orders.show', $entry['order_id']) }}" class="gt-focus mt-1 inline-flex text-xs font-semibold text-[#08745D] hover:underline">{{ $entry['date']?->format('M j, Y · g:i A') ?? 'Sale date unavailable' }} · View order</a>
                                            <p class="mt-1 text-xs text-slate-500">{{ number_format($entry['quantity']) }} {{ $entry['quantity'] === 1 ? 'unit' : 'units' }} · {{ $entry['cost_basis'] }}@if($entry['estimated']) · estimated @endif</p>
                                        </th>
                                        <td class="max-w-md px-4 py-4">
                                            @if(count($entry['ingredients']))
                                                <ul class="space-y-1.5 text-xs text-slate-600">
                                                    @foreach($entry['ingredients'] as $ingredient)
                                                        <li class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                                            <span>{{ $ingredient['name'] }} · {{ number_format($ingredient['quantity'], 2) }} {{ $ingredient['unit'] }}</span>
                                                            <span class="font-semibold text-[#0A2E2A]">
                                                                @if($ingredient['cost'] !== null)
                                                                    {{ $currencyPrefix }}{{ number_format($ingredient['cost'], 2) }}
                                                                @else
                                                                    Unit cost unavailable
                                                                @endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-xs text-slate-400">No recipe was saved for this sale.</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-bold text-[#08745D]">
                                            {{ $entry['ingredient_cost'] === null ? '—' : $currencyPrefix.number_format($entry['ingredient_cost'], 2) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold text-slate-700">
                                            {{ $entry['product_cost'] === null ? '—' : $currencyPrefix.number_format($entry['product_cost'], 2) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold text-slate-700">{{ $currencyPrefix }}{{ number_format($entry['sale_amount'], 2) }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-extrabold sm:px-6 {{ $entry['gross_margin'] !== null && $entry['gross_margin'] < 0 ? 'text-rose-600' : 'text-[#08745D]' }}">
                                            {{ $entry['gross_margin'] === null ? '—' : $currencyPrefix.number_format($entry['gross_margin'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-[#EAF2EF] px-5 py-3 text-xs text-slate-500 sm:px-6">Showing cost details from {{ $orders->count() }} orders on this page. Older sales without recipe snapshots may use current recipe or inventory costs.</div>
                    @if($orders->hasPages())<div class="border-t border-[#EAF2EF] px-5 py-4">{{ $orders->links() }}</div>@endif
                @else
                    <p class="px-6 py-12 text-center text-sm text-slate-500">No recorded product sales were found for these dates.</p>
                @endif
            </section>

            <section class="gt-card overflow-hidden" aria-labelledby="waste-cost-logs-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <div>
                        <h2 id="waste-cost-logs-heading" class="text-lg font-extrabold">Recorded waste cost</h2>
                        <p class="mt-1 text-sm text-slate-500">Only inventory reductions explicitly marked as waste or spoilage.</p>
                    </div>
                    <p class="rounded-xl bg-amber-50 px-4 py-2 text-sm font-extrabold text-amber-800">Period total: {{ $currencyPrefix }}{{ number_format($wasteTotal, 2) }}</p>
                </div>
                @if($wasteMovements->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[850px] text-left">
                            <caption class="sr-only">Inventory movements recorded as waste, including quantity and cost.</caption>
                            <thead class="bg-[#F8FBFA] text-[11px] font-extrabold uppercase tracking-[.1em] text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3 sm:px-6">Date</th>
                                    <th scope="col" class="px-4 py-3">Ingredient / reason</th>
                                    <th scope="col" class="px-4 py-3">Recorded by</th>
                                    <th scope="col" class="px-4 py-3 text-right">Quantity</th>
                                    <th scope="col" class="px-4 py-3 text-right">Unit cost</th>
                                    <th scope="col" class="px-5 py-3 text-right sm:px-6">Waste cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EAF2EF]">
                                @foreach($wasteMovements as $movement)
                                    @php($estimatedWasteCost = $movement->unit_cost_at_movement === null || (float) $movement->unit_cost_at_movement <= 0)
                                    <tr class="transition hover:bg-[#FBFDFC]">
                                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600 sm:px-6">{{ $movement->created_at?->format('M j, Y · g:i A') }}</td>
                                        <th scope="row" class="px-4 py-4">
                                            <p class="font-bold">{{ $movement->stock_name }}</p>
                                            <p class="mt-1 max-w-sm text-xs font-normal text-slate-500">{{ $movement->reason ?: 'No reason provided' }}</p>
                                        </th>
                                        <td class="px-4 py-4 text-sm text-slate-600">{{ $movement->recorded_by ?: 'Staff account unavailable' }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold">{{ number_format((float) $movement->quantity, 2) }} {{ $movement->stock_unit }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-slate-600">
                                            @if((float) $movement->effective_unit_cost > 0)
                                                {{ $currencyPrefix }}{{ number_format((float) $movement->effective_unit_cost, 2) }}@if($estimatedWasteCost)<span class="ml-1 text-[10px] font-bold text-amber-700">estimated</span>@endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-extrabold text-amber-700 sm:px-6">{{ $currencyPrefix }}{{ number_format((float) $movement->total_cost, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($wasteMovements->hasPages())<div class="border-t border-[#EAF2EF] px-5 py-4">{{ $wasteMovements->links() }}</div>@endif
                @else
                    <p class="px-6 py-12 text-center text-sm text-slate-500">No inventory reductions were explicitly recorded as waste for these dates.</p>
                @endif
            </section>
        </div>
    </main>
</div>
</body>
</html>
