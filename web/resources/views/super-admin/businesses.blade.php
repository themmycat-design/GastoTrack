@extends('layouts.super-admin')

@section('title', 'Businesses')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-6">
    <div><h1 class="text-3xl font-bold">Businesses</h1><p class="mt-1 text-slate-600">Review registrations and manage platform access.</p></div>
    <a href="{{ route('super-admin.businesses.create') }}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700"><i class="fas fa-plus mr-2"></i>Add business</a>
</div>

<div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200">
    @foreach([
        [null, 'All', $allCount],
        ['pending', 'Pending', $pendingCount],
        ['active', 'Active', $activeCount],
        ['suspended', 'Suspended', $suspendedCount],
        ['inactive', 'Inactive', $inactiveCount],
    ] as [$status, $label, $count])
        <a href="{{ $status ? route('super-admin.businesses', ['status' => $status]) : route('super-admin.businesses') }}" class="whitespace-nowrap px-4 py-3 text-sm font-medium {{ request('status') === $status && !request()->boolean('archived') ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-slate-600 hover:text-slate-900' }}">{{ $label }} ({{ $count }})</a>
    @endforeach
    <a href="{{ route('super-admin.businesses', ['archived' => 1]) }}" class="whitespace-nowrap px-4 py-3 text-sm font-medium {{ request()->boolean('archived') ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-slate-600 hover:text-slate-900' }}">Archived ({{ $archivedCount }})</a>
</div>

<form method="GET" class="mb-6 flex gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    @if(request()->boolean('archived'))<input type="hidden" name="archived" value="1">@endif
    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search business, email, or owner" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-4 py-2 focus:border-emerald-500 focus:outline-none">
    <button class="rounded-lg bg-slate-900 px-5 py-2 font-medium text-white">Search</button>
</form>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Business</th><th class="p-4">Owner</th><th class="p-4">Contact</th><th class="p-4">Location</th><th class="p-4">Staff</th><th class="p-4">Status</th><th class="p-4">Registered</th><th class="p-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($businesses as $business)
            <tr class="hover:bg-slate-50">
                <td class="p-4"><p class="font-semibold">{{ $business->name }}</p><p class="text-xs capitalize text-slate-500">{{ $business->business_type }}</p></td>
                <td class="p-4"><p>{{ $business->owner_account?->name ?? 'No owner assigned' }}</p><p class="text-xs text-slate-500">{{ $business->owner_account?->email }}</p></td>
                <td class="p-4"><p>{{ $business->phone ?: '—' }}</p><p class="text-xs text-slate-500">{{ $business->email }}</p></td>
                <td class="p-4 text-slate-600">{{ collect([$business->city, $business->province])->filter()->join(', ') ?: 'Not provided' }}</td>
                <td class="p-4">{{ $business->staff_count }}</td>
                <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $business->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($business->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $business->trashed() ? 'archived' : $business->status }}</span></td>
                <td class="p-4 text-slate-600">{{ $business->created_at->format('M d, Y') }}</td>
                <td class="p-4">
                    @if($business->trashed())
                        <form method="POST" action="{{ route('super-admin.businesses.restore', $business->id) }}">@csrf<button class="font-semibold text-emerald-700"><i class="fas fa-rotate-left mr-1"></i>Restore</button></form>
                    @else
                        <a href="{{ route('super-admin.businesses.show', $business) }}" class="font-semibold text-emerald-700">View</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="p-10 text-center text-slate-500">No businesses found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($businesses->hasPages())<div class="border-t border-slate-200 p-4">{{ $businesses->links() }}</div>@endif
</section>
@endsection
