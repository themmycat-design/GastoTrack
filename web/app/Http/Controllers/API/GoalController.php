<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Services\MonthlySalesGoals;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    private function owner(Request $request): void
    {
        abort_unless($request->user()->isOwner(), 403);
    }

    private function businessGoal(Request $request, int $id): Goal
    {
        return Goal::where('business_id', $request->user()->business_id)->findOrFail($id);
    }

    public function index(Request $request, MonthlySalesGoals $salesGoals)
    {
        $this->owner($request);
        $goals = Goal::where('business_id', $request->user()->business_id)
            ->latest()
            ->get()
            ->map(fn (Goal $goal) => $salesGoals->present($goal));

        return response()->json(['goals' => $goals]);
    }

    public function show(Request $request, int $id, MonthlySalesGoals $salesGoals)
    {
        $this->owner($request);

        return response()->json(['goal' => $salesGoals->present($this->businessGoal($request, $id))]);
    }

    public function store(Request $request, MonthlySalesGoals $salesGoals)
    {
        $this->owner($request);
        // Existing manual goals remain supported. Monthly sales goals use
        // the dedicated owner-only monthly target form.
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_amount' => 'required|numeric|min:0.01|max:99999999.99',
            'current_amount' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date|after_or_equal:today',
            'goal_type' => 'prohibited',
            'period_start' => 'prohibited',
        ]);
        $goal = Goal::create($data + ['business_id' => $request->user()->business_id]);

        return response()->json(['message' => 'Goal created', 'goal' => $salesGoals->present($goal)], 201);
    }

    public function update(Request $request, int $id, MonthlySalesGoals $salesGoals)
    {
        $this->owner($request);
        $goal = $this->businessGoal($request, $id);
        if ($goal->goal_type === 'monthly_confirmed_sales') {
            $data = $request->validate([
                'target_amount' => 'required|numeric|min:0.01|max:99999999.99',
                'current_amount' => 'prohibited',
                'status' => 'prohibited',
                'period_start' => 'prohibited',
                'goal_type' => 'prohibited',
                'deadline' => 'prohibited',
            ]);
            abort_if($goal->period_start->lt($salesGoals->monthStart()), 409, 'Past monthly targets cannot be changed.');
        } else {
            $data = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string|max:1000',
                'target_amount' => 'sometimes|numeric|min:0.01|max:99999999.99',
                'current_amount' => 'sometimes|numeric|min:0',
                'deadline' => 'nullable|date',
                'status' => 'sometimes|in:active,completed,cancelled',
                'period_start' => 'prohibited',
                'goal_type' => 'prohibited',
            ]);
        }

        $goal->update($data);

        return response()->json(['message' => 'Goal updated', 'goal' => $salesGoals->present($goal->fresh())]);
    }

    public function complete(Request $request, int $id, MonthlySalesGoals $salesGoals)
    {
        $this->owner($request);
        $goal = $this->businessGoal($request, $id);
        abort_if($goal->goal_type === 'monthly_confirmed_sales', 409, 'Monthly sales progress cannot be completed manually.');
        $goal->update(['status' => 'completed', 'current_amount' => $goal->target_amount, 'completed_at' => now()]);

        return response()->json(['message' => 'Goal completed', 'goal' => $salesGoals->present($goal->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->owner($request);
        $goal = $this->businessGoal($request, $id);
        abort_if($goal->goal_type === 'monthly_confirmed_sales', 409, 'Monthly sales target history cannot be deleted.');
        $goal->delete();

        return response()->json(['message' => 'Goal deleted']);
    }
}
