<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Products | GastoTrack</title>
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
        <section class="gt-enter flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold text-[#00A87E]">Menu management</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Products</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Manage your menu, prices, categories, and availability.</p>
            </div>
            <button type="button" onclick="openAddModal()" class="gt-focus gt-btn-primary">
                <span aria-hidden="true">+</span> Add product
            </button>
        </section>

        @if(session('success'))
            <div role="status" class="rounded-xl border border-[#C8DED7] bg-[#EAF8F2] px-4 py-3 text-sm font-semibold text-[#08745D]">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <p class="font-bold">Please correct the product details:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article class="gt-card gt-lift p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Total products</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#0A2E2A]">{{ $totalProducts }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-xl text-[#08745D]" aria-hidden="true">
                        🍽️
                    </div>
                </div>
            </article>

            <article class="gt-card gt-lift p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Available</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#08745D]">{{ $availableProducts }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-lg font-extrabold text-[#08745D]" aria-hidden="true">
                        ✓
                    </div>
                </div>
            </article>

            <article class="gt-card gt-lift p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Categories</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#08745D]">{{ $categoriesCount }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-xl text-[#08745D]" aria-hidden="true">
                        📁
                    </div>
                </div>
            </article>
        </div>

        <!-- Filters -->
        <section class="gt-card p-5 sm:p-6" aria-label="Filter products">
            <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(200px,1.4fr)_minmax(160px,1fr)_minmax(160px,1fr)_auto] xl:items-end">
                <!-- Search -->
                <div>
                    <label for="product-search" class="gt-label">Search</label>
                    <input id="product-search" type="search" name="search" value="{{ request('search') }}" placeholder="Search products..." class="gt-field">
                </div>

                <!-- Category Filter -->
                <div>
                    <label for="product-category" class="gt-label">Category</label>
                    <select id="product-category" name="category" class="gt-field">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ request('category') == $category ? 'selected' : '' }}>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Availability Filter -->
                <div>
                    <label for="product-availability" class="gt-label">Availability</label>
                    <select id="product-availability" name="available" class="gt-field">
                        <option value="">All Products</option>
                        <option value="1" {{ request('available') === '1' ? 'selected' : '' }}>Available</option>
                        <option value="0" {{ request('available') === '0' ? 'selected' : '' }}>Unavailable</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="gt-focus gt-btn-primary">
                        Apply filters
                    </button>
                    <a href="{{ route('products.index') }}" class="gt-focus gt-btn-secondary">
                        Clear
                    </a>
                </div>
            </form>
        </section>

        <!-- Products Grid -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" aria-label="Products">
            @forelse($products as $product)
            <article class="gt-card gt-lift flex min-w-0 flex-col overflow-hidden">
                <!-- Product Header -->
                <div class="flex-1 p-5 sm:p-6">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#EAF8F2] text-2xl" aria-hidden="true">{{ $product->emoji ?? '🍽️' }}</span>
                            <div>
                                <h3 class="break-words text-base font-extrabold text-[#0A2E2A]">{{ $product->name }}</h3>
                                <span class="mt-0.5 block text-xs font-semibold text-slate-500">{{ $product->category }}</span>
                            </div>
                        </div>
                        @if($product->active && $product->is_available)
                            <span class="shrink-0 rounded-full bg-[#EAF8F2] px-2.5 py-1 text-[11px] font-bold text-[#08745D]">Available</span>
                        @elseif(!$product->active)
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">Inactive</span>
                        @else
                            <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700">Unavailable</span>
                        @endif
                    </div>
                    
                    @if($product->description)
                    <p class="mb-4 line-clamp-2 text-sm leading-5 text-slate-600">{{ $product->description }}</p>
                    @endif
                    
                    <!-- Pricing -->
                    <div class="flex items-end justify-between gap-3 border-t border-[#EAF2EF] pt-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Price</p>
                            <p class="mt-1 text-xl font-extrabold text-[#08745D]">₱{{ number_format($product->price, 2) }}</p>
                        </div>
                        @if($product->cost > 0)
                        <div class="text-right">
                            <p class="text-xs font-semibold text-slate-500">Cost</p>
                            <p class="mt-1 text-sm font-bold text-[#0A2E2A]">₱{{ number_format($product->cost, 2) }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Product Actions -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#EAF2EF] bg-[#FBFDFC] px-5 py-3.5 sm:px-6">
                    @if($product->active)
                        <button type="button" onclick="toggleAvailability({{ $product->id }})" class="gt-focus rounded-lg px-2 py-1.5 text-sm font-bold transition {{ $product->is_available ? 'text-rose-600 hover:bg-rose-50' : 'text-[#08745D] hover:bg-[#EAF8F2]' }}">
                            {{ $product->is_available ? 'Mark Unavailable' : 'Mark Available' }}
                        </button>
                    @else
                        <span class="text-sm font-medium text-slate-500">Inactive product</span>
                    @endif
                    <div class="flex space-x-3">
                        <button type="button" onclick="openEditModal({{ \Illuminate\Support\Js::from(['id' => $product->id, 'name' => $product->name, 'description' => $product->description, 'category' => $product->category, 'price' => $product->price, 'cost' => $product->cost, 'emoji' => $product->emoji, 'is_available' => $product->is_available]) }})" class="gt-focus rounded-lg px-2 py-1.5 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Edit</button>
                        <button type="button" onclick="deleteProduct({{ $product->id }})" class="gt-focus rounded-lg px-2 py-1.5 text-sm font-bold text-rose-600 hover:bg-rose-50">Delete</button>
                    </div>
                </div>
            </article>
            @empty
            <div class="col-span-full">
                <div class="gt-card col-span-full px-6 py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#EAF8F2] text-3xl" aria-hidden="true">🍽️</span>
                    <p class="mt-4 text-base font-extrabold text-[#0A2E2A]">No products found</p>
                    <p class="mx-auto mt-2 mb-6 max-w-sm text-sm leading-6 text-slate-500">Try changing the filters, or add your first product to the menu.</p>
                    <button type="button" onclick="openAddModal()" class="gt-focus gt-btn-primary">
                        + Add product
                    </button>
                </div>
            </div>
            @endforelse
        </section>

        <!-- Pagination -->
        @if($products->hasPages())
        <div class="gt-card px-5 py-4 sm:px-6">
            {{ $products->links() }}
        </div>
        @endif
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-[#0A2E2A]/45 p-4 backdrop-blur-sm">
        <div class="gt-card my-auto max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto p-5 shadow-2xl sm:p-7">
            <div class="mb-5 flex items-start justify-between gap-4 border-b border-[#EAF2EF] pb-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Menu management</p>
                    <h2 id="modalTitle" class="mt-1 text-xl font-extrabold text-[#0A2E2A] sm:text-2xl">Add product</h2>
                </div>
                <button type="button" onclick="closeModal()" aria-label="Close product form" class="gt-focus flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#F4F8F7] text-xl text-slate-500 transition hover:bg-[#EAF8F2] hover:text-[#08745D]">
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <form id="productForm" method="POST" action="{{ route('products.store') }}">
                @csrf
                <input type="hidden" id="productId" name="product_id">
                <input type="hidden" id="formMethod" name="_method">
                <input type="hidden" name="is_available" value="0">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5">
                    <!-- Product Name -->
                    <div class="sm:col-span-2">
                        <label for="name" class="gt-label">Product name <span class="text-rose-600">*</span></label>
                        <input type="text" id="name" name="name" required class="gt-field" placeholder="e.g., Milk Tea">
                    </div>

                    <!-- Category -->
                    <div>
                        <label for="category" class="gt-label">Category <span class="text-rose-600">*</span></label>
                        <input type="text" id="category" name="category" required list="categoryList" class="gt-field" placeholder="e.g., Beverages">
                        <datalist id="categoryList">
                            @foreach($categories as $category)
                                <option value="{{ $category }}">
                            @endforeach
                        </datalist>
                    </div>

                    <!-- Emoji -->
                    <div>
                        <label for="emoji" class="gt-label">Icon</label>
                        <input type="text" id="emoji" name="emoji" maxlength="10" class="gt-field" placeholder="🍵">
                    </div>

                    <!-- Price -->
                    <div>
                        <label for="price" class="gt-label">Price <span class="text-rose-600">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" required class="gt-field" placeholder="0.00">
                    </div>

                    <!-- Cost -->
                    <div>
                        <label for="cost" class="gt-label">Cost</label>
                        <input type="number" id="cost" name="cost" step="0.01" class="gt-field" placeholder="0.00">
                    </div>

                    <!-- Description -->
                    <div class="sm:col-span-2">
                        <label for="description" class="gt-label">Description</label>
                        <textarea id="description" name="description" rows="3" class="gt-field" placeholder="Product description..."></textarea>
                    </div>

                    <!-- Availability -->
                    <div class="sm:col-span-2">
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#DDE9E5] bg-[#F8FCFA] px-4 py-3 text-sm font-semibold text-[#173F39]">
                            <input type="checkbox" id="is_available" name="is_available" value="1" checked class="gt-field">
                            Product is available
                        </label>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="mt-6 flex flex-col-reverse gap-3 border-t border-[#EAF2EF] pt-5 sm:flex-row">
                    <button type="button" onclick="closeModal()" class="gt-focus gt-btn-secondary flex-1">Cancel</button>
                    <button type="submit" class="gt-focus gt-btn-primary flex-1">
                        Save product
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

        const productUpdateUrl = @json(route('products.update', ['product' => '__PRODUCT__']));
        const productDestroyUrl = @json(route('products.destroy', ['product' => '__PRODUCT__']));
        const productToggleUrl = @json(route('products.toggle', ['product' => '__PRODUCT__']));
        const productUrl = (template, id) => template.replace('__PRODUCT__', encodeURIComponent(id));

        function openEditModal(product) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('productForm').action = productUrl(productUpdateUrl, product.id);
            document.getElementById('productId').value = product.id;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('name').value = product.name;
            document.getElementById('description').value = product.description || '';
            document.getElementById('category').value = product.category;
            document.getElementById('price').value = product.price;
            document.getElementById('cost').value = product.cost || 0;
            document.getElementById('emoji').value = product.emoji || '☕';
            document.getElementById('is_available').checked = Boolean(product.is_available);
            document.getElementById('productModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        function deleteProduct(id) {
            if (confirm('Remove this product from the active catalog? Existing order history will be preserved.')) {
                fetch(productUrl(productDestroyUrl, id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                })
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Could not delete product.');
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting product');
                    }
                })
                .catch(error => {
                    alert(error.message || 'Could not delete product. Check your connection and try again.');
                });
            }
        }

        function toggleAvailability(id) {
            fetch(productUrl(productToggleUrl, id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            })
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Could not update availability.');
                    return data;
                })
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating product availability');
                }
            })
                .catch(error => {
                    alert(error.message || 'Could not update availability. Check your connection and try again.');
            });
        }

        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
    </main>
</div>
</body>
</html>
