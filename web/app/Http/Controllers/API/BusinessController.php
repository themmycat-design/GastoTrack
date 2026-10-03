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
        $data = $request->validate([
            'name' => 'sometimes|string|max:255', 'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500', 'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email', 'business_type' => 'sometimes|in:restaurant,cafe,retail,service,other',
            'currency' => 'sometimes|string|size:3',
        ]);
        $request->user()->business->update($data);
        return response()->json(['message' => 'Business updated', 'business' => $request->user()->business->fresh()]);
    }
}
