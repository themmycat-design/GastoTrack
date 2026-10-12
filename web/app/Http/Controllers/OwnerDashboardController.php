<?php

namespace App\Http\Controllers;

use App\Services\OwnerDashboardMetrics;
use App\Services\SalesGoals;
use App\Models\Goal;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function __invoke(Request $request, OwnerDashboardMetrics $metrics, SalesGoals $salesGoals)
    {
        $businessGoals = Goal::query()
            ->where('business_id', $request->user()->business_id)
            ->whereIn('goal_type', [SalesGoals::MONTHLY, SalesGoals::WEEKLY])
            ->whereIn('period_start', [
                $salesGoals->monthStart()->toDateString(),
                $salesGoals->weekStart()->toDateString(),
            ])
            ->get();
        $monthlyGoal = $businessGoals
            ->first(fn (Goal $goal) => $goal->goal_type === SalesGoals::MONTHLY
                && $goal->period_start->toDateString() === $salesGoals->monthStart()->toDateString());
        $weeklyGoal = $businessGoals
            ->first(fn (Goal $goal) => $goal->goal_type === SalesGoals::WEEKLY
                && $goal->period_start->toDateString() === $salesGoals->weekStart()->toDateString());

        return view('dashboard', $metrics->overview($request->user()) + [
            'monthlyGoal' => $monthlyGoal ? $salesGoals->present($monthlyGoal) : null,
            'weeklyGoal' => $weeklyGoal ? $salesGoals->present($weeklyGoal) : null,
            'currentWeekStart' => $salesGoals->weekStart(),
            'currentWeekEnd' => $salesGoals->weekStart()->addDays(6),
        ]);
    }
}
