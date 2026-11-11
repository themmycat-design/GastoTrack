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
                <p class="mt-2 text-sm leading-6 text-slate-500">Manage your menu and per-product recipes. Products do not hold finished stock; completed sales deduct their ingredients.</p>
            </div>
            <button type="button" onclick="openAddModal()" class="gt-focus gt-btn-primary">
                <span aria-hidden="true">+</span> Add product
            </button>
        </section>

        <p class="gt-enter text-sm text-slate-600">Products: <strong>{{ $totalProducts }} / {{ $productCapacity ?? 'Unlimited' }}</strong> on your {{ ucfirst(auth()->user()->business?->plan_tier ?? 'free') }} plan.
            @if($productCapacity !== null && $totalProducts >= $productCapacity)<a class="font-bold text-[#08745D] underline" href="{{ route('owner.subscriptions.index') }}">View plans</a>@endif
        </p>

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

        <section id="product-edit-requests" class="gt-card scroll-mt-20 p-5 sm:p-6" aria-label="Staff product edit requests">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-extrabold text-[#0A2E2A]">Staff product edit requests</h2>
                <span class="rounded-full bg-[#EAF8F2] px-3 py-1 text-xs font-bold text-[#08745D]">{{ $pendingEditRequests->count() }} pending</span>
            </div>
            @forelse($pendingEditRequests as $editRequest)
                @php
                    $changes = $editRequest->changes;
                    $currentProduct = $editRequest->product;
                    $stale = !$currentProduct || (int) $currentProduct->revision !== $editRequest->expected_revision;
                @endphp
                <article class="mt-4 rounded-xl border border-[#DDE9E5] bg-[#F8FCFA] p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-[#0A2E2A]">{{ $currentProduct?->name ?? 'Removed product' }}</h3>
                            <p class="mt-1 text-xs text-slate-500">Requested by {{ $editRequest->requester?->name ?? 'Staff member' }} · {{ $editRequest->created_at?->format('M j, Y g:i A') }}</p>
                        </div>
                        @if($stale)<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">Product changed since request</span>@endif
                    </div>
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        @foreach(['name' => 'Name', 'category' => 'Category', 'size' => 'Size', 'price' => 'Price', 'description' => 'Description', 'emoji' => 'Icon', 'is_available' => 'Availability', 'display_order' => 'Menu order'] as $field => $label)
                            @if(array_key_exists($field, $changes))
                                <div class="rounded-lg bg-white px-3 py-2">
                                    <dt class="text-xs font-bold text-slate-500">{{ $label }}</dt>
                                    <dd class="mt-1 break-words text-xs text-slate-500">Current:
                                        @if($field === 'is_available'){{ $currentProduct?->is_available ? 'Available' : 'Unavailable' }}
                                        @elseif($field === 'price'){{ number_format((float) $currentProduct?->price, 2) }}
                                        @else{{ data_get($currentProduct, $field) ?: '—' }}@endif
                                    </dd>
                                    <dd class="mt-1 break-words text-[#173F39]">
                                        Proposed:
                                        @if($field === 'is_available'){{ $changes[$field] ? 'Available' : 'Unavailable' }}
                                        @elseif($field === 'price'){{ number_format((float) $changes[$field], 2) }}
                                        @else{{ $changes[$field] ?: '—' }}@endif
                                    </dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                    @if(array_key_exists('ingredients', $changes) || !empty($changes['replace_ingredients']))
                        <div class="mt-3 text-sm text-[#173F39]">
                            <p class="text-xs font-bold text-slate-500">Proposed recipe per product</p>
                            @forelse($changes['ingredients'] ?? [] as $ingredient)
                                @php $stockItem = $stockItems->firstWhere('id', $ingredient['stock_id']); @endphp
                                <p class="mt-1">{{ $stockItem?->name ?? 'Removed stock item' }}: {{ $ingredient['quantity'] }} {{ $stockItem?->unit ?? '' }}</p>
                            @empty
                                <p class="mt-1">No ingredients</p>
                            @endforelse
                        </div>
                    @endif
                    @if($editRequest->image_path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($editRequest->image_path) }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-bold text-[#08745D] underline">View proposed photo</a>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        @unless($stale)
                            <form method="POST" action="{{ route('products.edit-requests.approve', $editRequest) }}">@csrf<button type="submit" class="gt-focus gt-btn-primary">Approve and apply</button></form>
                        @endunless
                        <form method="POST" action="{{ route('products.edit-requests.reject', $editRequest) }}">@csrf<button type="submit" class="gt-focus gt-btn-secondary">Reject request</button></form>
                    </div>
                </article>
            @empty
                <p class="mt-3 text-sm text-slate-500">No product edits are waiting for review.</p>
            @endforelse
        </section>

        <!-- Filters -->
        <section class="gt-card p-5 sm:p-6" aria-label="Filter products">
            <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(180px,1.4fr)_repeat(3,minmax(135px,1fr))_auto] xl:items-end">
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

                <div>
                    <label for="product-size" class="gt-label">Size</label>
                    <select id="product-size" name="size" class="gt-field">
                        <option value="">All Sizes</option>
                        @foreach(['Small', 'Medium', 'Large'] as $size)
                            @if($sizes->contains($size))
                                <option value="{{ $size }}" {{ request('size') === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endif
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
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#EAF8F2] text-2xl" aria-hidden="true">
                                @if($product->image)
                                    <img src="{{ url('/api/product-images/'.$product->id) }}?v={{ $product->updated_at?->timestamp ?? $product->id }}" alt="{{ $product->name }} product photo" class="h-full w-full object-cover">
                                @else
                                    {{ $product->emoji ?? '🍽️' }}
                                @endif
                            </span>
                            <div>
                                <h3 class="break-words text-base font-extrabold text-[#0A2E2A]">{{ $product->name }}</h3>
                                <span class="mt-0.5 block text-xs font-semibold text-slate-500">{{ $product->category }}{{ $product->size ? ' · '.$product->size : '' }}</span>
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

                    <div class="mb-4 rounded-xl bg-[#F7FAF9] px-3.5 py-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recipe ingredients</p>
                        @forelse($product->productIngredients as $ingredient)
                            @php
                                $recipeUnit = $ingredient->stockItem?->unit ?? '';
                                $recipeQuantity = (float) $ingredient->quantity;
                                $alternateUnit = match (strtolower(trim($recipeUnit))) {
                                    'kg', 'kilogram', 'kilograms' => ['unit' => 'g', 'factor' => 0.001],
                                    'l', 'liter', 'liters', 'litre', 'litres' => ['unit' => 'ml', 'factor' => 0.001],
                                    'ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres' => ['unit' => 'L', 'factor' => 1000],
                                    default => null,
                                };
                                if ($alternateUnit && (($alternateUnit['factor'] < 1 && $recipeQuantity > 0 && $recipeQuantity < 1) || ($alternateUnit['factor'] > 1 && $recipeQuantity >= 1000))) {
                                    $displayQuantity = round($recipeQuantity / $alternateUnit['factor'], 4);
                                    if (abs($displayQuantity * $alternateUnit['factor'] - $recipeQuantity) < 0.0000001) {
                                        $recipeQuantity = $displayQuantity;
                                        $recipeUnit = $alternateUnit['unit'];
                                    }
                                }
                            @endphp
                            <p class="mt-1 text-sm text-[#173F39]">{{ $ingredient->stockItem?->name ?? 'Stock item' }} <span class="text-slate-500">· {{ rtrim(rtrim(number_format($recipeQuantity, 4, '.', ''), '0'), '.') }} {{ $recipeUnit }} per product</span></p>
                        @empty
                            <p class="mt-1 text-sm text-slate-500">No recipe ingredients configured.</p>
                        @endforelse
                    </div>
                    
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
                        <button type="button" onclick="openEditModal({{ \Illuminate\Support\Js::from(['id' => $product->id, 'name' => $product->name, 'description' => $product->description, 'category' => $product->category, 'size' => $product->size, 'price' => $product->price, 'cost' => $product->cost, 'emoji' => $product->emoji, 'is_available' => $product->is_available, 'image_url' => $product->image ? url('/api/product-images/'.$product->id.'?v='.$product->updated_at?->timestamp) : null, 'ingredients' => $product->productIngredients->map(fn ($ingredient) => ['stock_id' => $ingredient->stock_item_id, 'quantity' => $ingredient->quantity])->values()->all()]) }})" class="gt-focus rounded-lg px-2 py-1.5 text-sm font-bold text-[#08745D] hover:bg-[#EAF8F2]">Edit</button>
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
    <div id="productModal" data-owner-dialog class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-[#0A2E2A]/45 p-4 backdrop-blur-sm">
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

            <form id="productForm" method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
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

                    <div class="sm:col-span-2">
                        <label for="productImage" class="gt-label">Product photo</label>
                        <div class="flex flex-col gap-3 rounded-xl border border-[#DDE9E5] bg-[#F8FCFA] p-3 sm:flex-row sm:items-center">
                            <div id="productImagePreviewWrap" class="hidden h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-white">
                                <img id="productImagePreview" alt="Product photo preview" class="h-full w-full object-cover">
                            </div>
                            <div id="productImagePlaceholder" class="flex h-24 w-24 shrink-0 items-center justify-center rounded-xl bg-white text-center text-xs font-semibold text-slate-400">No photo</div>
                            <div class="min-w-0 flex-1">
                                <input type="file" id="productImage" name="image" accept="image/jpeg,image/png,image/webp" class="gt-field !py-2 text-sm">
                                <p class="mt-1.5 text-xs text-slate-500">JPG, PNG, or WebP · Maximum 5 MB. Existing photo is kept unless you choose a new one.</p>
                            </div>
                        </div>
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

                    <div>
                        <label for="size" class="gt-label">Size</label>
                        <select id="size" name="size" class="gt-field">
                            <option value="">No size</option>
                            <option value="Small">Small</option>
                            <option value="Medium">Medium</option>
                            <option value="Large">Large</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Create one product per size so each can have its own price and recipe.</p>
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

                    <div class="sm:col-span-2">
                        <div class="mb-3">
                            <label class="gt-label mb-1">Recipe ingredients</label>
                            <p class="text-xs leading-5 text-slate-500">Set the amount of each ingredient used by one product. For liquid stock, choose ml or L. A completed sale deducts that amount multiplied by the number sold. Creating a product does not add stock; record deliveries in Inventory.</p>
                        </div>
                        <input type="hidden" name="replace_ingredients" value="1">
                        @if($stockItems->isEmpty())
                            <p class="rounded-xl bg-[#F7FAF9] px-4 py-3 text-sm text-slate-500">No stock items yet. <a href="{{ route('stock.index') }}" class="font-bold text-[#08745D] underline">Add ingredients in Inventory</a>, then return to set this recipe.</p>
                        @else
                            <div class="max-h-64 space-y-2 overflow-y-auto rounded-xl border border-[#DDE9E5] p-3">
                                @foreach($stockItems as $stockItem)
                                    <div class="recipe-row flex flex-wrap items-center gap-3 rounded-lg bg-[#F8FCFA] px-3 py-2.5" data-stock-id="{{ $stockItem->id }}" data-stock-unit="{{ $stockItem->unit }}">
                                        <input type="checkbox" class="recipe-checkbox h-4 w-4 accent-[#08745D]" aria-label="Use {{ $stockItem->name }} in recipe">
                                        <span class="min-w-0 flex-1 text-sm font-semibold text-[#173F39]">{{ $stockItem->name }} <span class="font-normal text-slate-500">({{ $stockItem->unit }})</span> @unless($stockItem->active)<span class="text-xs font-semibold text-amber-700">Inactive</span>@endunless</span>
                                        <input type="hidden" class="recipe-stock-id" name="ingredients[{{ $stockItem->id }}][stock_id]" value="{{ $stockItem->id }}" disabled>
                                        <label class="flex items-center gap-2 text-xs text-slate-500">Per product
                                            <input type="number" class="recipe-quantity gt-field w-24 !px-2 !py-1.5" name="ingredients[{{ $stockItem->id }}][quantity]" min="0.0001" step="any" placeholder="0" disabled>
                                        </label>
                                        <select class="recipe-unit gt-field w-24 !px-2 !py-1.5 text-sm" name="ingredients[{{ $stockItem->id }}][unit]" aria-label="Unit for {{ $stockItem->name }}" disabled>
                                            <option value="{{ $stockItem->unit }}" data-to-stock-factor="1">{{ $stockItem->unit }}</option>
                                            @if(in_array(strtolower(trim($stockItem->unit)), ['kg', 'kilogram', 'kilograms'], true))<option value="g" data-to-stock-factor="0.001">g</option>@endif
                                            @if(in_array(strtolower(trim($stockItem->unit)), ['l', 'liter', 'liters', 'litre', 'litres'], true))<option value="ml" data-to-stock-factor="0.001">ml</option>@endif
                                            @if(in_array(strtolower(trim($stockItem->unit)), ['ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres'], true))<option value="L" data-to-stock-factor="1000">L</option>@endif
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        @endif
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
        const productImageInput = document.getElementById('productImage');
        const productImagePreview = document.getElementById('productImagePreview');
        const productImagePreviewWrap = document.getElementById('productImagePreviewWrap');
        const productImagePlaceholder = document.getElementById('productImagePlaceholder');
        let temporaryProductImageUrl = null;

        function setProductImagePreview(src) {
            if (temporaryProductImageUrl) {
                URL.revokeObjectURL(temporaryProductImageUrl);
                temporaryProductImageUrl = null;
            }
            if (src) {
                if (src.startsWith('blob:')) temporaryProductImageUrl = src;
                productImagePreview.src = src;
                productImagePreviewWrap.classList.remove('hidden');
                productImagePlaceholder.classList.add('hidden');
            } else {
                productImagePreview.removeAttribute('src');
                productImagePreviewWrap.classList.add('hidden');
                productImagePlaceholder.classList.remove('hidden');
            }
        }

        productImageInput.addEventListener('change', () => {
            const file = productImageInput.files?.[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                alert('Choose a JPG, PNG, or WebP photo no larger than 5 MB.');
                productImageInput.value = '';
                setProductImagePreview(productImageInput.dataset.savedImageUrl || null);
                return;
            }
            setProductImagePreview(URL.createObjectURL(file));
        });

        function setRecipeIngredients(ingredients = []) {
            const selected = new Map(ingredients.map(item => [String(item.stock_id), String(item.quantity)]));
            document.querySelectorAll('.recipe-row').forEach(row => {
                const isSelected = selected.has(row.dataset.stockId);
                const checkbox = row.querySelector('.recipe-checkbox');
                const stockId = row.querySelector('.recipe-stock-id');
                const quantity = row.querySelector('.recipe-quantity');
                const unit = row.querySelector('.recipe-unit');
                checkbox.checked = isSelected;
                stockId.disabled = !isSelected;
                quantity.disabled = !isSelected;
                unit.disabled = !isSelected;
                const stockQuantity = Number(selected.get(row.dataset.stockId));
                const alternate = unit.options.length > 1 ? unit.options[1] : null;
                const factor = Number(alternate?.dataset.toStockFactor);
                if (isSelected && alternate && ((factor < 1 && stockQuantity > 0 && stockQuantity < 1) || (factor > 1 && stockQuantity >= 1000))) {
                    const displayed = Number((stockQuantity / factor).toFixed(4));
                    if (Math.abs(displayed * factor - stockQuantity) < 0.0000001) {
                        unit.value = alternate.value;
                        quantity.value = String(displayed);
                    } else {
                        unit.value = row.dataset.stockUnit;
                        quantity.value = selected.get(row.dataset.stockId);
                    }
                } else {
                    unit.value = row.dataset.stockUnit;
                    quantity.value = isSelected ? selected.get(row.dataset.stockId) : '';
                }
            });
        }

        document.querySelectorAll('.recipe-row').forEach(row => {
            const checkbox = row.querySelector('.recipe-checkbox');
            const stockId = row.querySelector('.recipe-stock-id');
            const quantity = row.querySelector('.recipe-quantity');
            const unit = row.querySelector('.recipe-unit');
            quantity.addEventListener('input', () => quantity.setCustomValidity(''));
            unit.addEventListener('change', () => { quantity.value = ''; quantity.setCustomValidity(''); quantity.focus(); });
            checkbox.addEventListener('change', () => {
                stockId.disabled = !checkbox.checked;
                quantity.disabled = !checkbox.checked;
                unit.disabled = !checkbox.checked;
                if (checkbox.checked && !quantity.value) quantity.focus();
                if (!checkbox.checked) quantity.value = '';
            });
        });

        document.getElementById('productForm').addEventListener('submit', event => {
            for (const row of document.querySelectorAll('.recipe-row')) {
                if (!row.querySelector('.recipe-checkbox').checked) continue;
                const quantity = row.querySelector('.recipe-quantity');
                const unit = row.querySelector('.recipe-unit');
                const entered = Number(quantity.value);
                const normalized = entered * Number(unit.selectedOptions[0].dataset.toStockFactor || 1);
                const rounded = Number(normalized.toFixed(4));
                if (!Number.isFinite(normalized) || normalized <= 0 || normalized > 99999999.9999 ||
                    Math.abs(normalized - rounded) >= 0.0000001 || rounded < 0.0001) {
                    event.preventDefault();
                    quantity.setCustomValidity('Enter an amount that is at least 0.0001 of the stock unit and uses at most four decimal places.');
                    quantity.reportValidity();
                    return;
                }
                quantity.setCustomValidity('');
            }
        });

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('productForm').action = '{{ route("products.store") }}';
            document.getElementById('productId').value = '';
            document.getElementById('formMethod').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('category').value = '';
            document.getElementById('size').value = '';
            document.getElementById('price').value = '';
            document.getElementById('cost').value = '';
            document.getElementById('emoji').value = '🍽️';
            document.getElementById('is_available').checked = true;
            productImageInput.value = '';
            productImageInput.dataset.savedImageUrl = '';
            setProductImagePreview(null);
            setRecipeIngredients();
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
            document.getElementById('size').value = product.size || '';
            document.getElementById('price').value = product.price;
            document.getElementById('cost').value = product.cost || 0;
            document.getElementById('emoji').value = product.emoji || '☕';
            document.getElementById('is_available').checked = Boolean(product.is_available);
            productImageInput.value = '';
            productImageInput.dataset.savedImageUrl = product.image_url || '';
            setProductImagePreview(product.image_url);
            setRecipeIngredients(product.ingredients || []);
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

        @if(request()->boolean('add'))
            openAddModal();
        @endif
    </script>
    </main>
</div>
</body>
</html>
