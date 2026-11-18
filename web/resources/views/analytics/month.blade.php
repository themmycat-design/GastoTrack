<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>{{ $month->format('F Y') }} | Analytics | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
@php($currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode.' ')
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <header class="gt-enter flex flex-wrap items-end justify-between gap-4">
                <div>
                    <a href="{{ route('analytics.index', ['period' => 'year']) }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">← Back to Yearly analytics</a>
                    <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $month->format('F Y') }}</h1>
                    <p class="mt-2 text-sm text-slate-500">Daily recorded income and expenses for this month. Select a day to view its transactions, orders, and ingredients used.</p>
                </div>
                <nav class="flex flex-wrap gap-2 text-sm font-bold" aria-label="Browse months">
                    <a href="{{ route('analytics.month', ['month' => $month->subMonth()->format('Y-m')]) }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-[#08745D] hover:bg-[#EAF8F2]">← Previous month</a>
                    @if($month->addMonth()->lte($today))
                        <a href="{{ route('analytics.month', ['month' => $month->addMonth()->format('Y-m')]) }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-4 py-2.5 text-[#08745D] hover:bg-[#EAF8F2]">Next month →</a>
                    @endif
                </nav>
            </header>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Monthly totals">
                <article class="gt-card gt-enter p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-slate-500">Recorded income</h2>
                    <p class="mt-2 text-2xl font-extrabold text-[#08745D]"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $totals['income'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $totals['income'], 2) }}</span></p>
                </article>
                <article class="gt-card gt-enter gt-enter-2 p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-slate-500">Recorded expenses</h2>
                    <p class="mt-2 text-2xl font-extrabold text-rose-600"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $totals['expenses'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $totals['expenses'], 2) }}</span></p>
                </article>
                <article class="gt-card gt-enter gt-enter-3 p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-slate-500">Net recorded movement</h2>
                    <p class="mt-2 text-2xl font-extrabold {{ $totals['netRecordedMovement'] < 0 ? 'text-rose-600' : 'text-[#08745D]' }}"><span data-owner-motion-child data-owner-count-up data-owner-count-up-value="{{ (float) $totals['netRecordedMovement'] }}" data-owner-count-up-prefix="{{ $currencyPrefix }}" data-owner-count-up-decimals="2">{{ $currencyPrefix }}{{ number_format((float) $totals['netRecordedMovement'], 2) }}</span></p>
                    <p class="mt-2 text-xs text-slate-500">Income minus expenses; not accounting profit.</p>
                </article>
            </section>

            <section class="gt-card gt-enter overflow-hidden" aria-labelledby="daily-breakdown-heading">
                <div class="border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
                    <h2 id="daily-breakdown-heading" class="text-lg font-extrabold">Days in {{ $month->format('F') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $chart['dates'][0] }} to {{ $chart['dates'][count($chart['dates']) - 1] }} · {{ $currencyCode }}</p>
                </div>
                <div class="divide-y divide-[#EAF2EF]">
                    @foreach($chart['dates'] as $index => $date)
                        <a href="{{ route('analytics.day', ['date' => $date, 'from_month' => $month->format('Y-m')]) }}" class="gt-focus grid gap-2 px-5 py-4 transition hover:bg-[#F4F8F7] sm:grid-cols-[minmax(140px,1fr)_repeat(3,minmax(100px,auto))] sm:items-center sm:gap-4 sm:px-6" aria-label="View details for {{ \Carbon\CarbonImmutable::parse($date)->format('F j, Y') }}">
                            <span class="font-bold text-[#0A2E2A]">{{ \Carbon\CarbonImmutable::parse($date)->format('D, M j') }} <span class="text-[#08745D]" aria-hidden="true">→</span></span>
                            <span class="text-sm text-[#08745D]">Income <strong>{{ $currencyPrefix }}{{ number_format((float) $chart['income'][$index], 2) }}</strong></span>
                            <span class="text-sm text-rose-600">Expenses <strong>{{ $currencyPrefix }}{{ number_format((float) $chart['expenses'][$index], 2) }}</strong></span>
                            <span class="text-sm text-slate-600">Net <strong>{{ $currencyPrefix }}{{ number_format((float) $chart['income'][$index] - (float) $chart['expenses'][$index], 2) }}</strong></span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
