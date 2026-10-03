<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - GastoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center">
                        <span class="text-2xl font-bold" style="color: #00C897;">GastoTrack</span>
                    </a>
                    <div class="ml-10 flex space-x-8">
                        <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium">Dashboard</a>
                        <a href="{{ route('transactions.index') }}" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium">Transactions</a>
                        <a href="{{ route('products.index') }}" class="text-gray-900 border-b-2 px-3 py-2 text-sm font-semibold" style="border-color: #00C897; color: #00C897;">Products</a>
                        <a href="{{ route('stock.index') }}" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium">Stock</a>
                        <a href="{{ route('staff.index') }}" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium">Staff</a>
                        <a href="{{ route('analytics.index') }}" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium">Analytics</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">{{ Auth::user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-700 hover:text-gray-900">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Products</h1>
                <p class="text-gray-600 mt-1">Manage your menu items and products</p>
            </div>
            <button onclick="openAddModal()" class="px-6 py-3 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all" style="background-color: #00C897;">
                + Add Product
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Products</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $totalProducts }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">🍽️</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Available</p>
                        <p class="text-2xl font-bold text-green-600">{{ $availableProducts }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-2xl text-green-600">✓</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Categories</p>
                        <p class="text-2xl font-bold" style="color: #00C897;">{{ $categoriesCount }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(0, 200, 151, 0.1);">
                        <span class="text-2xl">📁</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Product name..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ request('category') == $category ? 'selected' : '' }}>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Availability Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Availability</label>
                    <select name="available" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;">
                        <option value="">All Products</option>
                        <option value="1" {{ request('available') === '1' ? 'selected' : '' }}>Available</option>
                        <option value="0" {{ request('available') === '0' ? 'selected' : '' }}>Unavailable</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end space-x-3">
                    <button type="submit" class="px-6 py-2 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Apply Filters
                    </button>
                    <a href="{{ route('products.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-6">
            @forelse($products as $product)
            <div class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                <!-- Product Header -->
                <div class="p-6 pb-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <span class="text-4xl">{{ $product->emoji ?? '🍽️' }}</span>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">{{ $product->name }}</h3>
                                <span class="text-xs text-gray-500">{{ $product->category }}</span>
                            </div>
                        </div>
                        @if($product->is_available)
                            <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Available</span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Unavailable</span>
                        @endif
                    </div>
                    
                    @if($product->description)
                    <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ $product->description }}</p>
                    @endif
                    
                    <!-- Pricing -->
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-xs text-gray-500">Price</p>
                            <p class="text-xl font-bold" style="color: #00C897;">₱{{ number_format($product->price, 2) }}</p>
                        </div>
                        @if($product->cost > 0)
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Cost</p>
                            <p class="text-sm text-gray-700">₱{{ number_format($product->cost, 2) }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Product Actions -->
                <div class="border-t border-gray-100 px-6 py-3 bg-gray-50 flex justify-between items-center">
                    <button onclick="toggleAvailability({{ $product->id }}, {{ $product->is_available ? 'false' : 'true' }})" class="text-sm font-medium hover:underline" style="color: {{ $product->is_available ? '#E53935' : '#00C897' }};">
                        {{ $product->is_available ? 'Mark Unavailable' : 'Mark Available' }}
                    </button>
                    <div class="flex space-x-3">
                        <button onclick="openEditModal({{ $product->id }}, '{{ addslashes($product->name) }}', '{{ addslashes($product->description ?? '') }}', '{{ $product->category }}', {{ $product->price }}, {{ $product->cost ?? 0 }}, '{{ $product->emoji }}', {{ $product->is_available ? 'true' : 'false' }})" class="text-blue-600 hover:text-blue-800 font-medium text-sm">Edit</button>
                        <button onclick="deleteProduct({{ $product->id }})" class="text-red-600 hover:text-red-800 font-medium text-sm">Delete</button>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full">
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <span class="text-6xl mb-4 block">🍽️</span>
                    <p class="text-lg font-medium text-gray-900 mb-2">No products found</p>
                    <p class="text-sm text-gray-600 mb-6">Get started by adding your first product to the menu</p>
                    <button onclick="openAddModal()" class="px-6 py-3 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        + Add Product
                    </button>
                </div>
            </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($products->hasPages())
        <div class="bg-white rounded-lg shadow-sm px-6 py-4">
            {{ $products->links() }}
        </div>
        @endif
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Add Product</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <form id="productForm" method="POST" action="{{ route('products.store') }}">
                @csrf
                <input type="hidden" id="productId" name="product_id">
                <input type="hidden" id="formMethod" name="_method">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Product Name -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                        <input type="text" id="name" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="e.g., Milk Tea">
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <input type="text" id="category" name="category" required list="categoryList" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="e.g., Beverages">
                        <datalist id="categoryList">
                            @foreach($categories as $category)
                                <option value="{{ $category }}">
                            @endforeach
                        </datalist>
                    </div>

                    <!-- Emoji -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Emoji</label>
                        <input type="text" id="emoji" name="emoji" maxlength="10" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="🍵">
                    </div>

                    <!-- Price -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Price *</label>
                        <input type="number" id="price" name="price" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="0.00">
                    </div>

                    <!-- Cost -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cost</label>
                        <input type="number" id="cost" name="cost" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="0.00">
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-opacity-50" style="focus:ring-color: #00C897;" placeholder="Product description..."></textarea>
                    </div>

                    <!-- Availability -->
                    <div class="md:col-span-2">
                        <label class="flex items-center">
                            <input type="checkbox" id="is_available" name="is_available" value="1" checked class="w-4 h-4 rounded" style="color: #00C897;">
                            <span class="ml-2 text-sm font-medium text-gray-700">Product is available</span>
                        </label>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="flex-1 px-6 py-3 text-white font-semibold rounded-lg hover:opacity-90" style="background-color: #00C897;">
                        Save Product
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('productForm').action = '{{ route("products.store") }}';
            document.getElementById('productId').value = '';
            document.getElementById('formMethod').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('category').value = '';
            document.getElementById('price').value = '';
            document.getElementById('cost').value = '';
            document.getElementById('emoji').value = '🍽️';
            document.getElementById('is_available').checked = true;
            document.getElementById('productModal').classList.remove('hidden');
        }

        function openEditModal(id, name, description, category, price, cost, emoji, isAvailable) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('productForm').action = '/products/' + id;
            document.getElementById('productId').value = id;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('name').value = name;
            document.getElementById('description').value = description;
            document.getElementById('category').value = category;
            document.getElementById('price').value = price;
            document.getElementById('cost').value = cost;
            document.getElementById('emoji').value = emoji;
            document.getElementById('is_available').checked = isAvailable;
            document.getElementById('productModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                fetch('/products/' + id, {
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
                        alert('Error deleting product');
                    }
                })
                .catch(error => {
                    alert('Error deleting product');
                });
            }
        }

        function toggleAvailability(id, newStatus) {
            fetch('/products/' + id + '/toggle', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating product availability');
                }
            })
            .catch(error => {
                alert('Error updating product availability');
            });
        }

        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
