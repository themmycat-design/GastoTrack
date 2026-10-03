<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $businessId = $user->business_id;
        
        // Start query
        $query = Product::where('business_id', $businessId);
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        
        if ($request->has('available')) {
            $query->where('is_available', $request->available);
        }
        
        // Get products with pagination
        $products = $query->orderBy('display_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();
        
        // Get unique categories
        $categories = Product::where('business_id', $businessId)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();
        
        // Get stats
        $totalProducts = Product::where('business_id', $businessId)->count();
        $availableProducts = Product::where('business_id', $businessId)->where('is_available', true)->count();
        $categoriesCount = Product::where('business_id', $businessId)->distinct('category')->count('category');
        
        return view('products.index', compact('products', 'categories', 'totalProducts', 'availableProducts', 'categoriesCount'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'emoji' => 'nullable|string|max:10',
            'is_available' => 'boolean',
        ]);
        
        $user = auth()->user();
        
        // Get next display order
        $maxOrder = Product::where('business_id', $user->business_id)->max('display_order');
        
        Product::create([
            'business_id' => $user->business_id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? 0,
            'emoji' => $validated['emoji'] ?? '🍽️',
            'is_available' => $validated['is_available'] ?? true,
            'display_order' => ($maxOrder ?? 0) + 1,
        ]);
        
        return redirect()->route('products.index')
            ->with('success', 'Product created successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        // Ensure the product belongs to the user's business
        if ($product->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'emoji' => 'nullable|string|max:10',
            'is_available' => 'boolean',
        ]);
        
        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? 0,
            'emoji' => $validated['emoji'] ?? '🍽️',
            'is_available' => $validated['is_available'] ?? true,
        ]);
        
        return redirect()->route('products.index')
            ->with('success', 'Product updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        // Ensure the product belongs to the user's business
        if ($product->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $product->delete();
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Toggle product availability.
     */
    public function toggleAvailability(Product $product)
    {
        // Ensure the product belongs to the user's business
        if ($product->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $product->update(['is_available' => !$product->is_available]);
        
        return response()->json([
            'success' => true,
            'is_available' => $product->is_available
        ]);
    }
}
