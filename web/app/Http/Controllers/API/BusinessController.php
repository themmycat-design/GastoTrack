<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function show(Request $request) { return response()->json(['business' => $request->user()->business]); }
    public function update(Request $request)
    {
        abort_unless($request->user()->isOwner(), 403);
        if ($request->filled('currency')) {
            $request->merge(['currency' => strtoupper((string) $request->input('currency'))]);
        }
        $data = $request->validate([
            'name' => 'sometimes|string|max:255', 'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500', 'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email', 'business_type' => 'sometimes|in:restaurant,cafe,retail,service,other',
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
        ]);
        $business = $request->user()->business;
        if (isset($data['currency']) && $data['currency'] !== $business->currency) {
            abort_if(
                $business->orders()->withTrashed()->exists() || $business->transactions()->withTrashed()->exists()
                    || $business->products()->withTrashed()->exists() || $business->stockItems()->withTrashed()->exists(),
                422,
                'Currency cannot be changed after products, stock, orders, or financial records exist.'
            );
        }
        $business->update($data);
        return response()->json(['message' => 'Business updated', 'business' => $business->fresh()]);
    }
}
