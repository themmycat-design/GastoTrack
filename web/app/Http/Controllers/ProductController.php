<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'available' => ['nullable', Rule::in(['0', '1'])],
        ]);
        $businessId = (int) $request->user()->business_id;

        $query = Product::query()->where('business_id', $businessId);
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        if (isset($validated['available'])) {
            if ($validated['available'] === '1') {
                $query->where('active', true)->where('is_available', true);
            } else {
                $query->where(function ($builder): void {
                    $builder->where('active', false)->orWhere('is_available', false);
                });
            }
        }

        $products = $query->orderBy('display_order')->orderBy('name')->paginate(12)->withQueryString();
        $categories = Product::query()
            ->where('business_id', $businessId)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        $totalProducts = Product::query()->where('business_id', $businessId)->count();
        $availableProducts = Product::query()
            ->where('business_id', $businessId)
            ->where('active', true)
            ->where('is_available', true)
            ->count();
        $categoriesCount = Product::query()
            ->where('business_id', $businessId)
            ->whereNotNull('category')
            ->distinct('category')
            ->count('category');

        return view('products.index', compact(
            'products', 'categories', 'totalProducts', 'availableProducts', 'categoriesCount'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->productRules());
        $businessId = (int) $request->user()->business_id;
        $maxOrder = Product::query()->where('business_id', $businessId)->max('display_order');
        $attributes = $this->productAttributes($validated);
        $attributes['business_id'] = $businessId;
        $attributes['active'] = true;
        $attributes['display_order'] = ((int) $maxOrder) + 1;

        Product::create($attributes);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $this->ensureBusinessOwns($request, $product);
        $validated = $request->validate($this->productRules());
        $attributes = $this->productAttributes($validated);
        $attributes['is_available'] = $validated['is_available'] ?? $product->is_available;
        $product->update($attributes);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->ensureBusinessOwns($request, $product);

        // Products are soft-deleted, preserving the foreign-key target and the
        // historical product-name/price snapshots on existing order lines.
        $product->delete();

        return response()->json(['success' => true]);
    }

    public function toggleAvailability(Request $request, Product $product)
    {
        $this->ensureBusinessOwns($request, $product);
        $product->update(['is_available' => !$product->is_available]);

        return response()->json([
            'success' => true,
            'is_available' => $product->fresh()->is_available,
        ]);
    }

    private function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    private function productAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? 0,
            'emoji' => $validated['emoji'] ?? '☕',
            'is_available' => $validated['is_available'] ?? true,
        ];
    }

    private function ensureBusinessOwns(Request $request, Product $product): void
    {
        abort_unless((int) $product->business_id === (int) $request->user()->business_id, 404);
    }
}
