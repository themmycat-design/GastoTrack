@extends('layouts.super-admin')

@section('title', 'Platform Overview')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold">Platform overview</h1>
    <p class="mt-1 text-slate-600">Monitor registrations and business account health.</p>
</div>

<div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Total businesses', $totalBusinesses, 'fa-building', 'bg-emerald-100 text-emerald-700'],
        ['Pending approval', $pendingBusinesses, 'fa-clock', 'bg-amber-100 text-amber-700'],
        ['Active businesses', $activeBusinesses, 'fa-circle-check', 'bg-blue-100 text-blue-700'],
        ['Suspended', $suspendedBusinesses, 'fa-ban', 'bg-red-100 text-red-700'],
    ] as [$label, $value, $icon, $color])
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg {{ $color }}"><i class="fas {{ $icon }}"></i></div>
            <p class="text-3xl font-bold">{{ $value }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $label }}</p>
        </div>
    @endforeach
</div>

<section class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-200 p-5">
        <div><h2 class="text-lg font-bold">Recent registrations</h2><p class="text-sm text-slate-500">Latest businesses joining GastoTrack</p></div>
        <a href="{{ route('super-admin.businesses') }}" class="font-semibold text-emerald-700">View all &rarr;</a>
    </div>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Business</th><th class="p-4">Owner</th><th class="p-4">Location</th><th class="p-4">Staff</th><th class="p-4">Status</th><th class="p-4">Registered</th><th class="p-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($recentBusinesses as $business)
            <tr class="hover:bg-slate-50">
                <td class="p-4 font-semibold">{{ $business->name }}</td>
                <td class="p-4"><p>{{ $business->owner_account?->name ?? 'No owner assigned' }}</p><p class="text-xs text-slate-500">{{ $business->owner_account?->email }}</p></td>
                <td class="p-4 text-slate-600">{{ collect([$business->city, $business->province])->filter()->join(', ') ?: 'Not provided' }}</td>
                <td class="p-4">{{ $business->staff_count }}</td>
                <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $business->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($business->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $business->status }}</span></td>
                <td class="p-4 text-slate-600">{{ $business->created_at->format('M d, Y') }}</td>
                <td class="p-4"><a href="{{ route('super-admin.businesses.show', $business) }}" class="font-semibold text-emerald-700">View</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-10 text-center text-slate-500">No businesses registered yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
