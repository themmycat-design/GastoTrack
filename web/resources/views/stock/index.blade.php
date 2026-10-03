<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - GastoTrack</title>
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
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Stock Management</h1>
                <p class="text-gray-600 mt-1">Monitor and manage your inventory levels</p>
            </div>
            <button onclick="openAddModal()" class="px-6 py-3 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all" style="background-color: #00C897;">
                + Add Stock Item
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Items</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $totalItems }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">📦</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Low Stock Items</p>
                        <p class="text-2xl font-bold text-red-600">{{ $lowStockCount }}</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl text-red-600">⚠️</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Inventory Value</p>
                        <p class="text-2xl font-bold" style="color: #00C897;">₱{{ number_format($totalValue, 2) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">💰</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" action="{{ route('stock.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Item name..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                </div>

                <!-- Low Stock Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stock Status</label>
                    <select name="low_stock" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                        <option value="">All Items</option>
                        <option value="1" {{ request('low_stock') == '1' ? 'selected' : '' }}>Low Stock Only</option>
                    </select>
                </div>

                <!-- Active Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="active" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                        <option value="">All</option>
                        <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end space-x-3">
                    <button type="submit" class="px-6 py-2 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Apply Filters
                    </button>
                    <a href="{{ route('stock.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Stock Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Qty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Qty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($stockItems as $item)
                    <tr class="hover:bg-gray-50 {{ $item->current_quantity <= $item->minimum_quantity ? 'bg-red-50' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                @if($item->current_quantity <= $item->minimum_quantity)
                                <span class="text-red-600 mr-2">⚠️</span>
                                @endif
                                <span class="text-sm font-medium text-gray-900">{{ $item->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-semibold {{ $item->current_quantity <= $item->minimum_quantity ? 'text-red-600' : 'text-gray-900' }}">
                                {{ number_format($item->current_quantity, 2) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ number_format($item->minimum_quantity, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $item->unit }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ₱{{ number_format($item->unit_cost, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold" style="color: #00C897;">
                            ₱{{ number_format($item->current_quantity * $item->unit_cost, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($item->active)
                                <span class="px-3 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Active</span>
                            @else
                                <span class="px-3 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <button onclick="openAdjustModal({{ $item->id }}, '{{ addslashes($item->name) }}', {{ $item->current_quantity }})" class="font-medium hover:underline" style="color: #00C897;">Adjust</button>
                            <button onclick="openEditModal({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ $item->unit }}', {{ $item->current_quantity }}, {{ $item->minimum_quantity }}, {{ $item->unit_cost }}, {{ $item->active ? 'true' : 'false' }})" class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                            <button onclick="deleteStock({{ $item->id }})" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <span class="text-4xl mb-2">📦</span>
                                <p class="text-lg font-medium">No stock items found</p>
                                <p class="text-sm mt-1">Add your first stock item to start tracking inventory</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                {{ $stockItems->links() }}
            </div>
        </div>
    </div>

    <!-- Add/Edit Stock Modal -->
    <div id="stockModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Add Stock Item</h2>
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
                    <button type="submit" class="flex-1 px-6 py-3 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Save Stock Item
                    </button>
                    <button type="button" onclick="closeStockModal()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-900">Adjust Stock</h2>
                <button onclick="closeAdjustModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <form id="adjustForm" onsubmit="submitAdjustment(event)">
                <input type="hidden" id="adjust_stock_id">

                <!-- Item Info -->
                <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600">Item</p>
                    <p id="adjust_item_name" class="text-lg font-bold text-gray-900"></p>
                    <p class="text-sm text-gray-600 mt-2">Current Quantity: <span id="adjust_current_qty" class="font-semibold"></span></p>
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
                    <button type="submit" class="flex-1 px-6 py-3 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Apply Adjustment
                    </button>
                    <button type="button" onclick="closeAdjustModal()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
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
            </div>
        </div>
    </div>
</body>
</html>
