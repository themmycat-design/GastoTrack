<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $businessId = $user->business_id;
        
        // Start query
        $query = StockItem::where('business_id', $businessId);
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }
        
        if ($request->has('low_stock') && $request->low_stock == '1') {
            $query->whereColumn('current_quantity', '<=', 'minimum_quantity');
        }
        
        if ($request->has('active')) {
            $query->where('active', $request->active);
        }
        
        // Get stock items with pagination
        $stockItems = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();
        
        // Get stats
        $totalItems = StockItem::where('business_id', $businessId)->where('active', true)->count();
        $lowStockCount = StockItem::where('business_id', $businessId)
            ->where('active', true)
            ->whereColumn('current_quantity', '<=', 'minimum_quantity')
            ->count();
        $totalValue = StockItem::where('business_id', $businessId)
            ->where('active', true)
            ->selectRaw('SUM(current_quantity * unit_cost) as total')
            ->value('total') ?? 0;
        
        return view('stock.index', compact('stockItems', 'totalItems', 'lowStockCount', 'totalValue'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'current_quantity' => 'required|numeric|min:0',
            'minimum_quantity' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);
        
        $user = auth()->user();
        
        StockItem::create([
            'business_id' => $user->business_id,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'current_quantity' => $validated['current_quantity'],
            'minimum_quantity' => $validated['minimum_quantity'],
            'unit_cost' => $validated['unit_cost'],
            'active' => $validated['active'] ?? true,
        ]);
        
        return redirect()->route('stock.index')
            ->with('success', 'Stock item created successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StockItem $stock)
    {
        // Ensure the stock item belongs to the user's business
        if ($stock->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'minimum_quantity' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);
        
        $stock->update([
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'minimum_quantity' => $validated['minimum_quantity'],
            'unit_cost' => $validated['unit_cost'],
            'active' => $validated['active'] ?? true,
        ]);
        
        return redirect()->route('stock.index')
            ->with('success', 'Stock item updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockItem $stock)
    {
        // Ensure the stock item belongs to the user's business
        if ($stock->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $stock->delete();
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Adjust stock quantity.
     */
    public function adjust(Request $request, StockItem $stock)
    {
        // Ensure the stock item belongs to the user's business
        if ($stock->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'type' => 'required|in:add,subtract,set',
            'quantity' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);
        
        $stock = DB::transaction(function () use ($stock, $validated) {
            $lockedStock = StockItem::where('business_id', auth()->user()->business_id)
                ->lockForUpdate()
                ->findOrFail($stock->id);
            $quantityBefore = (float) $lockedStock->current_quantity;
            $quantity = (float) $validated['quantity'];

            $quantityAfter = match ($validated['type']) {
                'add' => $quantityBefore + $quantity,
                'subtract' => $quantityBefore - $quantity,
                'set' => $quantity,
            };
            abort_if($quantityAfter < 0, 422, 'Adjustment would make stock negative.');

            $lockedStock->update(['current_quantity' => $quantityAfter]);
            StockMovement::create([
                'stock_item_id' => $lockedStock->id,
                'user_id' => auth()->id(),
                'type' => $validated['type'] === 'add'
                    ? 'in'
                    : ($validated['type'] === 'subtract' ? 'out' : 'adjustment'),
                'quantity' => $quantity,
                'previous_quantity' => $quantityBefore,
                'new_quantity' => $quantityAfter,
                'reason' => $validated['reason'] ?? 'Manual stock adjustment',
            ]);

            return $lockedStock;
        });
        app(NotificationService::class)->notifyLowStock($stock);
        
        return response()->json([
            'success' => true,
            'new_quantity' => $stock->current_quantity
        ]);
    }
}
