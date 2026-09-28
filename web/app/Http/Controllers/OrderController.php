<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\StockItem;
// Siguraduhing may ProductIngredient model ka na naka-link sa product
use Illuminate\Support\Facades\DB; 

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $product = Product::findOrFail($request->product_id);
            $totalAmount = $product->price * $request->quantity;

            // 1. I-record ang Income Transaction
            $transaction = Transaction::create([
                'user_id' => $request->user()->id,
                'amount' => $totalAmount,
                'type' => 'income',
                'category' => 'Sales',
                'source' => 'Cash', // Default to cash muna for direct orders
                'date' => now()->toDateString(),
                'notes' => "Order: " . $request->quantity . "x " . $product->name,
            ]);

            // 2. I-deduct ang ingredients sa Stock
            // I-assume natin na may relation ang Product sa Ingredients niya
            $ingredients = DB::table('product_ingredients')
                             ->where('product_id', $product->id)
                             ->get();

            foreach ($ingredients as $ingredient) {
                $totalDeduction = $ingredient->quantity * $request->quantity;
                
                StockItem::where('id', $ingredient->stock_item_id)
                         ->decrement('quantity', $totalDeduction);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order recorded and stock deducted successfully!',
                'transaction' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}