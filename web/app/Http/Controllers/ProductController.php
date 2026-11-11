<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductEditRequest;
use App\Models\StockItem;
use App\Services\BusinessPlanLimits;
use App\Services\RecipeIngredientNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request, BusinessPlanLimits $planLimits)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', Rule::in(['Small', 'Medium', 'Large'])],
            'available' => ['nullable', Rule::in(['0', '1'])],
        ]);
        $businessId = (int) $request->user()->business_id;

        $query = Product::query()
            ->where('business_id', $businessId)
            ->with('productIngredients.stockItem');
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
        if (!empty($validated['size'])) {
            $query->where('size', $validated['size']);
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
        $sizes = Product::query()
            ->where('business_id', $businessId)
            ->when(!empty($validated['category']), fn ($query) => $query->where('category', $validated['category']))
            ->whereNotNull('size')
            ->distinct()
            ->pluck('size');
        $totalProducts = Product::query()->where('business_id', $businessId)->count();
        $productCapacity = $planLimits->forTier($request->user()->business?->plan_tier)['products'];
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
        $stockItems = StockItem::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);
        $pendingEditRequests = ProductEditRequest::query()
            ->where('business_id', $businessId)
            ->where('status', 'pending')
            ->with(['product', 'requester'])
            ->latest()
            ->get();

        return view('products.index', compact(
            'products', 'categories', 'sizes', 'totalProducts', 'productCapacity', 'availableProducts', 'categoriesCount', 'stockItems', 'pendingEditRequests'
        ));
    }

    public function store(Request $request, RecipeIngredientNormalizer $normalizer, BusinessPlanLimits $planLimits)
    {
        $businessId = (int) $request->user()->business_id;
        $validated = $request->validate($this->productRules($businessId));
        $validated['ingredients'] = $normalizer->normalize($validated['ingredients'] ?? [], $businessId);
        $attributes = $this->productAttributes($validated);
        $attributes['business_id'] = $businessId;
        $attributes['active'] = true;
        $imagePath = $request->file('image')?->store('products/'.$businessId, 'public');
        abort_unless(! $request->hasFile('image') || $imagePath, 503, 'The product photo could not be saved. Please try again.');
        $attributes['image'] = $imagePath;

        try {
            DB::transaction(function () use ($attributes, $validated, $businessId, $planLimits): void {
                $planLimits->assertCanAddProduct($businessId);
                $maxOrder = Product::query()->where('business_id', $businessId)->max('display_order');
                $attributes['display_order'] = ((int) $maxOrder) + 1;
                $product = Product::create($attributes);
                $this->syncIngredients($product, $validated['ingredients'] ?? []);
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $exception;
        }

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product, RecipeIngredientNormalizer $normalizer)
    {
        $this->ensureBusinessOwns($request, $product);
        $validated = $request->validate($this->productRules((int) $request->user()->business_id));
        $validated['ingredients'] = $normalizer->normalize($validated['ingredients'] ?? [], (int) $request->user()->business_id);
        $attributes = $this->productAttributes($validated);
        $attributes['is_available'] = $validated['is_available'] ?? $product->is_available;
        $imagePath = $request->file('image')?->store('products/'.$request->user()->business_id, 'public');
        abort_unless(! $request->hasFile('image') || $imagePath, 503, 'The product photo could not be saved. Please try again.');
        $oldImage = $product->image;

        try {
            DB::transaction(function () use ($product, $attributes, $validated, $imagePath): void {
                if ($imagePath) {
                    $attributes['image'] = $imagePath;
                }
                $product->update($attributes);
                if (($validated['replace_ingredients'] ?? false) === true || ($validated['replace_ingredients'] ?? null) === '1') {
                    $this->syncIngredients($product, $validated['ingredients'] ?? []);
                }
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $exception;
        }

        if ($imagePath && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

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

    private function productRules(int $businessId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'max:100'],
            'size' => ['nullable', Rule::in(['Small', 'Medium', 'Large'])],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'is_available' => ['sometimes', 'boolean'],
            'replace_ingredients' => ['sometimes', 'boolean'],
            'ingredients' => ['sometimes', 'array'],
            'ingredients.*.stock_id' => [
                'required', 'integer',
                Rule::exists('stock_items', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'ingredients.*.unit' => ['sometimes', 'string', 'max:20'],
        ];
    }

    private function productAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'size' => $validated['size'] ?? null,
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? 0,
            'emoji' => $validated['emoji'] ?? '☕',
            'is_available' => $validated['is_available'] ?? true,
        ];
    }

    private function syncIngredients(Product $product, array $ingredients): void
    {
        $product->productIngredients()->delete();
        foreach ($ingredients as $ingredient) {
            $product->productIngredients()->create([
                'stock_item_id' => $ingredient['stock_id'],
                'quantity' => $ingredient['quantity'],
            ]);
        }
    }

    private function ensureBusinessOwns(Request $request, Product $product): void
    {
        abort_unless((int) $product->business_id === (int) $request->user()->business_id, 404);
    }
}
