<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    private function owner(Request $request): void { abort_unless($request->user()->isOwner(), 403); }
    public function index(Request $request) { $this->owner($request); return response()->json(['goals' => Goal::where('business_id', $request->user()->business_id)->latest()->get()->append('progress_percentage')]); }
    public function show(Request $request, $id) { $this->owner($request); return response()->json(['goal' => Goal::where('business_id', $request->user()->business_id)->findOrFail($id)->append('progress_percentage')]); }
    public function store(Request $request)
    {
        $this->owner($request);
        $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:1000', 'target_amount' => 'required|numeric|min:0.01', 'current_amount' => 'nullable|numeric|min:0', 'deadline' => 'nullable|date|after_or_equal:today']);
        $goal = Goal::create($data + ['business_id' => $request->user()->business_id]);
        return response()->json(['message' => 'Goal created', 'goal' => $goal], 201);
    }
    public function update(Request $request, $id)
    {
        $this->owner($request); $goal = Goal::where('business_id', $request->user()->business_id)->findOrFail($id);
        $data = $request->validate(['name' => 'sometimes|string|max:255', 'description' => 'nullable|string|max:1000', 'target_amount' => 'sometimes|numeric|min:0.01', 'current_amount' => 'sometimes|numeric|min:0', 'deadline' => 'nullable|date', 'status' => 'sometimes|in:active,completed,cancelled']);
        $goal->update($data); return response()->json(['message' => 'Goal updated', 'goal' => $goal->fresh()]);
    }
    public function complete(Request $request, $id)
    {
        $this->owner($request); $goal = Goal::where('business_id', $request->user()->business_id)->findOrFail($id);
        $goal->update(['status' => 'completed', 'current_amount' => $goal->target_amount, 'completed_at' => now()]);
        return response()->json(['message' => 'Goal completed', 'goal' => $goal->fresh()]);
    }
    public function destroy(Request $request, $id) { $this->owner($request); Goal::where('business_id', $request->user()->business_id)->findOrFail($id)->delete(); return response()->json(['message' => 'Goal deleted']); }
}
