<?php

namespace App\Http\Controllers;

use App\Services\OwnerDashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class OwnerAnalyticsMonthController extends Controller
{
    public function __invoke(Request $request, OwnerDashboardMetrics $metrics, string $month)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month)
            && checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4)), 404);

        $timezone = config('app.timezone');
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', $timezone)->startOfDay();
        $today = CarbonImmutable::today($timezone);
        abort_if($start->gt($today), 404);

        $end = $start->endOfMonth()->min($today)->endOfDay();
        $comparisonStart = $start->subMonthNoOverflow()->startOfMonth();
        $comparisonEnd = $comparisonStart->setDay(min($end->day, $comparisonStart->daysInMonth))->endOfDay();
        $data = $metrics->analytics($request->user(), $start, $end, $comparisonStart, $comparisonEnd, 'month');

        return view('analytics.month', [
            'month' => $start,
            'today' => $today,
            'chart' => $data['chart'],
            'totals' => $data['current'],
            'currencyCode' => strtoupper((string) ($request->user()->business?->currency ?: 'PHP')),
        ]);
    }
}
