<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | GastoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand: #00a87e; --brand-dark: #08745d; --canvas: #f4f8f7; }
        body { font-family: Inter, sans-serif; background: var(--canvas); color: #123b35; }
        .brand-bg { background: var(--brand); }
        .brand-text { color: var(--brand-dark); }
        .brand-ring:focus { outline: 3px solid rgba(0,168,126,.25); outline-offset: 2px; }
    </style>
</head>
<body>
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')

    <main class="min-w-0 flex-1">
        <header class="sticky top-0 z-10 border-b border-emerald-100 bg-white/95 backdrop-blur">
            <div class="flex items-center justify-between px-5 py-4 sm:px-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.16em] brand-text">{{ $business?->name ?? 'Your business' }}</p>
                    <h1 class="mt-1 text-xl font-extrabold tracking-tight text-gray-900 sm:text-2xl">Good day, {{ Auth::user()->name }}</h1>
                </div>
                <div class="flex items-center gap-2 lg:hidden">
                    <a href="{{ route('profile.edit') }}" class="flex h-10 w-10 items-center justify-center rounded-full brand-bg font-bold text-white">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</a>
                </div>
                <p class="hidden text-sm text-gray-500 sm:block">{{ $monthLabel }}</p>
            </div>
        </header>

        <div class="mx-auto max-w-7xl space-y-6 px-5 py-6 sm:px-8 sm:py-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm text-gray-500">Here is the financial health of your business.</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-gray-900">Business overview</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('transactions.index') }}" class="brand-ring rounded-xl brand-bg px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:opacity-90">View transactions</a>
                    <a href="{{ route('stock.index') }}" class="brand-ring rounded-xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-bold brand-text hover:bg-emerald-50">Manage inventory</a>
                </div>
            </div>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Business metrics">
                @php
                    $metrics = [
                        ['label' => 'Revenue', 'value' => $totalRevenue, 'caption' => 'This month', 'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
                        ['label' => 'Expenses', 'value' => $totalExpenses, 'caption' => 'This month', 'color' => 'text-rose-600', 'bg' => 'bg-rose-50'],
                        ['label' => 'Net profit', 'value' => $netProfit, 'caption' => 'Revenue less expenses', 'color' => $netProfit >= 0 ? 'text-emerald-700' : 'text-rose-600', 'bg' => $netProfit >= 0 ? 'bg-emerald-50' : 'bg-rose-50'],
                    ];
                @endphp
                @foreach($metrics as $metric)
                    <article class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-500">{{ $metric['label'] }}</p>
                                <p class="mt-3 text-2xl font-extrabold {{ $metric['color'] }}">&#8369;{{ number_format($metric['value'] ?? 0, 2) }}</p>
                                <p class="mt-2 text-xs text-gray-400">{{ $metric['caption'] }}</p>
                            </div>
                            <span class="rounded-xl p-3 text-lg {{ $metric['bg'] }} {{ $metric['color'] }}">&#9670;</span>
                        </div>
                    </article>
                @endforeach
                <article class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-500">Inventory value</p>
                            <p class="mt-3 text-2xl font-extrabold text-sky-700">&#8369;{{ number_format($totalStockValue ?? 0, 2) }}</p>
                            <p class="mt-2 text-xs text-gray-400">{{ $totalProducts }} active products · {{ $totalStaff }} staff</p>
                        </div>
                        <span class="rounded-xl bg-sky-50 p-3 text-lg text-sky-700">&#9632;</span>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
                <article class="rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
                        <div>
                            <h3 class="font-extrabold text-gray-900">Recent transactions</h3>
                            <p class="mt-1 text-xs text-gray-500">The latest money movement in your business.</p>
                        </div>
                        <a href="{{ route('transactions.index') }}" class="text-sm font-bold brand-text hover:underline">View all</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($recentTransactions as $transaction)
                            @php $isIncome = strtolower($transaction->type) === 'income'; @endphp
                            <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isIncome ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' }}">{{ $isIncome ? '↑' : '↓' }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-gray-900">{{ $transaction->category ?: 'Uncategorized' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ optional($transaction->date ?? $transaction->created_at)->format('M d, Y') }} · {{ ucfirst($transaction->source ?? 'Unknown') }}</p>
                                    </div>
                                </div>
                                <p class="shrink-0 text-sm font-extrabold {{ $isIncome ? 'text-emerald-700' : 'text-rose-600' }}">{{ $isIncome ? '+' : '-' }}&#8369;{{ number_format($transaction->amount, 2) }}</p>
                            </div>
                        @empty
                            <div class="px-6 py-12 text-center">
                                <p class="font-bold text-gray-600">No transactions yet</p>
                                <p class="mt-1 text-sm text-gray-400">Your latest income and expenses will appear here.</p>
                            </div>
                        @endforelse
                    </div>
                </article>

                <article class="rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
                        <div>
                            <h3 class="font-extrabold text-gray-900">Needs attention</h3>
                            <p class="mt-1 text-xs text-gray-500">Resolve these before they affect sales.</p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $lowStockCount }} alerts</span>
                    </div>
                    <div class="p-5 sm:p-6">
                        @forelse($lowStockItems as $item)
                            <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-3 first:pt-0 last:border-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-gray-900">{{ $item->name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">Minimum {{ number_format($item->minimum_quantity, 2) }} {{ $item->unit }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-600">{{ number_format($item->current_quantity, 2) }} left</span>
                            </div>
                        @empty
                            <div class="py-8 text-center">
                                <p class="font-bold text-emerald-700">Inventory is healthy</p>
                                <p class="mt-1 text-sm text-gray-400">No items are below their minimum level.</p>
                            </div>
                        @endforelse
                        <a href="{{ route('stock.index') }}" class="mt-5 block rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-bold brand-text hover:bg-emerald-100">Review inventory</a>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-emerald-100 bg-gradient-to-r from-emerald-700 to-emerald-500 p-6 text-white shadow-sm sm:p-7">
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-sm font-semibold text-emerald-100">Next best action</p>
                        <h3 class="mt-1 text-xl font-extrabold">Keep your daily records complete.</h3>
                        <p class="mt-2 max-w-xl text-sm text-emerald-50">Review transactions and stock levels every day so your profit numbers and AI insights stay reliable.</p>
                    </div>
                    <a href="{{ route('analytics.index') }}" class="shrink-0 rounded-xl bg-white px-5 py-3 text-center text-sm font-extrabold brand-text hover:bg-emerald-50">Open analytics</a>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
