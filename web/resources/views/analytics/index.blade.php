<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analytics | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
    <div class="min-h-screen lg:flex">
        @include('layouts.owner-navigation')

        <main class="min-w-0 flex-1">
            @include('layouts.owner-topbar')

            <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
        <section class="gt-enter">
            <p class="text-sm font-semibold text-[#00A87E]">Business performance · {{ now()->format('F Y') }}</p>
            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Analytics</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Explore sales, expenses, trends, and inventory value to understand how your shop is performing.</p>
        </section>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <!-- Revenue Card -->
            <div class="gt-card gt-lift gt-enter gt-enter-1 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-semibold text-slate-500">Monthly revenue</p>
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="12" cy="12" r="3"/><path d="M7 9h.01M17 15h.01"/></svg>
                    </div>
                </div>
                <p class="mb-1 text-2xl font-extrabold text-[#08745D]">&#8369;{{ number_format($currentMonthRevenue, 2) }}</p>
                @if($revenueGrowth != 0)
                <div class="flex items-center text-sm">
                    @if($revenueGrowth > 0)
                    <span class="font-semibold text-[#08745D]">↑ {{ number_format(abs($revenueGrowth), 1) }}%</span>
                    @else
                    <span class="font-semibold text-rose-600">↓ {{ number_format(abs($revenueGrowth), 1) }}%</span>
                    @endif
                    <span class="ml-2 text-slate-500">vs last month</span>
                </div>
                @endif
            </div>

            <!-- Expenses Card -->
            <div class="gt-card gt-lift gt-enter gt-enter-2 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-semibold text-slate-500">Monthly expenses</p>
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50">
                        <span class="text-xl text-rose-600">↓</span>
                    </div>
                </div>
                <p class="mb-1 text-2xl font-extrabold text-rose-600">&#8369;{{ number_format($currentMonthExpenses, 2) }}</p>
                @if($expenseGrowth != 0)
                <div class="flex items-center text-sm">
                    @if($expenseGrowth > 0)
                    <span class="font-semibold text-rose-600">↑ {{ number_format(abs($expenseGrowth), 1) }}%</span>
                    @else
                    <span class="font-semibold text-[#08745D]">↓ {{ number_format(abs($expenseGrowth), 1) }}%</span>
                    @endif
                    <span class="ml-2 text-slate-500">vs last month</span>
                </div>
                @endif
            </div>

            <!-- Net Profit Card -->
            <div class="gt-card gt-lift gt-enter gt-enter-3 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-semibold text-slate-500">Net profit</p>
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#EAF8F2]">
                        <span class="text-xl text-[#08745D]">↗</span>
                    </div>
                </div>
                <p class="mb-1 text-2xl font-extrabold {{ ($currentMonthRevenue - $currentMonthExpenses) >= 0 ? 'text-[#08745D]' : 'text-rose-600' }}">
                        &#8369;{{ number_format($currentMonthRevenue - $currentMonthExpenses, 2) }}
                </p>
                <div class="flex items-center text-sm">
                    <span class="text-slate-500">
                        {{ $currentMonthRevenue > 0 ? number_format((($currentMonthRevenue - $currentMonthExpenses) / $currentMonthRevenue) * 100, 1) : 0 }}% margin
                    </span>
                </div>
            </div>

            <!-- Transactions Card -->
            <div class="gt-card gt-lift gt-enter gt-enter-4 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-semibold text-slate-500">Transactions</p>
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#EAF8F2]">
                        <span class="text-xl text-[#08745D]">≡</span>
                    </div>
                </div>
                <p class="mb-1 text-2xl font-extrabold text-[#0A2E2A]">{{ number_format($transactionCount) }}</p>
                @if($transactionGrowth != 0)
                <div class="flex items-center text-sm">
                    @if($transactionGrowth > 0)
                    <span class="font-semibold text-[#08745D]">↑ {{ number_format(abs($transactionGrowth), 1) }}%</span>
                    @else
                    <span class="font-semibold text-rose-600">↓ {{ number_format(abs($transactionGrowth), 1) }}%</span>
                    @endif
                    <span class="ml-2 text-slate-500">vs last month</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Revenue vs Expenses Chart -->
            <div class="gt-card gt-enter gt-enter-2 p-5 sm:p-6">
                <h3 class="mb-4 text-lg font-extrabold text-[#0A2E2A]">Revenue vs Expenses</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="revenueExpensesChart"></canvas>
                </div>
            </div>

            <!-- Monthly Comparison Chart -->
            <div class="gt-card gt-enter gt-enter-3 p-5 sm:p-6">
                <h3 class="mb-4 text-lg font-extrabold text-[#0A2E2A]">Month-over-Month Comparison</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="monthComparisonChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Top Stock Items -->
            <div class="gt-card gt-enter gt-enter-3 p-5 sm:p-6">
                <h3 class="mb-4 text-lg font-extrabold text-[#0A2E2A]">Top Stock Items by Value</h3>
                <div class="space-y-4">
                    @forelse($topStockItems as $index => $item)
                    <div class="flex items-center justify-between pb-4 {{ $loop->last ? '' : 'border-b border-gray-100' }}">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold" style="background-color: #00C897;">
                                {{ $index + 1 }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">{{ number_format($item->current_quantity, 2) }} {{ $item->unit }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold" style="color: #00C897;">₱{{ number_format($item->current_quantity * $item->unit_cost, 2) }}</p>
                            <p class="text-xs text-gray-500">@ ₱{{ number_format($item->unit_cost, 2) }}/{{ $item->unit }}</p>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-8 text-gray-500">
                        <span class="text-4xl mb-2 block">📦</span>
                        <p>No stock items available</p>
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Insights & Tips -->
            <div class="gt-card gt-enter gt-enter-4 p-5 sm:p-6">
                <h3 class="mb-4 text-lg font-extrabold text-[#0A2E2A]">Business Insights</h3>
                <div class="space-y-4">
                    <!-- Insight 1: Profitability -->
                    @php
                        $profitMargin = $currentMonthRevenue > 0 ? (($currentMonthRevenue - $currentMonthExpenses) / $currentMonthRevenue) * 100 : 0;
                    @endphp
                            <div class="rounded-xl border {{ $profitMargin >= 20 ? 'border-[#C8DED7] bg-[#EAF8F2]' : 'border-rose-100 bg-rose-50' }} p-4">
                        <div class="flex items-start space-x-3">
                            <span class="text-2xl">{{ $profitMargin >= 20 ? '✅' : '⚠️' }}</span>
                            <div>
                                <p class="font-semibold text-gray-900">Profit Margin: {{ number_format($profitMargin, 1) }}%</p>
                                <p class="text-sm text-gray-600 mt-1">
                                    @if($profitMargin >= 20)
                                    Your profit margin is healthy! Keep up the good work.
                                    @elseif($profitMargin >= 10)
                                    Your profit margin is moderate. Consider optimizing costs.
                                    @else
                                    Your profit margin is low. Review expenses and pricing strategy.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Insight 2: Revenue Growth -->
                    <div class="rounded-xl border {{ $revenueGrowth > 0 ? 'border-[#C8DED7] bg-[#EAF8F2]' : 'border-rose-100 bg-rose-50' }} p-4">
                        <div class="flex items-start space-x-3">
                            <span class="text-2xl">{{ $revenueGrowth > 0 ? '📈' : '📉' }}</span>
                            <div>
                                <p class="font-semibold text-gray-900">Revenue Trend</p>
                                <p class="text-sm text-gray-600 mt-1">
                                    @if($revenueGrowth > 0)
                                    Revenue is up {{ number_format(abs($revenueGrowth), 1) }}% from last month. Great progress!
                                    @elseif($revenueGrowth < 0)
                                    Revenue is down {{ number_format(abs($revenueGrowth), 1) }}% from last month. Focus on marketing.
                                    @else
                                    Revenue is stable compared to last month.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Insight 3: Transaction Volume -->
                    <div class="rounded-xl border border-[#DDE9E5] bg-[#F4F8F7] p-4">
                        <div class="flex items-start space-x-3">
                            <span class="text-2xl">💡</span>
                            <div>
                                <p class="font-semibold text-gray-900">Transaction Activity</p>
                                <p class="text-sm text-gray-600 mt-1">
                                    You've processed {{ number_format($transactionCount) }} transactions this month.
                                    @if($transactionGrowth > 0)
                                    Customer activity is increasing!
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="pt-4 border-t border-gray-200">
                        <p class="text-sm font-semibold text-gray-900 mb-3">Quick Actions</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('transactions.index') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#08745D]">View transactions</a>
                            <a href="{{ route('stock.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-sm font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">Manage inventory</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prepare data for charts
        const dailyRevenueData = @json($dailyRevenue);
        const dailyExpensesData = @json($dailyExpenses);
        
        // Create a complete date range for the current month
        const startDate = new Date('{{ now()->startOfMonth()->format('Y-m-d') }}');
        const endDate = new Date('{{ now()->endOfMonth()->format('Y-m-d') }}');
        const dateLabels = [];
        const revenueValues = [];
        const expenseValues = [];
        
        // Generate all dates in the month
        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            const dateStr = d.toISOString().split('T')[0];
            dateLabels.push(d.getDate());
            
            // Find revenue for this date
            const revenueEntry = dailyRevenueData.find(item => item.date === dateStr);
            revenueValues.push(revenueEntry ? parseFloat(revenueEntry.total) : 0);
            
            // Find expense for this date
            const expenseEntry = dailyExpensesData.find(item => item.date === dateStr);
            expenseValues.push(expenseEntry ? parseFloat(expenseEntry.total) : 0);
        }
        
        // Revenue vs Expenses Chart
        const ctx1 = document.getElementById('revenueExpensesChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: dateLabels,
                datasets: [{
                    label: 'Revenue',
                    data: revenueValues,
                    borderColor: '#00C897',
                    backgroundColor: 'rgba(0, 200, 151, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Expenses',
                    data: expenseValues,
                    borderColor: '#E11D48',
                    backgroundColor: 'rgba(225, 29, 72, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Month Comparison Chart
        const ctx2 = document.getElementById('monthComparisonChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Revenue', 'Expenses', 'Net Profit'],
                datasets: [{
                    label: 'Last Month',
                    data: [
                        {{ $lastMonthRevenue }},
                        {{ $lastMonthExpenses }},
                        {{ $lastMonthRevenue - $lastMonthExpenses }}
                    ],
                    backgroundColor: 'rgba(156, 163, 175, 0.6)',
                    borderColor: 'rgba(156, 163, 175, 1)',
                    borderWidth: 1
                }, {
                    label: 'This Month',
                    data: [
                        {{ $currentMonthRevenue }},
                        {{ $currentMonthExpenses }},
                        {{ $currentMonthRevenue - $currentMonthExpenses }}
                    ],
                    backgroundColor: 'rgba(0, 200, 151, 0.6)',
                    borderColor: '#00C897',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
        </main>
    </div>
</body>
</html>
