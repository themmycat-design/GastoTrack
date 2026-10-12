<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Services\SalesGoals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OwnerGoalController extends Controller
{
    public function index(Request $request, SalesGoals $salesGoals): View
    {
        $business = $request->user()->business;
        $currentMonth = $salesGoals->monthStart()->toDateString();
        $currentWeekStart = $salesGoals->weekStart()->toDateString();
        $goals = Goal::query()
            ->where('business_id', $business->id)
            ->whereIn('goal_type', [SalesGoals::MONTHLY, SalesGoals::WEEKLY])
            ->orderByDesc('period_start')
            ->orderBy('goal_type')
            ->get()
            ->map(fn (Goal $goal) => $salesGoals->present($goal));
        $currentMonthGoal = $goals->first(fn (array $goal) =>
            $goal['goal_type'] === SalesGoals::MONTHLY && substr($goal['period_start'], 0, 10) === $currentMonth
        );
        $currentWeekGoal = $goals->first(fn (array $goal) =>
            $goal['goal_type'] === SalesGoals::WEEKLY && substr($goal['period_start'], 0, 10) === $currentWeekStart
        );

        return view('goals.index', compact(
            'business',
            'currentMonth',
            'currentWeekStart',
            'currentMonthGoal',
            'currentWeekGoal',
            'goals',
        ));
    }

    public function store(Request $request, SalesGoals $salesGoals): RedirectResponse
    {
        $data = $request->validate([
            'goal_type' => ['required', 'in:'.SalesGoals::MONTHLY.','.SalesGoals::WEEKLY],
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ]);

        if ($data['goal_type'] === SalesGoals::MONTHLY) {
            $periodData = $request->validate([
                'month' => ['required', 'date_format:Y-m'],
                'week_date' => ['prohibited'],
            ]);
            $periodStart = $salesGoals->monthStart($periodData['month']);
            if ($periodStart->lessThan($salesGoals->monthStart())) {
                return back()->withErrors(['month' => 'Past months cannot be changed.'])->withInput();
            }
            $periodEnd = $periodStart->endOfMonth();
            $periodLabel = $periodStart->format('F Y');
            $successMessage = 'Monthly sales target saved.';
        } else {
            $periodData = $request->validate([
                'week_date' => ['required', 'date_format:Y-m-d'],
                'month' => ['prohibited'],
            ]);
            $periodStart = $salesGoals->weekStart($periodData['week_date']);
            if ($periodStart->lessThan($salesGoals->weekStart())) {
                return back()->withErrors(['week_date' => 'Past weeks cannot be changed.'])->withInput();
            }
            $periodEnd = $periodStart->addDays(6);
            $periodLabel = $periodStart->format('M j').' - '.$periodEnd->format('M j, Y');
            $successMessage = 'Weekly sales target saved.';
        }

        $goalType = $data['goal_type'];
        $businessId = $request->user()->business_id;

        // One target per business, goal type, and period. Progress is calculated
        // from eligible orders and is deliberately never saved to current_amount.
        DB::table('goals')->upsert([[
            'business_id' => $businessId,
            'goal_type' => $goalType,
            'period_start' => $periodStart->toDateString(),
            'name' => ucfirst(str_replace('_', ' ', $goalType)).' - '.$periodLabel,
            'target_amount' => $data['target_amount'],
            'deadline' => $periodEnd->toDateString(),
            'status' => 'active',
            'current_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['business_id', 'goal_type', 'period_start'], ['name', 'target_amount', 'deadline', 'updated_at']);

        return redirect()->route('goals.index')->with('status', $successMessage);
    }
}
