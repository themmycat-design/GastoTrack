<?php

namespace App\Http\Controllers;

use App\Services\OwnerDashboardMetrics;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerAnalyticsController extends Controller
{
    public function __invoke(Request $request, OwnerDashboardMetrics $metrics)
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['week', 'month', 'year', 'custom'])],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $timezone = config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $hasCustomDates = isset($validated['start_date']) || isset($validated['end_date']);
        $period = $hasCustomDates ? 'custom' : ($validated['period'] ?? 'month');

        if ($period === 'custom' && !$hasCustomDates) {
            throw ValidationException::withMessages([
                'start_date' => 'Choose a start and end date for a custom report.',
            ]);
        }

        if ($period === 'custom') {
            $end = isset($validated['end_date'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['end_date'], $timezone)
                : $today;
            $start = isset($validated['start_date'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['start_date'], $timezone)
                : $end->startOfMonth();
        } else {
            $end = $today;
            $start = match ($period) {
                'week' => $today->startOfWeek(CarbonInterface::MONDAY),
                'year' => $today->startOfYear(),
                default => $today->startOfMonth(),
            };
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }

        $rangeDays = (int) $start->diffInDays($end);
        if ($rangeDays > 365) {
            throw ValidationException::withMessages([
                'start_date' => 'Choose a reporting period of one year or less.',
            ]);
        }

        [$comparisonStart, $comparisonEnd] = match ($period) {
            'week' => [$start->subWeek(), $start->subWeek()->addDays($rangeDays)->endOfDay()],
            'month' => $this->monthComparison($start, $end),
            'year' => [$start->subYear()->startOfYear(), $end->subYearNoOverflow()->endOfDay()],
            default => [$start->subDays($rangeDays + 1), $start->subDay()->endOfDay()],
        };

        $data = $metrics->analytics(
            $request->user(),
            $start->startOfDay(),
            $end->endOfDay(),
            $comparisonStart->startOfDay(),
            $comparisonEnd->endOfDay(),
            $period,
        );

        $data['chartLinks'] = array_map(
            function (string $date) use ($data, $end): string {
                if ($data['chartInterval'] === 'day') {
                    return route('analytics.day', ['date' => $date]);
                }

                $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $date.'-01');

                return route('analytics.index', [
                    'start_date' => $monthStart->toDateString(),
                    'end_date' => $monthStart->endOfMonth()->min($end)->toDateString(),
                ]);
            },
            $data['chart']['dates'],
        );

        return view('analytics.index', array_merge($data, [
            'business' => $request->user()->business,
            'currencyCode' => strtoupper((string) ($request->user()->business?->currency ?: 'PHP')),
            'period' => $period,
        ]));
    }

    private function monthComparison(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $comparisonStart = $start->subMonthNoOverflow()->startOfMonth();

        return [
            $comparisonStart,
            $comparisonStart->setDay(min($end->day, $comparisonStart->daysInMonth))->endOfDay(),
        ];
    }
}
