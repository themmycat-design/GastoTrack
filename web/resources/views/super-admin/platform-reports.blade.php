@extends('layouts.super-admin')

@section('title', 'Platform Reports')

@section('content')
@php
    $trendMonths = collect(range(5, 0))->map(function ($monthsAgo) use ($registrationTrend) {
        $date = now()->subMonths($monthsAgo);
        $key = $date->format('Y-m');

        return ['key' => $key, 'label' => $date->format('M'), 'count' => (int) $registrationTrend->get($key, 0)];
    });
    $trendMax = max(1, (int) $trendMonths->max('count'));
    $trendPoints = $trendMonths->values()->map(function ($month, $index) use ($trendMax) {
        return [
            'x' => 32 + ($index * 100),
            'y' => 164 - (int) round(($month['count'] / $trendMax) * 124),
            'label' => $month['label'],
            'count' => $month['count'],
        ];
    });
    $trendPolyline = $trendPoints->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
@endphp

<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="mb-1 text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Platform insights</p>
        <h1>Platform reports</h1>
        <p class="mt-2 text-sm text-slate-600">Registration and account health across GastoTrack.</p>
    </div>
    <a href="{{ route('super-admin.reports.export') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700">
        <i class="fas fa-download mr-2" aria-hidden="true"></i>Export CSV
    </a>
</div>

<div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
    @foreach([
        ['Businesses', $summary['businesses'], 'fa-building', 'bg-emerald-100 text-emerald-700'],
        ['Staff accounts', $summary['staff'], 'fa-users', 'bg-sky-100 text-sky-700'],
        ['New this month', $summary['new_this_month'], 'fa-user-plus', 'bg-violet-100 text-violet-700'],
        ['Open bugs', $summary['open_bugs'], 'fa-bug', 'bg-amber-100 text-amber-700'],
    ] as [$label, $value, $icon, $color])
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <p class="text-sm font-semibold text-slate-600">{{ $label }}</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $color }}"><i class="fas {{ $icon }}" aria-hidden="true"></i></span>
            </div>
            <p class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format((int) $value) }}</p>
        </article>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="text-lg font-extrabold">Businesses by status</h2><p class="mt-1 text-sm text-slate-500">Status totals and registered business names.</p></div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ number_format((int) $statusCounts->sum()) }} total</span>
        </div>
        <div class="mt-5 space-y-3">
            @forelse($statusCounts->sortKeys() as $status => $total)
                @php
                    $statusStyle = match ($status) {
                        'active' => 'bg-emerald-50 text-emerald-800',
                        'pending' => 'bg-amber-50 text-amber-800',
                        'suspended' => 'bg-rose-50 text-rose-800',
                        default => 'bg-slate-100 text-slate-700',
                    };
                    $statusBusinesses = $businessesByStatus->get($status, collect());
                @endphp
                <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold capitalize {{ $statusStyle }}">{{ str_replace('_', ' ', $status) }}</span>
                        <span class="text-lg font-extrabold text-slate-900">{{ number_format((int) $total) }}</span>
                    </div>
                    @if($statusBusinesses->isNotEmpty())
                        <ul class="mt-3 space-y-2 border-t border-slate-200 pt-3 text-sm text-slate-700">
                            @foreach($statusBusinesses->take(5) as $platformBusiness)
                                <li class="flex items-center gap-2"><i class="fas fa-store text-xs text-slate-400" aria-hidden="true"></i><span class="min-w-0 truncate">{{ $platformBusiness->name }}</span></li>
                            @endforeach
                        </ul>
                        @if((int) $total > $statusBusinesses->count())
                            <p class="mt-2 text-xs text-slate-500">And {{ number_format((int) $total - $statusBusinesses->count()) }} more</p>
                        @endif
                    @else
                        <p class="mt-3 border-t border-slate-200 pt-3 text-sm text-slate-500">No businesses in this status.</p>
                    @endif
                    <a href="{{ route('super-admin.businesses', ['status' => $status]) }}" class="mt-3 inline-flex text-xs font-bold text-emerald-700 hover:text-emerald-900">View businesses <span class="ml-1" aria-hidden="true">&rarr;</span></a>
                </div>
            @empty
                <p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">No business status data available.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="text-lg font-extrabold">Registration trend</h2><p class="mt-1 text-sm text-slate-500">New business registrations over the last six months.</p></div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800">{{ number_format((int) $trendMonths->sum('count')) }} registrations</span>
        </div>
        <div class="mt-5 overflow-hidden rounded-xl bg-slate-50 px-2 pt-4 sm:px-4">
            <svg viewBox="0 0 572 205" role="img" aria-label="Line graph of monthly business registrations" class="h-auto w-full">
                <line x1="24" y1="40" x2="548" y2="40" stroke="#DDE9E5" stroke-dasharray="4 5" />
                <line x1="24" y1="102" x2="548" y2="102" stroke="#DDE9E5" stroke-dasharray="4 5" />
                <line x1="24" y1="164" x2="548" y2="164" stroke="#CBD9D4" />
                <polyline points="{{ $trendPolyline }}" fill="none" stroke="#00A87E" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                @foreach($trendPoints as $point)
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#fff" stroke="#00A87E" stroke-width="3"><title>{{ $point['label'] }}: {{ $point['count'] }} registrations</title></circle>
                    <text x="{{ $point['x'] }}" y="190" text-anchor="middle" fill="#607570" font-size="12">{{ $point['label'] }}</text>
                @endforeach
            </svg>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach($trendMonths as $month)
                <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-xs"><span class="text-slate-500">{{ \Carbon\Carbon::createFromFormat('Y-m', $month['key'])->format('M Y') }}</span><span class="font-bold text-slate-800">{{ number_format($month['count']) }}</span></div>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div><h2 class="text-lg font-extrabold">Businesses by type</h2><p class="mt-1 text-sm text-slate-500">A vertical breakdown of registered business categories.</p></div>
        <div class="mt-5 space-y-3">
            @forelse($typeCounts as $type => $total)
                <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3">
                    <span class="font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $type ?: 'Not specified') }}</span>
                    <span class="rounded-full bg-white px-3 py-1 text-sm font-extrabold text-slate-800 shadow-sm">{{ number_format((int) $total) }}</span>
                </div>
            @empty
                <p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">No business type data available.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div><h2 class="text-lg font-extrabold">Top registered locations</h2><p class="mt-1 text-sm text-slate-500">Locations with the most registered businesses.</p></div>
        <ol class="mt-5 space-y-3">
            @forelse($locations as $location)
                <li class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-xs font-extrabold text-emerald-800">{{ $loop->iteration }}</span>
                    <span class="min-w-0 flex-1 truncate font-medium text-slate-700">{{ collect([$location->city, $location->province])->filter()->join(', ') }}</span>
                    <span class="rounded-full bg-white px-3 py-1 text-sm font-extrabold text-slate-800 shadow-sm">{{ number_format((int) $location->total) }}</span>
                </li>
            @empty
                <li class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">No location data available.</li>
            @endforelse
        </ol>
    </section>
</div>

<div class="mt-6 flex items-start gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
    <i class="fas fa-shield-halved mt-0.5 text-blue-700" aria-hidden="true"></i>
    <p><strong>Privacy boundary:</strong> these reports contain platform registration metadata, account statuses, staff counts, and bug-report information. They exclude sales, expenses, transactions, stock, products, and goals.</p>
</div>
@endsection
