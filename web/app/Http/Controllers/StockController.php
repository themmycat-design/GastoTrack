<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /** Download the owner's current (non-deleted) inventory, including inactive items. */
    public function export(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $currency = strtoupper((string) ($request->user()->business?->currency ?: 'PHP'));
        $filename = 'gastotrack-inventory-'.now(config('business.timezone'))->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($businessId, $currency): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Item', 'Unit', 'Current quantity', 'Minimum quantity', 'Unit cost ('.$currency.')', 'Stock value ('.$currency.')', 'Status', 'Updated at']);

            foreach (StockItem::where('business_id', $businessId)->orderBy('id')->cursor() as $item) {
                $safeText = static fn (string $value): string => preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value;
                fputcsv($output, [
                    $safeText($item->name),
                    $safeText($item->unit),
                    $item->current_quantity,
                    $item->minimum_quantity,
                    $item->unit_cost,
                    number_format((float) $item->current_quantity * (float) $item->unit_cost, 2, '.', ''),
                    $item->active ? 'Active' : 'Inactive',
                    $item->updated_at?->toDateTimeString(),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

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
        
        $stockStatus = $request->query('stock_status');
        if ($stockStatus === 'out') {
            $query->where('current_quantity', '<=', 0);
        } elseif ($stockStatus === 'low') {
            $query->where('current_quantity', '>', 0)
                ->whereColumn('current_quantity', '<=', 'minimum_quantity');
        } elseif ($stockStatus === 'in') {
            $query->where('current_quantity', '>', 0)
                ->whereColumn('current_quantity', '>', 'minimum_quantity');
        } elseif ($stockStatus === 'attention' || $request->query('low_stock') == '1') {
            // Keep existing inventory-alert links working.
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
        $attentionCounts = StockItem::where('business_id', $businessId)
            ->where('active', true)
            ->selectRaw('SUM(CASE WHEN current_quantity > 0 AND current_quantity <= minimum_quantity THEN 1 ELSE 0 END) as low_count')
            ->selectRaw('SUM(CASE WHEN current_quantity <= 0 THEN 1 ELSE 0 END) as out_count')
            ->first();
        $lowStockCount = (int) ($attentionCounts?->low_count ?? 0);
        $outOfStockCount = (int) ($attentionCounts?->out_count ?? 0);
        $inStockCount = max(0, $totalItems - $lowStockCount - $outOfStockCount);
        $totalValue = StockItem::where('business_id', $businessId)
            ->where('active', true)
            ->selectRaw('SUM(current_quantity * unit_cost) as total')
            ->value('total') ?? 0;
        $recentMovements = StockMovement::query()
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->where('stock_items.business_id', $businessId)
            ->whereNull('stock_items.deleted_at')
            ->select('stock_movements.*', 'stock_items.name as stock_name', 'stock_items.unit as stock_unit')
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit(5)
            ->get();

        return view('stock.index', compact('stockItems', 'totalItems', 'lowStockCount', 'outOfStockCount', 'inStockCount', 'totalValue', 'recentMovements'));
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
