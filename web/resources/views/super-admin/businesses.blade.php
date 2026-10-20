@extends('layouts.super-admin')

@section('title', 'Businesses')

@section('content')
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div><p class="mb-1 text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Platform directory</p><h1>Businesses</h1><p class="mt-2 text-sm text-slate-600">Review registrations and manage platform access.</p></div>
    <a href="{{ route('super-admin.businesses.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"><i class="fas fa-plus mr-2"></i>Add business</a>
</div>

<nav aria-label="Filter businesses by status" class="mb-5 flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
    @foreach([
        [null, 'All', $allCount],
        ['pending', 'Pending', $pendingCount],
        ['active', 'Active', $activeCount],
        ['suspended', 'Suspended', $suspendedCount],
        ['inactive', 'Inactive', $inactiveCount],
    ] as [$status, $label, $count])
        <a href="{{ $status ? route('super-admin.businesses', ['status' => $status]) : route('super-admin.businesses') }}" aria-current="{{ request('status') === $status && !request()->boolean('archived') ? 'page' : 'false' }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request('status') === $status && !request()->boolean('archived') ? 'bg-[#EAF8F2] text-[#08745D]' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"><span>{{ $label }}</span><span class="rounded-full bg-white/80 px-2 py-0.5 text-xs text-slate-500">{{ $count }}</span></a>
    @endforeach
    <a href="{{ route('super-admin.businesses', ['archived' => 1]) }}" aria-current="{{ request()->boolean('archived') ? 'page' : 'false' }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->boolean('archived') ? 'bg-[#EAF8F2] text-[#08745D]' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"><span>Archived</span><span class="rounded-full bg-white/80 px-2 py-0.5 text-xs text-slate-500">{{ $archivedCount }}</span></a>
</nav>

<form method="GET" role="search" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    @if(request()->boolean('archived'))<input type="hidden" name="archived" value="1">@endif
    <label class="sr-only" for="business-search">Search businesses</label>
    <input id="business-search" type="search" name="search" value="{{ request('search') }}" placeholder="Search business, email, or owner" class="min-w-0 rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white"><i class="fas fa-magnifying-glass mr-2" aria-hidden="true"></i>Search</button>
</form>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Business</th><th class="p-4">Owner</th><th class="p-4">Contact</th><th class="p-4">Location</th><th class="p-4">Staff</th><th class="p-4">Status</th><th class="p-4">Registered</th><th class="p-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($businesses as $business)
            <tr class="hover:bg-slate-50">
                <td class="p-4"><p class="font-semibold">{{ $business->name }}</p><p class="text-xs capitalize text-slate-500">{{ $business->business_type }}</p></td>
                <td class="p-4">
                    @if($business->verified_owner_account)
                        <p>{{ $business->verified_owner_account->name }}</p><p class="text-xs text-slate-500">{{ $business->verified_owner_account->email }}</p>
                    @elseif($business->owner_email_verification_pending)
                        <span class="text-amber-700">Email verification pending</span>
                    @else
                        <span class="text-slate-500">No owner assigned</span>
                    @endif
                </td>
                <td class="p-4">
                    @if($business->owner_email_verification_pending)
                        <span class="text-slate-500">Contact hidden until verification</span>
                    @else
                        <p>{{ $business->phone ?: '—' }}</p><p class="text-xs text-slate-500">{{ $business->email }}</p>
                    @endif
                </td>
                <td class="p-4 text-slate-600">{{ collect([$business->city, $business->province])->filter()->join(', ') ?: 'Not provided' }}</td>
                <td class="p-4">{{ $business->staff_count }}</td>
                <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $business->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($business->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $business->trashed() ? 'archived' : $business->status }}</span></td>
                <td class="p-4 text-slate-600">{{ $business->created_at->format('M d, Y') }}</td>
                <td class="p-4">
                    @if($business->trashed())
                        <div class="flex items-center gap-3">
                            <form method="POST" action="{{ route('super-admin.businesses.restore', $business->id) }}">@csrf<button class="font-semibold text-emerald-700"><i class="fas fa-rotate-left mr-1"></i>Restore</button></form>
                            <form method="POST" action="{{ route('super-admin.businesses.force-destroy', $business->id) }}" onsubmit="return confirm('Permanently delete this archived business and all associated business records? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-semibold text-red-700 hover:text-red-900">Delete permanently</button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <a href="{{ route('super-admin.businesses.show', $business) }}" class="font-semibold text-emerald-700">View</a>
                            <form method="POST" action="{{ route('super-admin.businesses.destroy', $business) }}" onsubmit="return confirm('Delete this business? It will be archived, access disabled, and its records preserved.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-semibold text-red-700 hover:text-red-900">Delete</button>
                            </form>
                        </div>
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
