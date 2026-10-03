<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\StockItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private function base(Request $request) { abort_unless($request->user()->isOwner(), 403); return Transaction::where('business_id', $request->user()->business_id); }
    public function summary(Request $request) { $q = $this->base($request); $income = (clone $q)->where('type','income')->sum('amount'); $expense = (clone $q)->where('type','expense')->sum('amount'); return response()->json(['income'=>$income,'expense'=>$expense,'net'=>$income-$expense]); }
    public function categories(Request $request) { $q = $this->base($request); return response()->json(['categories'=>$q->select('type','category',DB::raw('SUM(amount) as total'))->groupBy('type','category')->get()]); }
    public function topProducts(Request $request) { $this->base($request); return response()->json(['products'=>OrderItem::whereHas('order', fn($q)=>$q->where('business_id',$request->user()->business_id))->select('product_id','product_name',DB::raw('SUM(quantity) as quantity_sold'),DB::raw('SUM(subtotal) as revenue'))->groupBy('product_id','product_name')->orderByDesc('quantity_sold')->limit(10)->get()]); }
    public function stockValue(Request $request) { $this->base($request); return response()->json(['stock_value'=>StockItem::where('business_id',$request->user()->business_id)->selectRaw('COALESCE(SUM(current_quantity * unit_cost),0) as total')->value('total')]); }
    public function trends(Request $request) { $q=$this->base($request); return response()->json(['trends'=>$q->select('transaction_date','type',DB::raw('SUM(amount) as total'))->groupBy('transaction_date','type')->orderBy('transaction_date')->get()]); }
}
