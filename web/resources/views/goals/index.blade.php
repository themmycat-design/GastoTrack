<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sales goals | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
@php $currencyPrefix = ($business->currency ?: 'PHP') === 'PHP' ? '₱' : strtoupper($business->currency).' '; @endphp
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <section class="gt-enter">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">Owner workspace</p>
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Monthly sales goals</h2>
                <p class="mt-2 text-sm text-slate-500">Set a target. Completed orders with confirmed payment move the progress automatically.</p>
            </section>

            @if(session('status'))
                <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>
            @endif

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,1fr)]">
                <section class="gt-card gt-enter overflow-hidden p-5 sm:p-6" aria-labelledby="goal-progress-heading">
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">{{ \Carbon\CarbonImmutable::parse($currentMonth)->format('F Y') }}</p>
                    <h3 id="goal-progress-heading" class="mt-2 text-lg font-extrabold">Current month</h3>
                    @if($currentGoal)
                        <p class="mt-5 text-3xl font-extrabold tracking-tight text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $currentGoal['current_amount'], 2) }} <span class="text-base font-medium text-slate-500">of {{ $currencyPrefix }}{{ number_format((float) $currentGoal['target_amount'], 2) }}</span></p>
                        <div class="mt-5 h-3 overflow-hidden rounded-full bg-[#EAF2EF]" role="progressbar" aria-label="Monthly sales goal progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $currentGoal['progress_percentage'] }}">
                            <div class="h-full rounded-full bg-[#00C897]" style="width: {{ $currentGoal['progress_percentage'] }}%"></div>
                        </div>
                        <p class="mt-3 text-sm font-semibold {{ $currentGoal['target_reached'] ? 'text-[#08745D]' : 'text-slate-600' }}">{{ $currentGoal['target_reached'] ? 'Target reached' : number_format($currentGoal['progress_percentage'], 1).'% of target' }}</p>
                    @else
                        <p class="mt-5 text-sm text-slate-500">No sales target is set for this month yet.</p>
                    @endif
                    <p class="mt-5 text-xs leading-5 text-slate-500">Achieving Sales on this month</p>
                </section>

                <section class="gt-card gt-enter p-5 sm:p-6" aria-labelledby="set-goal-heading">
                    <h3 id="set-goal-heading" class="text-lg font-extrabold">Set a monthly target</h3>
                    <p class="mt-1 text-xs text-slate-500">You can set this month or a future month. Saving the same month updates its target.</p>
                    <form method="POST" action="{{ route('goals.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="goal-month" class="block text-sm font-bold">Month</label>
                            <input id="goal-month" name="month" type="month" min="{{ substr($currentMonth, 0, 7) }}" value="{{ old('month', substr($currentMonth, 0, 7)) }}" required class="gt-focus mt-2 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                            @error('month') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="goal-target" class="block text-sm font-bold">Target ({{ strtoupper($business->currency ?: 'PHP') }})</label>
                            <input id="goal-target" name="target_amount" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" value="{{ old('target_amount', $currentGoal['target_amount'] ?? '') }}" required placeholder="e.g. 50000.00" class="gt-focus mt-2 w-full rounded-xl border border-[#DDE9E5] bg-white px-3 py-2.5 text-sm">
                            @error('target_amount') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="gt-focus w-full rounded-xl bg-[#00A87E] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#08745D]">Save target</button>
                    </form>
                </section>
            </div>

            <section class="gt-card gt-enter overflow-hidden" aria-labelledby="goal-history-heading">
                <div class="border-b border-[#EAF2EF] px-5 py-4 sm:px-6"><h3 id="goal-history-heading" class="text-lg font-extrabold">Monthly targets</h3></div>
                <div class="divide-y divide-[#EAF2EF]">
                    @forelse($goals as $goal)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                            <div>
                                <p class="text-sm font-extrabold">{{ \Carbon\CarbonImmutable::parse($goal['period_start'])->format('F Y') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $goal['target_reached'] ? 'Target reached' : number_format($goal['progress_percentage'], 1).'% of target' }}</p>
                            </div>
                            <p class="text-sm font-bold text-[#08745D]">{{ $currencyPrefix }}{{ number_format((float) $goal['current_amount'], 2) }} <span class="font-medium text-slate-500">/ {{ $currencyPrefix }}{{ number_format((float) $goal['target_amount'], 2) }}</span></p>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-500">Monthly targets will appear here after you save the first one.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
