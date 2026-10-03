@extends('layouts.super-admin')
@section('title', 'Platform Reports')
@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-6">
    <div><h1 class="text-3xl font-bold">Platform reports</h1><p class="mt-1 text-slate-600">Registration and account-health reporting without private business data.</p></div>
    <a href="{{ route('super-admin.reports.export') }}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white"><i class="fas fa-download mr-2"></i>Export CSV</a>
</div>
<div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([['Businesses', $summary['businesses']], ['Staff accounts', $summary['staff']], ['New this month', $summary['new_this_month']], ['Open violations', $summary['open_violations']]] as [$label, $value])
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-3xl font-bold">{{ $value }}</p><p class="mt-1 text-sm text-slate-600">{{ $label }}</p></div>
    @endforeach
</div>
<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold">Businesses by status</h2><div class="mt-5 space-y-4">@forelse($statusCounts as $status => $total)<div class="flex items-center justify-between"><span class="capitalize text-slate-700">{{ $status }}</span><span class="rounded-full bg-slate-100 px-3 py-1 font-semibold">{{ $total }}</span></div>@empty<p class="text-slate-500">No data available.</p>@endforelse</div></section>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold">Businesses by type</h2><div class="mt-5 space-y-4">@forelse($typeCounts as $type => $total)<div class="flex items-center justify-between"><span class="capitalize text-slate-700">{{ $type }}</span><span class="rounded-full bg-slate-100 px-3 py-1 font-semibold">{{ $total }}</span></div>@empty<p class="text-slate-500">No data available.</p>@endforelse</div></section>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold">Registration trend</h2><div class="mt-5 space-y-4">@forelse($registrationTrend as $month => $total)<div class="flex items-center justify-between"><span>{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</span><span class="font-bold text-emerald-700">{{ $total }}</span></div>@empty<p class="text-slate-500">No recent registrations.</p>@endforelse</div></section>
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold">Top registered locations</h2><div class="mt-5 space-y-4">@forelse($locations as $location)<div class="flex items-center justify-between"><span>{{ collect([$location->city, $location->province])->filter()->join(', ') }}</span><span class="font-bold text-emerald-700">{{ $location->total }}</span></div>@empty<p class="text-slate-500">No location data available.</p>@endforelse</div></section>
</div>
<div class="mt-8 rounded-xl border border-blue-200 bg-blue-50 p-6 text-sm text-blue-900"><strong>Privacy boundary:</strong> these reports contain only platform registration metadata, account statuses, staff counts, and violation-management information. They exclude sales, expenses, transactions, stock, products, and goals.</div>
@endsection
