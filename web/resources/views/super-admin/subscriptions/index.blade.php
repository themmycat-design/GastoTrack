@extends('layouts.super-admin')

@section('title', 'Subscription requests')

@section('content')
<header class="mb-6">
    <p class="text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Plan review</p>
    <h1 class="mt-1">Subscription requests</h1>
    <p class="mt-2 text-sm text-slate-600">Check submitted payment proof before activating a paid plan.</p>
</header>

<nav aria-label="Filter subscription requests" class="mb-5 flex flex-wrap gap-2">
    @foreach(['pending' => 'Pending', 'accepted' => 'Accepted', 'all' => 'All'] as $key => $label)
        <a href="{{ route('super-admin.subscription-requests.index', ['status' => $key]) }}" aria-current="{{ $status === $key ? 'page' : 'false' }}" class="inline-flex min-h-10 items-center rounded-xl border px-4 text-sm font-bold {{ $status === $key ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            {{ $label }} @if($key !== 'all')<span class="ml-2 text-xs">{{ $counts[$key] }}</span>@endif
        </a>
    @endforeach
</nav>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-extrabold">Requests</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="p-4">Business</th><th class="p-4">Plan</th><th class="p-4">Payment</th><th class="p-4">Submitted</th><th class="p-4">Status</th><th class="p-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($requests as $subscriptionRequest)
                    <tr>
                        <td class="p-4"><p class="font-bold text-slate-900">{{ $subscriptionRequest->business?->name ?? 'Deleted business' }}</p><p class="text-xs text-slate-500">{{ $subscriptionRequest->requester?->name ?? 'Former owner' }}</p></td>
                        <td class="p-4 font-semibold capitalize">{{ $subscriptionRequest->plan_tier }}</td>
                        <td class="p-4 capitalize">{{ str_replace('_', ' ', $subscriptionRequest->payment_method) }}</td>
                        <td class="whitespace-nowrap p-4 text-slate-600">{{ $subscriptionRequest->created_at->timezone('Asia/Manila')->format('M d, Y') }}</td>
                        <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $subscriptionRequest->status === 'accepted' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">{{ ucfirst($subscriptionRequest->status) }}</span></td>
                        <td class="p-4"><div class="flex items-center gap-2"><a href="{{ route('super-admin.subscription-requests.show', $subscriptionRequest) }}" class="rounded-lg px-3 py-2 font-bold text-emerald-700 hover:bg-emerald-50">View</a><form method="POST" action="{{ route('super-admin.subscription-requests.destroy', $subscriptionRequest) }}" onsubmit="return confirm('Delete this request and its payment proof? Any active plan will remain unchanged.');">@csrf @method('DELETE')<button class="rounded-lg px-3 py-2 font-bold text-rose-700 hover:bg-rose-50">Delete</button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-10 text-center text-slate-600">No subscription requests in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())<div class="border-t border-slate-200 p-4">{{ $requests->links() }}</div>@endif
</section>
@endsection
