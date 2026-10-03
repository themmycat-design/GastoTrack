<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - GastoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen lg:flex">
        @include('layouts.owner-navigation')

        <!-- Main Content -->
        <div class="min-w-0 flex-1">
            <div class="mx-auto max-w-7xl px-5 py-6 sm:px-8 sm:py-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Sales Analytics</h1>
            <p class="text-gray-600 mt-1">Performance insights and trends for {{ now()->format('F Y') }}</p>
        </div>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Revenue Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-600">Monthly Revenue</p>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-xl">💰</span>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 mb-1">₱{{ number_format($currentMonthRevenue, 2) }}</p>
                @if($revenueGrowth != 0)
                <div class="flex items-center text-sm">
                    @if($revenueGrowth > 0)
                    <span class="text-green-600 font-semibold">↑ {{ number_format(abs($revenueGrowth), 1) }}%</span>
                    @else
                    <span class="text-red-600 font-semibold">↓ {{ number_format(abs($revenueGrowth), 1) }}%</span>
                    @endif
                    <span class="text-gray-500 ml-2">vs last month</span>
                </div>
                @endif
            </div>

            <!-- Expenses Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-600">Monthly Expenses</p>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <span class="text-xl text-red-600">💸</span>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 mb-1">₱{{ number_format($currentMonthExpenses, 2) }}</p>
                @if($expenseGrowth != 0)
                <div class="flex items-center text-sm">
                    @if($expenseGrowth > 0)
                    <span class="text-red-600 font-semibold">↑ {{ number_format(abs($expenseGrowth), 1) }}%</span>
                    @else
                    <span class="text-green-600 font-semibold">↓ {{ number_format(abs($expenseGrowth), 1) }}%</span>
                    @endif
                    <span class="text-gray-500 ml-2">vs last month</span>
                </div>
                @endif
            </div>

            <!-- Net Profit Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-600">Net Profit</p>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-xl text-blue-600">📊</span>
                    </div>
                </div>
                <p class="text-2xl font-bold mb-1" style="color: {{ ($currentMonthRevenue - $currentMonthExpenses) >= 0 ? '#00C897' : '#E53935' }};">
                    ₱{{ number_format($currentMonthRevenue - $currentMonthExpenses, 2) }}
                </p>
                <div class="flex items-center text-sm">
                    <span class="text-gray-500">
                        {{ $currentMonthRevenue > 0 ? number_format((($currentMonthRevenue - $currentMonthExpenses) / $currentMonthRevenue) * 100, 1) : 0 }}% margin
                    </span>
                </div>
            </div>

            <!-- Transactions Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-600">Transactions</p>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <span class="text-xl text-purple-600">📝</span>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 mb-1">{{ number_format($transactionCount) }}</p>
                @if($transactionGrowth != 0)
                <div class="flex items-center text-sm">
                    @if($transactionGrowth > 0)
                    <span class="text-green-600 font-semibold">↑ {{ number_format(abs($transactionGrowth), 1) }}%</span>
                    @else
                    <span class="text-red-600 font-semibold">↓ {{ number_format(abs($transactionGrowth), 1) }}%</span>
                    @endif
                    <span class="text-gray-500 ml-2">vs last month</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Revenue vs Expenses Chart -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Revenue vs Expenses</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="revenueExpensesChart"></canvas>
                </div>
            </div>

            <!-- Monthly Comparison Chart -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Month-over-Month Comparison</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="monthComparisonChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Stock Items -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Top Stock Items by Value</h3>
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
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Business Insights</h3>
                <div class="space-y-4">
                    <!-- Insight 1: Profitability -->
                    @php
                        $profitMargin = $currentMonthRevenue > 0 ? (($currentMonthRevenue - $currentMonthExpenses) / $currentMonthRevenue) * 100 : 0;
                    @endphp
                    <div class="p-4 rounded-lg" style="background-color: {{ $profitMargin >= 20 ? 'rgba(0, 200, 151, 0.1)' : 'rgba(229, 57, 53, 0.1)' }};">
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
                    <div class="p-4 rounded-lg" style="background-color: {{ $revenueGrowth > 0 ? 'rgba(0, 200, 151, 0.1)' : 'rgba(229, 57, 53, 0.1)' }};">
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
                    <div class="p-4 rounded-lg bg-blue-50">
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
                            <a href="{{ route('transactions.index') }}" class="px-4 py-2 text-sm font-medium text-white rounded-lg hover:opacity-90" style="background-color: #00C897;">View Transactions</a>
                            <a href="{{ route('stock.index') }}" class="px-4 py-2 text-sm font-medium bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Manage Stock</a>
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
                    borderColor: '#E53935',
                    backgroundColor: 'rgba(229, 57, 53, 0.1)',
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
</body>
</html>
