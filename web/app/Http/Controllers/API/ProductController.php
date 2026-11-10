<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEditRequest;
use App\Models\ProductIngredient;
use App\Models\Notification;
use App\Models\User;
use App\Services\RecipeIngredientNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        $canManageProducts = $request->user()->isOwner() || $request->user()->isStaff();
        $products = Product::where('business_id', $request->user()->business_id)
            ->with('productIngredients.stockItem')
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->category))
            ->when(! $canManageProducts, fn ($query) => $query->where('is_available', true))
            ->orderBy('display_order')
            ->get();

        if ($request->user()->isStaff()) {
            $pendingProductIds = ProductEditRequest::query()
                ->where('business_id', $request->user()->business_id)
                ->where('status', 'pending')
                ->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            foreach ($products as $product) {
                $product->approval_pending = in_array($product->id, $pendingProductIds, true);
            }
        }

        return response()->json(['products' => $products]);
    }

    // POST /api/products
    public function store(Request $request, RecipeIngredientNormalizer $normalizer)
    {
        $this->ensureCanManageProducts($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'emoji' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'is_available' => 'nullable|boolean',
            'ingredients' => 'nullable|array',
            'ingredients.*.stock_id' => ['required', Rule::exists('stock_items', 'id')->where('business_id', $request->user()->business_id)],
            'ingredients.*.quantity' => 'required|numeric|decimal:0,4|min:0.0001|max:99999999.9999',
            'ingredients.*.unit' => 'sometimes|string|max:20',
        ]);

        $ingredients = $normalizer->normalize($validated['ingredients'] ?? [], (int) $request->user()->business_id);

        $imagePath = $this->storePhoto($request);
        try {
            return DB::transaction(function () use ($request, $imagePath, $ingredients) {
                $product = Product::create([
                    'business_id' => $request->user()->business_id,
                    'name' => $request->name,
                    'category' => $request->category,
                    'price' => $request->price,
                    'description' => $request->description,
                    'emoji' => $request->emoji ?? '🍵',
                    'is_available' => $request->is_available ?? true,
                    'image' => $imagePath,
                ]);

                // Save ingredients
                if ($ingredients) {
                    foreach ($ingredients as $ingredient) {
                        ProductIngredient::create([
                            'product_id' => $product->id,
                            'stock_item_id' => $ingredient['stock_id'],
                            'quantity' => $ingredient['quantity'],
                        ]);
                    }
                }

                return response()->json([
                    'message' => 'Product saved',
                    'product' => $product->load('productIngredients.stockItem'),
                ], 201);
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $exception;
        }
    }

    // GET /api/products/{id}
    public function show(Request $request, $id)
    {
        $product = Product::where('business_id', $request->user()->business_id)
            ->with('productIngredients.stockItem')
            ->findOrFail($id);

        return response()->json(['product' => $product]);
    }

    // PUT /api/products/{id}
    public function update(Request $request, $id, RecipeIngredientNormalizer $normalizer)
    {
        $this->ensureCanManageProducts($request);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255', 'category' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0', 'description' => 'nullable|string|max:1000',
            'emoji' => 'nullable|string|max:10', 'is_available' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'display_order' => 'sometimes|integer|min:0', 'ingredients' => 'sometimes|array',
            'replace_ingredients' => 'sometimes|boolean',
            'ingredients.*.stock_id' => ['required', Rule::exists('stock_items', 'id')->where('business_id', $request->user()->business_id)],
            'ingredients.*.quantity' => 'required|numeric|decimal:0,4|min:0.0001|max:99999999.9999',
            'ingredients.*.unit' => 'sometimes|string|max:20',
        ]);
        $ingredients = $normalizer->normalize($validated['ingredients'] ?? [], (int) $request->user()->business_id);
        $product = Product::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        if ($request->user()->isStaff()) {
            return $this->submitEditRequest($request, $product, $validated, $ingredients);
        }

        $imagePath = $this->storePhoto($request);
        $oldImage = $product->image;
        try {
            DB::transaction(function () use ($request, $product, $imagePath, $ingredients) {
                $product->update($request->only([
                    'name', 'category', 'price', 'description',
                    'emoji', 'is_available', 'display_order',
                ]));

                if ($imagePath) {
                    $product->update(['image' => $imagePath]);
                }

                // Update ingredients if provided
                if ($request->has('ingredients') || $request->boolean('replace_ingredients')) {
                    $product->productIngredients()->delete();
                    foreach ($ingredients as $ingredient) {
                        ProductIngredient::create([
                            'product_id' => $product->id,
                            'stock_item_id' => $ingredient['stock_id'],
                            'quantity' => $ingredient['quantity'],
                        ]);
                    }
                    // A recipe-only edit must invalidate older offline product edits.
                    if (! $product->wasChanged()) {
                        $product->touch();
                    }
                }
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $exception;
        }
        // Keep the previous photo until both storage and the database update succeed.
        if ($imagePath && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return response()->json([
            'message' => 'Product updated',
            'product' => $product->load('productIngredients.stockItem'),
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy(Request $request, $id)
    {
        $this->ensureCanManageProducts($request);
        $product = Product::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        DB::transaction(function () use ($product): void {
            $product->productIngredients()->delete();
            $imagePath = $product->image;
            $product->delete();
            if ($imagePath) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($imagePath));
            }
        });

        return response()->json(['message' => 'Product deleted']);
    }

    public function image(Product $product)
    {
        abort_unless($product->image && Storage::disk('public')->exists($product->image), 404);

        return Storage::disk('public')->response($product->image);
    }

    private function ensureCanManageProducts(Request $request): void
    {
        abort_unless(
            $request->user()->isOwner() || $request->user()->isStaff(),
            403,
            'Only business owners and staff can manage products.'
        );
    }

    private function submitEditRequest(Request $request, Product $product, array $validated, array $ingredients)
    {
        $changes = $validated;
        unset($changes['image']);
        if (array_key_exists('ingredients', $changes)) {
            $changes['ingredients'] = $ingredients;
        }

        $imagePath = $this->storePhoto($request);
        try {
            $editRequest = DB::transaction(function () use ($request, $product, $changes, $imagePath): ProductEditRequest {
                $lockedProduct = Product::query()
                    ->where('business_id', $request->user()->business_id)
                    ->lockForUpdate()
                    ->findOrFail($product->id);
                abort_if(ProductEditRequest::query()
                    ->where('product_id', $lockedProduct->id)
                    ->where('status', 'pending')->exists(), 409,
                    'This product already has an edit awaiting owner review.');

                $editRequest = ProductEditRequest::create([
                    'business_id' => $lockedProduct->business_id,
                    'product_id' => $lockedProduct->id,
                    'requested_by' => $request->user()->id,
                    'expected_revision' => (int) $lockedProduct->revision,
                    'status' => 'pending',
                    'changes' => $changes,
                    'image_path' => $imagePath,
                ]);

                User::query()->where('business_id', $lockedProduct->business_id)
                    ->where('role', 'owner')->where('status', 'active')
                    ->each(fn (User $owner) => Notification::create([
                        'user_id' => $owner->id,
                        'business_id' => $lockedProduct->business_id,
                        'type' => 'product_edit_request',
                        'title' => 'Product edit needs review',
                        'message' => "{$request->user()->name} requested changes to {$lockedProduct->name}.",
                        'data' => ['request_id' => $editRequest->id, 'product_id' => $lockedProduct->id],
                    ]));

                return $editRequest;
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Product edit sent to the owner for review',
            'approval_pending' => true,
            'edit_request_id' => $editRequest->id,
            'product' => $product->load('productIngredients.stockItem'),
        ], 202);
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $path = $request->file('image')->store('products/'.$request->user()->business_id, 'public');
        abort_unless($path, 503, 'The product photo could not be saved. Please try again or contact the administrator.');

        return $path;
    }
}
