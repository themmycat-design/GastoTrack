<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Services\MonthlySalesGoals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class OwnerGoalController extends Controller
{
    public function index(Request $request, MonthlySalesGoals $salesGoals): View
    {
        $business = $request->user()->business;
        $currentMonth = $salesGoals->monthStart()->toDateString();
        $goals = Goal::query()
            ->where('business_id', $business->id)
            ->where('goal_type', 'monthly_confirmed_sales')
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (Goal $goal) => $salesGoals->present($goal));
        $currentGoal = $goals->first(fn (array $goal) => substr($goal['period_start'], 0, 10) === $currentMonth);

        return view('goals.index', compact('business', 'currentMonth', 'currentGoal', 'goals'));
    }

    public function store(Request $request, MonthlySalesGoals $salesGoals): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ]);

        $month = $salesGoals->monthStart($data['month']);
        if ($month->lessThan($salesGoals->monthStart())) {
            return back()->withErrors(['month' => 'Past months cannot be changed.'])->withInput();
        }

        $businessId = $request->user()->business_id;
        // The unique business/type/month key also protects concurrent saves.
        // current_amount is deliberately never written for automatic goals.
        DB::table('goals')->upsert([[
            'business_id' => $businessId,
            'goal_type' => 'monthly_confirmed_sales',
            'period_start' => $month->toDateString(),
            'name' => 'Confirmed sales · '.$month->format('F Y'),
            'target_amount' => $data['target_amount'],
            'deadline' => $month->endOfMonth()->toDateString(),
            'status' => 'active',
            'current_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['business_id', 'goal_type', 'period_start'], ['name', 'target_amount', 'deadline', 'updated_at']);

        return redirect()->route('goals.index')->with('status', 'Monthly sales target saved.');
    }
}
