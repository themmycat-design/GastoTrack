<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        $canManageProducts = $request->user()->isOwner() || $request->user()->isStaff();
        $products = Product::where('business_id', $request->user()->business_id)
            ->with('productIngredients.stockItem')
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->category))
            ->when(!$canManageProducts, fn ($query) => $query->where('is_available', true))
            ->orderBy('display_order')
            ->get();

        return response()->json(['products' => $products]);
    }

    // POST /api/products
    public function store(Request $request)
    {
        $this->ensureCanManageProducts($request);
        $request->validate([
            'name'           => 'required|string|max:255',
            'category'       => 'required|string',
            'price'          => 'required|numeric|min:0',
            'description'    => 'nullable|string',
            'emoji'          => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'is_available'   => 'nullable|boolean',
            'ingredients'    => 'nullable|array',
            'ingredients.*.stock_id'  => ['required', Rule::exists('stock_items', 'id')->where('business_id', $request->user()->business_id)],
            'ingredients.*.quantity'  => 'required|numeric|min:0',
        ]);

        $product = Product::create([
            'business_id'  => $request->user()->business_id,
            'name'         => $request->name,
            'category'     => $request->category,
            'price'        => $request->price,
            'description'  => $request->description,
            'emoji'        => $request->emoji ?? '🍵',
            'is_available' => $request->is_available ?? true,
        ]);

        if ($request->hasFile('image')) {
            $product->update([
                'image' => $request->file('image')->store('products/'.$request->user()->business_id, 'public'),
            ]);
        }

        // Save ingredients
        if ($request->has('ingredients')) {
            foreach ($request->ingredients as $ingredient) {
                ProductIngredient::create([
                    'product_id' => $product->id,
                    'stock_item_id' => $ingredient['stock_id'],
                    'quantity'   => $ingredient['quantity'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Product saved',
            'product' => $product->load('productIngredients.stockItem'),
        ], 201);
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
    public function update(Request $request, $id)
    {
        $this->ensureCanManageProducts($request);
        $request->validate([
            'name' => 'sometimes|string|max:255', 'category' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0', 'description' => 'nullable|string|max:1000',
            'emoji' => 'nullable|string|max:10', 'is_available' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'display_order' => 'sometimes|integer|min:0', 'ingredients' => 'sometimes|array',
            'replace_ingredients' => 'sometimes|boolean',
            'ingredients.*.stock_id' => ['required', Rule::exists('stock_items', 'id')->where('business_id', $request->user()->business_id)],
            'ingredients.*.quantity' => 'required|numeric|min:0.0001',
        ]);
        $product = Product::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $product->update($request->only([
            'name', 'category', 'price', 'description',
            'emoji', 'is_available', 'display_order'
        ]));

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $product->update([
                'image' => $request->file('image')->store('products/'.$request->user()->business_id, 'public'),
            ]);
        }

        // Update ingredients if provided
        if ($request->has('ingredients') || $request->boolean('replace_ingredients')) {
            $product->productIngredients()->delete();
            foreach ($request->input('ingredients', []) as $ingredient) {
                ProductIngredient::create([
                    'product_id' => $product->id,
                    'stock_item_id' => $ingredient['stock_id'],
                    'quantity'   => $ingredient['quantity'],
                ]);
            }
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

        $product->productIngredients()->delete();
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();

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
}
