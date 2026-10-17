<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sales goals | GastoTrack</title>
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php
    $currencyPrefix = ($business->currency ?: 'PHP') === 'PHP' ? "\u{20B1}" : strtoupper($business->currency).' ';
    $monthlyType = \App\Services\SalesGoals::MONTHLY;
    $weeklyType = \App\Services\SalesGoals::WEEKLY;
    $currentWeekEnd = \Carbon\CarbonImmutable::parse($currentWeekStart)->addDays(6);
@endphp
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section class="gt-enter">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">Owner workspace</p>
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Sales goals</h2>
                <p class="mt-2 text-sm text-slate-500">Set weekly and monthly targets. Progress counts completed orders with confirmed payments only.</p>
            </section>

            @if(session('status'))
                <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>
            @endif

            <section class="grid gap-5 md:grid-cols-2" aria-label="Current sales goal progress">
                <article class="gt-card gt-enter overflow-hidden p-5 sm:p-6" aria-labelledby="monthly-progress-heading">
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">{{ \Carbon\CarbonImmutable::parse($currentMonth)->format('F Y') }}</p>
                    <h3 id="monthly-progress-heading" class="mt-2 text-lg font-extrabold">Monthly target</h3>
                    @if($currentMonthGoal)
                        <p class="mt-5 text-3xl font-extrabold tracking-tight text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $currentMonthGoal['current_amount'], 2) }} <span class="text-base font-medium text-slate-500">of {{ $currencyPrefix }}{{ number_format((float) $currentMonthGoal['target_amount'], 2) }}</span></p>
                        <div class="mt-5 h-3 overflow-hidden rounded-full bg-[#EAF2EF]" role="progressbar" aria-label="Monthly sales goal progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $currentMonthGoal['progress_percentage'] }}">
                            <div class="h-full rounded-full bg-[#00C897]" style="width: {{ $currentMonthGoal['progress_percentage'] }}%"></div>
                        </div>
                        <p class="mt-3 text-sm font-semibold {{ $currentMonthGoal['target_reached'] ? 'text-[#08745D]' : 'text-slate-600' }}">{{ $currentMonthGoal['target_reached'] ? 'Target reached' : number_format($currentMonthGoal['progress_percentage'], 1).'% of target' }}</p>
                    @else
                        <p class="mt-5 text-sm text-slate-500">No sales target is set for this month yet.</p>
                    @endif
                </article>

                <article class="gt-card gt-enter overflow-hidden p-5 sm:p-6" aria-labelledby="weekly-progress-heading">
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">{{ \Carbon\CarbonImmutable::parse($currentWeekStart)->format('M j') }} - {{ $currentWeekEnd->format('M j, Y') }}</p>
                    <h3 id="weekly-progress-heading" class="mt-2 text-lg font-extrabold">Weekly target</h3>
                    @if($currentWeekGoal)
                        <p class="mt-5 text-3xl font-extrabold tracking-tight text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $currentWeekGoal['current_amount'], 2) }} <span class="text-base font-medium text-slate-500">of {{ $currencyPrefix }}{{ number_format((float) $currentWeekGoal['target_amount'], 2) }}</span></p>
                        <div class="mt-5 h-3 overflow-hidden rounded-full bg-[#EAF2EF]" role="progressbar" aria-label="Weekly sales goal progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $currentWeekGoal['progress_percentage'] }}">
                            <div class="h-full rounded-full bg-[#00C897]" style="width: {{ $currentWeekGoal['progress_percentage'] }}%"></div>
                        </div>
                        <p class="mt-3 text-sm font-semibold {{ $currentWeekGoal['target_reached'] ? 'text-[#08745D]' : 'text-slate-600' }}">{{ $currentWeekGoal['target_reached'] ? 'Target reached' : number_format($currentWeekGoal['progress_percentage'], 1).'% of target' }}</p>
                    @else
                        <p class="mt-5 text-sm text-slate-500">No sales target is set for this week yet.</p>
                    @endif
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-2" aria-label="Set sales targets">
                <article class="gt-card gt-enter p-5 sm:p-6" aria-labelledby="set-monthly-goal-heading">
                    <h3 id="set-monthly-goal-heading" class="text-lg font-extrabold">Set a monthly target</h3>
                    <p class="mt-1 text-xs text-slate-500">Choose this month or a future month. Saving again updates that month's target.</p>
                    <form method="POST" action="{{ route('goals.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="goal_type" value="{{ $monthlyType }}">
                        <div>
                            <label for="monthly-goal-month" class="block text-sm font-bold">Month</label>
                            <input id="monthly-goal-month" name="month" type="month" min="{{ substr($currentMonth, 0, 7) }}" value="{{ old('goal_type') === $monthlyType ? old('month', substr($currentMonth, 0, 7)) : substr($currentMonth, 0, 7) }}" required class="gt-focus mt-2 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                            @error('month') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="monthly-goal-target" class="block text-sm font-bold">Target ({{ strtoupper($business->currency ?: 'PHP') }})</label>
                            <input id="monthly-goal-target" name="target_amount" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" value="{{ old('goal_type') === $monthlyType ? old('target_amount', $currentMonthGoal['target_amount'] ?? '') : ($currentMonthGoal['target_amount'] ?? '') }}" required placeholder="e.g. 50000.00" class="gt-focus mt-2 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                            @if(old('goal_type', $monthlyType) === $monthlyType)
                                @error('target_amount') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                            @endif
                        </div>
                        <button type="submit" class="gt-focus w-full rounded-xl bg-[#00A87E] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#08745D]">Save monthly target</button>
                    </form>
                </article>

                <article class="gt-card gt-enter p-5 sm:p-6" aria-labelledby="set-weekly-goal-heading">
                    <h3 id="set-weekly-goal-heading" class="text-lg font-extrabold">Set a weekly target</h3>
                    <p class="mt-1 text-xs text-slate-500">Weeks run Monday through Sunday in your shop's timezone. Saving again updates that week's target.</p>
                    <form method="POST" action="{{ route('goals.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="goal_type" value="{{ $weeklyType }}">
                        <div>
                            <label for="weekly-goal-date" class="block text-sm font-bold">Choose a day in the week</label>
                            <input id="weekly-goal-date" name="week_date" type="date" min="{{ $currentWeekStart }}" value="{{ old('goal_type') === $weeklyType ? old('week_date', $currentWeekStart) : $currentWeekStart }}" required class="gt-focus mt-2 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Selected date will be grouped into its Monday-Sunday week.</p>
                            @error('week_date') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="weekly-goal-target" class="block text-sm font-bold">Target ({{ strtoupper($business->currency ?: 'PHP') }})</label>
                            <input id="weekly-goal-target" name="target_amount" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" value="{{ old('goal_type') === $weeklyType ? old('target_amount', $currentWeekGoal['target_amount'] ?? '') : ($currentWeekGoal['target_amount'] ?? '') }}" required placeholder="e.g. 12000.00" class="gt-focus mt-2 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                            @if(old('goal_type') === $weeklyType)
                                @error('target_amount') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                            @endif
                        </div>
                        <button type="submit" class="gt-focus w-full rounded-xl bg-[#00A87E] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#08745D]">Save weekly target</button>
                    </form>
                </article>
            </section>

            <section class="gt-card gt-enter overflow-hidden" aria-labelledby="goal-history-heading">
                <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6"><h3 id="goal-history-heading" class="text-lg font-extrabold">Sales target history</h3></div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($goals as $goal)
                        @php
                            $weekly = $goal['goal_type'] === $weeklyType;
                            $periodStart = \Carbon\CarbonImmutable::parse($goal['period_start']);
                            $periodEnd = \Carbon\CarbonImmutable::parse($goal['period_end']);
                            $periodLabel = $weekly
                                ? $periodStart->format('M j').' - '.$periodEnd->format('M j, Y')
                                : $periodStart->format('F Y');
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                            <div>
                                <p class="text-sm font-extrabold">{{ $weekly ? 'Weekly' : 'Monthly' }} target <span class="font-semibold text-slate-500">{{ $periodLabel }}</span></p>
                                <p class="mt-1 text-xs text-slate-500">{{ $goal['target_reached'] ? 'Target reached' : number_format($goal['progress_percentage'], 1).'% of target' }}</p>
                            </div>
                            <p class="text-sm font-bold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $goal['current_amount'], 2) }} <span class="font-medium text-slate-500">/ {{ $currencyPrefix }}{{ number_format((float) $goal['target_amount'], 2) }}</span></p>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-500">Weekly and monthly targets will appear here after you save them.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
