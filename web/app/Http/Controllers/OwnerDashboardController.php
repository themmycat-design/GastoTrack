<?php

namespace App\Http\Controllers;

use App\Services\OwnerDashboardMetrics;
use App\Services\MonthlySalesGoals;
use App\Models\Goal;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function __invoke(Request $request, OwnerDashboardMetrics $metrics, MonthlySalesGoals $salesGoals)
    {
        $monthlyGoal = Goal::query()
            ->where('business_id', $request->user()->business_id)
            ->where('goal_type', 'monthly_confirmed_sales')
            ->whereDate('period_start', $salesGoals->monthStart()->toDateString())
            ->first();

        return view('dashboard', $metrics->overview($request->user()) + [
            'monthlyGoal' => $monthlyGoal ? $salesGoals->present($monthlyGoal) : null,
        ]);
    }
}
