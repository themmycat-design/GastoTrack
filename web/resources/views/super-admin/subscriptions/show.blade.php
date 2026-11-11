@extends('layouts.super-admin')

@section('title', 'Subscription request')

@section('content')
<div class="mb-6">
    <a href="{{ route('super-admin.subscription-requests.index') }}" class="text-sm font-bold text-emerald-700">&larr; Subscription requests</a>
    <h1 class="mt-3">Subscription request</h1>
    <p class="mt-2 text-sm text-slate-600">Review the submitted information and proof before accepting.</p>
</div>

<div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,.8fr)]">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-extrabold">Submitted details</h2>
        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-slate-500">Business</dt><dd class="mt-1 font-bold">{{ $subscriptionRequest->business?->name ?? 'Deleted business' }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Owner</dt><dd class="mt-1 font-bold">{{ $subscriptionRequest->requester?->name ?? 'Former owner' }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Preferred plan</dt><dd class="mt-1 font-bold capitalize">{{ $subscriptionRequest->plan_tier }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Payment method</dt><dd class="mt-1 font-bold capitalize">{{ str_replace('_', ' ', $subscriptionRequest->payment_method) }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Payment reference</dt><dd class="mt-1">{{ $subscriptionRequest->payment_reference ?: 'Not provided' }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Submitted</dt><dd class="mt-1">{{ $subscriptionRequest->created_at->timezone('Asia/Manila')->format('M d, Y, g:i A') }}</dd></div>
            <div class="sm:col-span-2"><dt class="font-semibold text-slate-500">Owner note</dt><dd class="mt-1 whitespace-pre-wrap">{{ $subscriptionRequest->owner_note ?: 'No note provided' }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Request status</dt><dd class="mt-1 font-bold capitalize">{{ $subscriptionRequest->status }}</dd></div>
            @if($subscriptionRequest->reviewed_at)<div><dt class="font-semibold text-slate-500">Reviewed</dt><dd class="mt-1">{{ $subscriptionRequest->reviewed_at->timezone('Asia/Manila')->format('M d, Y, g:i A') }} by {{ $subscriptionRequest->reviewer?->name ?? 'Former admin' }}</dd></div>@endif
        </dl>
        <div class="mt-6 border-t border-slate-200 pt-5">
            <h3 class="font-bold">Payment proof</h3>
            <a href="{{ route('super-admin.subscription-requests.proof', $subscriptionRequest) }}" target="_blank" rel="noopener" class="mt-2 inline-block text-sm font-bold text-emerald-700 underline">Open original image</a>
            <img src="{{ route('super-admin.subscription-requests.proof', $subscriptionRequest) }}" alt="Uploaded payment proof for {{ $subscriptionRequest->business?->name ?? 'business' }}" class="mt-4 max-h-[480px] w-full rounded-xl border border-slate-200 object-contain">
        </div>
    </section>

    <aside class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-extrabold">Review decision</h2>
            @if($subscriptionRequest->business)
                <p class="mt-2 text-sm text-slate-600">Current plan: <strong class="capitalize">{{ $subscriptionRequest->business->plan_tier }}</strong>. Accepting activates {{ ucfirst($subscriptionRequest->plan_tier) }} for 30 days. A renewal of the same active tier adds 30 days to its current end date.</p>
                <p class="mt-2 text-xs text-slate-500">Business approval and account status do not change.</p>
            @endif
            @if($subscriptionRequest->status === 'pending' && $subscriptionRequest->business)
                <form method="POST" action="{{ route('super-admin.subscription-requests.accept', $subscriptionRequest) }}" onsubmit="return confirm('Have you verified this payment? Accept and activate the paid plan?');" class="mt-5">@csrf<button class="min-h-11 w-full rounded-xl bg-emerald-700 px-5 text-sm font-bold text-white hover:bg-emerald-800">Accept and activate</button></form>
            @endif
            <form method="POST" action="{{ route('super-admin.subscription-requests.destroy', $subscriptionRequest) }}" onsubmit="return confirm('Delete this request and its payment proof? Any active plan will remain unchanged.');" class="mt-3">@csrf @method('DELETE')<button class="min-h-11 w-full rounded-xl border border-rose-200 px-5 text-sm font-bold text-rose-700 hover:bg-rose-50">Delete request</button></form>
        </section>
    </aside>
</div>
@endsection
