<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        $products = Product::where('business_id', $request->user()->business_id)
            ->with('ingredients.stockItem')
            ->orderBy('display_order')
            ->get();

        return response()->json(['products' => $products]);
    }

    // POST /api/products
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'category'       => 'required|string',
            'price'          => 'required|numeric|min:0',
            'description'    => 'nullable|string',
            'emoji'          => 'nullable|string',
            'is_available'   => 'nullable|boolean',
            'ingredients'    => 'nullable|array',
            'ingredients.*.stock_id'  => 'required|exists:stock_items,id',
            'ingredients.*.quantity'  => 'required|numeric|min:0',
            'ingredients.*.unit'      => 'required|string',
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

        // Save ingredients
        if ($request->has('ingredients')) {
            foreach ($request->ingredients as $ingredient) {
                ProductIngredient::create([
                    'product_id' => $product->id,
                    'stock_id'   => $ingredient['stock_id'],
                    'quantity'   => $ingredient['quantity'],
                    'unit'       => $ingredient['unit'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Product saved',
            'product' => $product->load('ingredients.stockItem'),
        ], 201);
    }

    // GET /api/products/{id}
    public function show(Request $request, $id)
    {
        $product = Product::where('business_id', $request->user()->business_id)
            ->with('ingredients.stockItem')
            ->findOrFail($id);

        return response()->json(['product' => $product]);
    }

    // PUT /api/products/{id}
    public function update(Request $request, $id)
    {
        $product = Product::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $product->update($request->only([
            'name', 'category', 'price', 'description',
            'emoji', 'is_available', 'display_order'
        ]));

        // Update ingredients if provided
        if ($request->has('ingredients')) {
            $product->ingredients()->delete();
            foreach ($request->ingredients as $ingredient) {
                ProductIngredient::create([
                    'product_id' => $product->id,
                    'stock_id'   => $ingredient['stock_id'],
                    'quantity'   => $ingredient['quantity'],
                    'unit'       => $ingredient['unit'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Product updated',
            'product' => $product->load('ingredients.stockItem'),
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy(Request $request, $id)
    {
        $product = Product::where('business_id', $request->user()->business_id)
            ->findOrFail($id);

        $product->ingredients()->delete();
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }
}