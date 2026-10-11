<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
    <div class="min-h-screen lg:flex">
        @include('layouts.owner-navigation')

        <main class="min-w-0 flex-1">
            @include('layouts.owner-topbar')

            <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
        <section class="gt-enter flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold text-[#00A87E]">Ingredients and supplies</p>
                <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Inventory</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Track ingredient quantities, review stock changes, and spot items that need restocking.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="openAddModal()" class="gt-focus inline-flex items-center justify-center gap-2 rounded-xl bg-[#00A87E] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#08745D]">
                    <span aria-hidden="true">+</span> Add stock item
                </button>
                <a href="{{ route('stock.export') }}" class="gt-focus inline-flex items-center justify-center rounded-xl border border-[#C8DED7] bg-white px-5 py-3 text-sm font-bold text-[#08745D] transition hover:bg-[#EAF8F2]" title="Download all non-deleted stock items, including inactive items">Download CSV</a>
            </div>
        </section>

        @php
            $currencyCode = strtoupper((string) (Auth::user()->business?->currency ?: 'PHP'));
            $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ';
            $inPercentage = $totalItems > 0 ? $inStockCount / $totalItems * 100 : 0;
            $lowPercentage = $totalItems > 0 ? $lowStockCount / $totalItems * 100 : 0;
            $outPercentage = $totalItems > 0 ? $outOfStockCount / $totalItems * 100 : 0;
        @endphp
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Current inventory summary">
            <div class="gt-card gt-lift gt-enter gt-enter-1 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Active items</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#0A2E2A]">{{ number_format($totalItems) }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 12v8.5"/></svg>
                    </div>
                </div>
            </div>

            <div class="gt-card gt-lift gt-enter gt-enter-2 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Low stock</p>
                        <p class="mt-2 text-2xl font-extrabold text-amber-700">{{ number_format($lowStockCount) }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 9 17H3L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg>
                    </div>
                </div>
            </div>

            <div class="gt-card gt-lift gt-enter gt-enter-3 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Out of stock</p>
                        <p class="mt-2 text-2xl font-extrabold text-rose-600">{{ number_format($outOfStockCount) }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                    </div>
                </div>
            </div>

            <div class="gt-card gt-lift gt-enter gt-enter-4 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Inventory value</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $totalValue, 2) }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="12" cy="12" r="3"/><path d="M7 9h.01M17 15h.01"/></svg>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
            <section class="gt-card gt-enter min-w-0 overflow-hidden" aria-labelledby="inventory-items-heading">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <div>
                        <h3 id="inventory-items-heading" class="text-lg font-extrabold">Inventory items</h3>
                        <p class="mt-1 text-xs text-slate-500">Search and filter this business's ingredients and supplies.</p>
                    </div>
                    <span class="rounded-full bg-[#EAF8F2] px-3 py-1.5 text-xs font-bold text-[#08745D]">Live stock</span>
                </div>

        <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6">
            <form id="inventory-filter-form" method="GET" action="{{ route('stock.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(150px,1fr)_minmax(130px,.8fr)_minmax(130px,.8fr)_auto] xl:items-end">
                <!-- Search -->
                <div>
                    <label for="inventory-search" class="mb-2 block text-xs font-bold text-slate-600">Search items</label>
                    <input id="inventory-search" type="search" name="search" value="{{ request('search') }}" placeholder="Search by name" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                </div>

                <!-- Low Stock Filter -->
                <div>
                    <label for="inventory-stock-status" class="mb-2 block text-xs font-bold text-slate-600">Stock level</label>
                    <select id="inventory-stock-status" name="stock_status" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm text-[#0A2E2A] focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                        <option value="">All levels</option>
                        <option value="in" {{ request('stock_status') === 'in' ? 'selected' : '' }}>In stock</option>
                        <option value="low" {{ request('stock_status') === 'low' ? 'selected' : '' }}>Low stock</option>
                        <option value="out" {{ request('stock_status') === 'out' ? 'selected' : '' }}>Out of stock</option>
                        <option value="attention" {{ request('low_stock') == '1' || request('stock_status') === 'attention' ? 'selected' : '' }}>Needs attention</option>
                    </select>
                </div>

                <!-- Active Filter -->
                <div>
                    <label for="inventory-availability" class="mb-2 block text-xs font-bold text-slate-600">Availability</label>
                    <select id="inventory-availability" name="active" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm text-[#0A2E2A] focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                        <option value="">All items</option>
                        <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
                    <button type="submit" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#08745D]">
                        Filter
                    </button>
                    <a id="clear-inventory-filters" href="{{ route('stock.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-sm font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <div id="owner-inventory-items" class="min-w-0 overflow-hidden" aria-live="polite" aria-busy="false">
            <div data-inventory-loading class="hidden border-b border-[#C8DED7] bg-[#EAF8F2] px-5 py-3 text-sm font-semibold text-[#08745D] sm:px-6" role="status">Updating inventory list...</div>
            <div class="flex items-center justify-between gap-3 px-5 py-3 text-xs text-slate-500 sm:px-6">
                <span>{{ number_format($stockItems->total()) }} {{ $stockItems->total() === 1 ? 'item' : 'items' }} match the current filters</span>
                <span>20 per page</span>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] divide-y divide-[#EAF2EF] text-left">
                <caption class="sr-only">Inventory items for {{ Auth::user()->business?->name }}</caption>
                <thead class="bg-[#F8FCFA]">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">Item</th>
                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Current stock</th>
                        <th scope="col" class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Value</th>
                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                        <th scope="col" class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Updated</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EAF2EF] bg-white">
                    @forelse($stockItems as $item)
                    @php
                        $isOut = (float) $item->current_quantity <= 0;
                        $isLow = !$isOut && (float) $item->current_quantity <= (float) $item->minimum_quantity;
                    @endphp
                    <tr class="transition hover:bg-[#FBFDFC]">
                        <th scope="row" class="px-5 py-4 sm:px-6">
                            <span class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isOut && $item->active ? 'bg-rose-50 text-rose-600' : ($isLow && $item->active ? 'bg-amber-50 text-amber-700' : 'bg-[#EAF8F2] text-[#08745D]') }}" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 12v8.5"/></svg>
                                </span>
                                <span class="text-sm font-bold text-[#0A2E2A]">{{ $item->name }}</span>
                            </span>
                        </th>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-semibold {{ $isOut ? 'text-rose-600' : ($isLow ? 'text-amber-700' : 'text-[#0A2E2A]') }}">
                                {{ number_format((float) $item->current_quantity, 2) }} {{ $item->unit }}
                            </span>
                            <span class="mt-1 block text-xs text-slate-500">Min {{ number_format((float) $item->minimum_quantity, 2) }} {{ $item->unit }}</span>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-bold text-[#08745D]">
                            {{ $currencyPrefix }}{{ number_format((float) $item->current_quantity * (float) $item->unit_cost, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if(!$item->active)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Inactive</span>
                            @elseif($isOut)
                                <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700">Out of stock</span>
                            @elseif($isLow)
                                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">Low stock</span>
                            @else
                                <span class="rounded-full bg-[#EAF8F2] px-3 py-1 text-xs font-bold text-[#08745D]">In stock</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-xs text-slate-500"><time datetime="{{ $item->updated_at?->toIso8601String() }}">{{ $item->updated_at?->format('M j, Y') ?: '—' }}</time></td>
                        <td class="px-5 py-4 whitespace-nowrap text-right text-xs sm:px-6">
                            <button type="button" data-stock-adjust data-id="{{ $item->id }}" data-name="{{ $item->name }}" data-current="{{ $item->current_quantity }}" class="gt-focus rounded-lg px-2 py-1.5 font-bold text-[#08745D] hover:bg-[#EAF8F2]">Adjust</button>
                            <button type="button" data-stock-edit data-id="{{ $item->id }}" data-name="{{ $item->name }}" data-unit="{{ $item->unit }}" data-current="{{ $item->current_quantity }}" data-minimum="{{ $item->minimum_quantity }}" data-cost="{{ $item->unit_cost }}" data-active="{{ $item->active ? '1' : '0' }}" class="gt-focus rounded-lg px-2 py-1.5 font-bold text-slate-600 hover:bg-[#F4F8F7]">Edit</button>
                            <button type="button" data-stock-delete data-id="{{ $item->id }}" class="gt-focus rounded-lg px-2 py-1.5 font-bold text-rose-600 hover:bg-rose-50">Delete</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-14 text-center text-slate-500">
                            <div class="flex flex-col items-center">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF8F2] text-xl text-[#08745D]" aria-hidden="true">▦</span>
                                <p class="mt-3 text-sm font-bold text-[#0A2E2A]">No stock items found</p>
                                <p class="mt-1 text-sm">{{ request()->hasAny(['search', 'stock_status', 'low_stock', 'active']) ? 'Try clearing the filters to see more items.' : 'Add your first stock item to start tracking inventory.' }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <!-- Pagination -->
            <div class="border-t border-[#EAF2EF] bg-[#FBFDFC] px-5 py-4 sm:px-6">
                {{ $stockItems->links() }}
            </div>
        </div>
            </section>

            <aside class="space-y-5" aria-label="Inventory overview and activity">
                <section class="gt-card gt-enter p-5 sm:p-6" aria-labelledby="stock-overview-heading">
                    <h3 id="stock-overview-heading" class="text-lg font-extrabold">Stock overview</h3>
                    <p class="mt-1 text-xs text-slate-500">Active items by current quantity and minimum level.</p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-6 xl:justify-between">
                        <div class="flex h-40 w-40 shrink-0 items-center justify-center rounded-full p-4" @if($totalItems > 0) style="background: conic-gradient(#00A87E 0 {{ $inPercentage }}%, #F59E0B {{ $inPercentage }}% {{ $inPercentage + $lowPercentage }}%, #E11D48 {{ $inPercentage + $lowPercentage }}% 100%)" @else style="background: #EAF2EF" @endif role="img" aria-label="{{ $inStockCount }} in stock, {{ $lowStockCount }} low stock, {{ $outOfStockCount }} out of stock">
                            <div class="flex h-full w-full flex-col items-center justify-center rounded-full bg-white text-center shadow-sm">
                                <strong class="text-3xl font-extrabold text-[#0A2E2A]">{{ number_format($totalItems) }}</strong>
                                <span class="text-xs text-slate-500">active items</span>
                            </div>
                        </div>
                        <div class="min-w-[165px] flex-1 space-y-3 text-sm">
                            <a href="{{ route('stock.index', ['stock_status' => 'in', 'active' => 1]) }}" class="gt-focus flex items-center justify-between gap-3 rounded-lg px-2 py-1 hover:bg-[#EAF8F2]"><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-[#00A87E]"></span>In stock</span><strong>{{ $inStockCount }} <span class="text-xs font-medium text-slate-400">({{ number_format($inPercentage, 0) }}%)</span></strong></a>
                            <a href="{{ route('stock.index', ['stock_status' => 'low', 'active' => 1]) }}" class="gt-focus flex items-center justify-between gap-3 rounded-lg px-2 py-1 hover:bg-amber-50"><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Low stock</span><strong>{{ $lowStockCount }} <span class="text-xs font-medium text-slate-400">({{ number_format($lowPercentage, 0) }}%)</span></strong></a>
                            <a href="{{ route('stock.index', ['stock_status' => 'out', 'active' => 1]) }}" class="gt-focus flex items-center justify-between gap-3 rounded-lg px-2 py-1 hover:bg-rose-50"><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Out of stock</span><strong>{{ $outOfStockCount }} <span class="text-xs font-medium text-slate-400">({{ number_format($outPercentage, 0) }}%)</span></strong></a>
                        </div>
                    </div>
                    @if($lowStockCount + $outOfStockCount > 0)
                        <a href="{{ route('stock.index', ['low_stock' => 1, 'active' => 1]) }}" class="gt-focus mt-6 block rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 hover:bg-amber-100">{{ $lowStockCount + $outOfStockCount }} {{ $lowStockCount + $outOfStockCount === 1 ? 'item needs' : 'items need' }} attention <span aria-hidden="true">→</span></a>
                    @endif
                </section>

                <section class="gt-card gt-enter overflow-hidden" aria-labelledby="recent-stock-activity-heading">
                    <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6">
                        <h3 id="recent-stock-activity-heading" class="text-lg font-extrabold">Recent activity</h3>
                        <p class="mt-1 text-xs text-slate-500">Latest recorded quantity changes for this business.</p>
                    </div>
                    <div class="divide-y divide-[#EAF2EF]">
                        @forelse($recentMovements as $movement)
                            @php
                                $change = (float) $movement->new_quantity - (float) $movement->previous_quantity;
                                $movementLabel = $movement->order_id ? 'Used for order' : ($movement->type === 'in' ? 'Stock added' : ($movement->type === 'out' ? 'Stock reduced' : 'Quantity set'));
                            @endphp
                            <div class="flex items-start gap-3 px-5 py-3 sm:px-6">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $change < 0 ? 'bg-amber-50 text-amber-700' : 'bg-[#EAF8F2] text-[#08745D]' }}" aria-hidden="true">{{ $change < 0 ? '−' : ($change > 0 ? '+' : '•') }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-[#0A2E2A]">{{ $movement->stock_name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $movementLabel }} · {{ $change > 0 ? '+' : '' }}{{ number_format($change, 2) }} {{ $movement->stock_unit }}</p>
                                    @if($movement->reason && $movement->reason !== 'Manual stock adjustment')
                                        <p class="mt-0.5 truncate text-xs text-slate-400">{{ $movement->reason }}</p>
                                    @endif
                                </div>
                                <time datetime="{{ $movement->created_at?->toIso8601String() }}" class="shrink-0 text-[11px] text-slate-400" title="{{ $movement->created_at?->format('M j, Y g:i A') }}">{{ $movement->created_at?->format('M j') }}</time>
                            </div>
                        @empty
                            <p class="px-5 py-8 text-center text-sm text-slate-500">Stock adjustments and order usage will appear here.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <!-- Add/Edit Stock Modal -->
    <div id="stockModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-[#0A2E2A]/45 p-4 backdrop-blur-sm">
        <div class="gt-card w-full max-w-2xl p-5 shadow-2xl sm:p-7">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-2xl font-extrabold text-[#0A2E2A]">Add Stock Item</h2>
                <button onclick="closeStockModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <form id="stockForm" method="POST" action="{{ route('stock.store') }}">
                @csrf
                <input type="hidden" id="stockId" name="stock_id">
                <input type="hidden" id="formMethod" name="_method">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Item Name -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Item Name *</label>
                        <input type="text" id="name" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="e.g., Milk Powder">
                    </div>

                    <!-- Unit -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit *</label>
                        <input type="text" id="unit" name="unit" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="e.g., kg, liters, pieces">
                    </div>

                    <!-- Unit Cost -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit Cost *</label>
                        <input type="number" id="unit_cost" name="unit_cost" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="0.00">
                    </div>

                    <!-- Current Quantity -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Quantity *</label>
                        <input type="number" id="current_quantity" name="current_quantity" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="0.00">
                        <p id="quantity_help" class="hidden text-xs text-gray-500 mt-1">Use Adjust to change quantity after an item has been created.</p>
                    </div>

                    <!-- Minimum Quantity -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Quantity *</label>
                        <input type="number" id="minimum_quantity" name="minimum_quantity" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="0.00">
                    </div>

                    <!-- Active Status -->
                    <div class="md:col-span-2">
                        <label class="flex items-center">
                            <input type="checkbox" id="active" name="active" value="1" checked class="w-4 h-4 rounded" style="color: #00C897;">
                            <span class="ml-2 text-sm font-medium text-gray-700">Item is active</span>
                        </label>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="gt-focus flex-1 rounded-xl bg-[#00A87E] px-5 py-3 font-bold text-white transition hover:bg-[#08745D]">
                        Save Stock Item
                    </button>
                    <button type="button" onclick="closeStockModal()" class="gt-focus flex-1 rounded-xl border border-[#C8DED7] bg-white px-5 py-3 font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-[#0A2E2A]/45 p-4 backdrop-blur-sm">
        <div class="gt-card w-full max-w-md p-5 shadow-2xl sm:p-7">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-900">Adjust Stock</h2>
                <button onclick="closeAdjustModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <form id="adjustForm" onsubmit="submitAdjustment(event)">
                <input type="hidden" id="adjust_stock_id">

                <!-- Item Info -->
                <div class="mb-4 rounded-xl border border-[#DDE9E5] bg-[#F4F8F7] p-4">
                    <p class="text-sm text-slate-500">Item</p>
                    <p id="adjust_item_name" class="text-lg font-extrabold text-[#0A2E2A]"></p>
                    <p class="mt-2 text-sm text-slate-500">Current quantity: <span id="adjust_current_qty" class="font-bold text-[#0A2E2A]"></span></p>
                </div>

                <!-- Adjustment Type -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Adjustment Type *</label>
                    <select id="adjust_type" name="type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                        <option value="add">Add Stock</option>
                        <option value="subtract">Subtract Stock</option>
                        <option value="set">Set Quantity</option>
                    </select>
                </div>

                <!-- Quantity -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantity *</label>
                    <input type="number" id="adjust_quantity" name="quantity" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="0.00">
                </div>

                <!-- Reason -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                    <textarea id="adjust_reason" name="reason" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="Optional notes..."></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex space-x-3">
                    <button type="submit" class="gt-focus flex-1 rounded-xl bg-[#00A87E] px-5 py-3 font-bold text-white transition hover:bg-[#08745D]">
                        Apply Adjustment
                    </button>
                    <button type="button" onclick="closeAdjustModal()" class="gt-focus flex-1 rounded-xl border border-[#C8DED7] bg-white px-5 py-3 font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('click', (event) => {
            const adjustButton = event.target.closest('[data-stock-adjust]');
            if (adjustButton) {
                openAdjustModal(adjustButton.dataset.id, adjustButton.dataset.name, adjustButton.dataset.current);
                return;
            }
            const editButton = event.target.closest('[data-stock-edit]');
            if (editButton) {
                openEditModal(
                    editButton.dataset.id,
                    editButton.dataset.name,
                    editButton.dataset.unit,
                    editButton.dataset.current,
                    editButton.dataset.minimum,
                    editButton.dataset.cost,
                    editButton.dataset.active === '1',
                );
                return;
            }
            const deleteButton = event.target.closest('[data-stock-delete]');
            if (deleteButton) deleteStock(deleteButton.dataset.id);
        });

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Stock Item';
            document.getElementById('stockForm').action = '{{ route("stock.store") }}';
            document.getElementById('stockId').value = '';
            document.getElementById('formMethod').value = '';
            document.getElementById('name').value = '';
            document.getElementById('unit').value = '';
            document.getElementById('current_quantity').value = '';
            document.getElementById('current_quantity').disabled = false;
            document.getElementById('current_quantity').required = true;
            document.getElementById('quantity_help').classList.add('hidden');
            document.getElementById('minimum_quantity').value = '';
            document.getElementById('unit_cost').value = '';
            document.getElementById('active').checked = true;
            document.getElementById('stockModal').classList.remove('hidden');
        }

        function openEditModal(id, name, unit, currentQty, minQty, unitCost, isActive) {
            document.getElementById('modalTitle').textContent = 'Edit Stock Item';
            document.getElementById('stockForm').action = '/stock/' + id;
            document.getElementById('stockId').value = id;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('name').value = name;
            document.getElementById('unit').value = unit;
            document.getElementById('current_quantity').value = currentQty;
            document.getElementById('current_quantity').disabled = true;
            document.getElementById('current_quantity').required = false;
            document.getElementById('quantity_help').classList.remove('hidden');
            document.getElementById('minimum_quantity').value = minQty;
            document.getElementById('unit_cost').value = unitCost;
            document.getElementById('active').checked = isActive;
            document.getElementById('stockModal').classList.remove('hidden');
        }

        function closeStockModal() {
            document.getElementById('stockModal').classList.add('hidden');
        }

        function openAdjustModal(id, name, currentQty) {
            document.getElementById('adjust_stock_id').value = id;
            document.getElementById('adjust_item_name').textContent = name;
            document.getElementById('adjust_current_qty').textContent = parseFloat(currentQty).toFixed(2);
            document.getElementById('adjust_type').value = 'add';
            document.getElementById('adjust_quantity').value = '';
            document.getElementById('adjust_reason').value = '';
            document.getElementById('adjustModal').classList.remove('hidden');
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
        }

        function submitAdjustment(event) {
            event.preventDefault();
            
            const stockId = document.getElementById('adjust_stock_id').value;
            const type = document.getElementById('adjust_type').value;
            const quantity = document.getElementById('adjust_quantity').value;
            const reason = document.getElementById('adjust_reason').value;
            
            fetch('/stock/' + stockId + '/adjust', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: type,
                    quantity: quantity,
                    reason: reason
                })
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Unable to adjust stock.');
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Unable to adjust stock.');
                }
            })
            .catch(error => {
                alert(error.message || 'Unable to adjust stock.');
            });
        }

        function deleteStock(id) {
            if (confirm('Are you sure you want to delete this stock item? This action cannot be undone.')) {
                fetch('/stock/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting stock item');
                    }
                })
                .catch(error => {
                    alert('Error deleting stock item');
                });
            }
        }

        (() => {
            const form = document.getElementById('inventory-filter-form');
            const clearLink = document.getElementById('clear-inventory-filters');
            let requestController = null;
            let requestSequence = 0;

            const cleanUrl = (value) => {
                const url = new URL(value, window.location.origin);
                url.searchParams.delete('fragment');
                return url;
            };

            async function loadInventory(value, updateHistory = false) {
                const displayUrl = cleanUrl(value);
                requestController?.abort();
                const controller = new AbortController();
                const sequence = ++requestSequence;
                requestController = controller;

                const currentList = document.getElementById('owner-inventory-items');
                const loadingMessage = currentList.querySelector('[data-inventory-loading]');
                currentList.setAttribute('aria-busy', 'true');
                currentList.classList.add('opacity-60');
                loadingMessage.textContent = 'Updating inventory list...';
                loadingMessage.classList.remove('hidden');

                try {
                    const response = await fetch(displayUrl, {
                        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('Inventory could not be loaded.');

                    const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const updatedList = responseDocument.getElementById('owner-inventory-items');
                    if (!updatedList) throw new Error('Inventory response was incomplete.');
                    if (sequence !== requestSequence) return;

                    currentList.replaceWith(updatedList);
                    if (updateHistory) {
                        window.history.pushState({}, '', displayUrl.pathname + displayUrl.search);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError' && sequence === requestSequence) {
                        currentList.setAttribute('aria-busy', 'false');
                        currentList.classList.remove('opacity-60');
                        loadingMessage.textContent = 'Could not refresh inventory. Please try again.';
                        loadingMessage.classList.remove('hidden');
                    }
                } finally {
                    if (requestController === controller) requestController = null;
                }
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const url = new URL(form.action, window.location.origin);
                new FormData(form).forEach((value, key) => {
                    if (String(value).trim()) url.searchParams.set(key, value);
                });
                loadInventory(url, true);
            });

            clearLink.addEventListener('click', (event) => {
                event.preventDefault();
                form.querySelector('[name="search"]').value = '';
                form.querySelector('[name="stock_status"]').value = '';
                form.querySelector('[name="active"]').value = '';
                loadInventory(clearLink.href, true);
            });

            document.addEventListener('click', (event) => {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                const pageLink = event.target.closest('#owner-inventory-items nav[role="navigation"] a[href], #owner-inventory-items .pagination a[href]');
                if (!pageLink) return;
                event.preventDefault();
                loadInventory(pageLink.href, true);
            });

            window.addEventListener('popstate', () => {
                const url = new URL(window.location.href);
                form.querySelector('[name="search"]').value = url.searchParams.get('search') || '';
                form.querySelector('[name="stock_status"]').value = url.searchParams.get('stock_status') || (url.searchParams.get('low_stock') === '1' ? 'attention' : '');
                form.querySelector('[name="active"]').value = url.searchParams.get('active') || '';
                loadInventory(url);
            });
        })();

        // Close modals when clicking outside
        document.getElementById('stockModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStockModal();
            }
        });

        document.getElementById('adjustModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdjustModal();
            }
        });
    </script>
        </main>
    </div>
</body>
</html>
