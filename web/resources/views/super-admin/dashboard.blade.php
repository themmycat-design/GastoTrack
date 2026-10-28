@extends('layouts.super-admin')

@section('title', 'Platform Overview')

@section('content')
<div class="mb-7">
    <p class="mb-1 text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Control center</p>
    <h1>Platform overview</h1>
    <p class="mt-2 text-sm text-slate-600">Monitor registrations and business account health.</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
    @foreach([
        ['Total businesses', $totalBusinesses, 'fa-building', 'bg-emerald-100 text-emerald-700'],
        ['Pending approval', $pendingBusinesses, 'fa-clock', 'bg-amber-100 text-amber-700'],
        ['Active businesses', $activeBusinesses, 'fa-circle-check', 'bg-blue-100 text-blue-700'],
        ['Suspended', $suspendedBusinesses, 'fa-ban', 'bg-red-100 text-red-700'],
    ] as [$label, $value, $icon, $color])
        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex items-start justify-between gap-4"><p class="text-sm font-semibold text-slate-600">{{ $label }}</p><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $color }}"><i class="fas {{ $icon }}" aria-hidden="true"></i></span></div>
            <p class="text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format((int) $value) }}</p>
            <div class="mt-4 h-1 w-12 rounded-full bg-emerald-500/70"></div>
        </div>
    @endforeach
</div>

<section class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5 sm:p-6">
        <div><h2 class="text-lg font-extrabold">Recent registrations</h2><p class="mt-1 text-sm text-slate-500">Latest businesses joining GastoTrack</p></div>
        <a href="{{ route('super-admin.businesses') }}" class="inline-flex min-h-11 items-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50">View all <span class="ml-2" aria-hidden="true">→</span></a>
    </div>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Business</th><th class="p-4">Owner</th><th class="p-4">Location</th><th class="p-4">Staff</th><th class="p-4">Status</th><th class="p-4">Registered</th><th class="p-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($recentBusinesses as $business)
            <tr class="hover:bg-slate-50">
                <td class="p-4 font-semibold">{{ $business->name }}</td>
                <td class="p-4">
                    @if($business->verified_owner_account)
                        <p>{{ $business->verified_owner_account->name }}</p><p class="text-xs text-slate-500">{{ $business->verified_owner_account->email }}</p>
                    @elseif($business->owner_email_verification_pending)
                        <span class="text-amber-700">Email verification pending</span>
                    @else
                        <span class="text-slate-500">No owner assigned</span>
                    @endif
                </td>
                <td class="p-4 text-slate-600">{{ collect([$business->city, $business->province])->filter()->join(', ') ?: 'Not provided' }}</td>
                <td class="p-4">{{ $business->staff_count }}</td>
                <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $business->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($business->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $business->status }}</span></td>
                <td class="p-4 text-slate-600">{{ $business->created_at->format('M d, Y') }}</td>
                <td class="p-4"><a href="{{ route('super-admin.businesses.show', $business) }}" class="inline-flex min-h-11 items-center rounded-lg px-2 font-semibold text-emerald-700 hover:bg-emerald-50">View</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-10 text-center text-slate-500">No businesses registered yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
