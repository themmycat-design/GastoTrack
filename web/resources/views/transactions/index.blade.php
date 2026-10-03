<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - GastoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            <h1 class="text-3xl font-bold text-gray-900">All Transactions</h1>
            <p class="text-gray-600 mt-1">View all transactions recorded by staff members</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" action="{{ route('transactions.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Description, customer..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                </div>

                <!-- Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                        <option value="">All Types</option>
                        <option value="income" {{ request('type') == 'income' ? 'selected' : '' }}>Income</option>
                        <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>Expense</option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                </div>

                <!-- Buttons -->
                <div class="md:col-span-4 flex space-x-3">
                    <button type="submit" class="px-6 py-2 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Apply Filters
                    </button>
                    <a href="{{ route('transactions.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Income</p>
                        <p class="text-2xl font-bold text-green-600">₱{{ number_format($totalIncome, 2) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">↑</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Expenses</p>
                        <p class="text-2xl font-bold text-red-600">₱{{ number_format($totalExpense, 2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl text-red-600">↓</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Net Total</p>
                        <p class="text-2xl font-bold" style="color: #00C897;">₱{{ number_format($totalIncome - $totalExpense, 2) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">💰</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Member</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($transactions as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ \Carbon\Carbon::parse($transaction->transaction_date)->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($transaction->type == 'income')
                                <span class="px-3 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Income</span>
                            @else
                                <span class="px-3 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Expense</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $transaction->description ?? 'No description' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $transaction->user->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold" style="color: {{ $transaction->type == 'income' ? '#10B981' : '#E53935' }};">
                            {{ $transaction->type == 'income' ? '+' : '-' }}₱{{ number_format($transaction->amount, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <span class="text-4xl mb-2">📊</span>
                                <p class="text-lg font-medium">No transactions found</p>
                                <p class="text-sm mt-1">Try adjusting your filters or add a new transaction</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                {{ $transactions->links() }}
            </div>
        </div>
            </div>
        </div>
    </div>
</body>
</html>
