@extends('layouts.super-admin')

@section('title', $business->name)

@section('content')
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div>
        <a href="{{ route('super-admin.businesses') }}" class="inline-flex min-h-11 items-center rounded-lg text-sm font-bold text-emerald-700 hover:text-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">&larr; Businesses</a>
        <div class="mt-3 flex flex-wrap items-center gap-3"><h1>{{ $business->name }}</h1><span class="rounded-full px-3 py-1 text-xs font-bold capitalize {{ $business->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($business->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $business->status }}</span></div>
        <p class="mt-2 text-sm text-slate-600">Business profile <span class="mx-1 text-slate-300">&middot;</span> Registered {{ $business->created_at->format('F d, Y') }}</p>
    </div>
    <a href="{{ route('super-admin.businesses.edit', $business) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"><i class="fas fa-pen mr-2" aria-hidden="true"></i>Edit details</a>
</div>

<div class="grid gap-5 xl:grid-cols-3">
    <div class="space-y-5 xl:col-span-2">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Business information</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2 text-sm">
                <div><dt class="text-slate-500">Business name</dt><dd class="mt-1 font-semibold">{{ $business->name }}</dd></div>
                <div><dt class="text-slate-500">Business type</dt><dd class="mt-1 font-semibold capitalize">{{ $business->business_type }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="mt-1">{{ $ownerDetailsHidden ? 'Hidden until owner verifies email' : ($business->email ?: 'Not provided') }}</dd></div>
                <div><dt class="text-slate-500">Phone</dt><dd class="mt-1">{{ $ownerDetailsHidden ? 'Hidden until owner verifies email' : ($business->phone ?: 'Not provided') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Location</dt><dd class="mt-1">{{ $business->address ?: $business->location ?: 'Not provided' }}@if($business->city || $business->province)<span class="block text-slate-500">{{ collect([$business->city, $business->province])->filter()->join(', ') }}</span>@endif</dd></div>
                @if($business->description)<div class="sm:col-span-2"><dt class="text-slate-500">Description</dt><dd class="mt-1">{{ $business->description }}</dd></div>@endif
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Owner account</h2>@if($ownerAccountForDisplay)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize">{{ $ownerAccountForDisplay->status }}</span>@endif</div>
            @if($ownerAccountForDisplay)
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 text-sm">
                    <div><dt class="text-slate-500">Name</dt><dd class="mt-1 font-semibold">{{ $ownerAccountForDisplay->name }}</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd class="mt-1">{{ $ownerAccountForDisplay->email }}</dd></div>
                    <div><dt class="text-slate-500">Phone</dt><dd class="mt-1">{{ $ownerAccountForDisplay->phone ?: 'Not provided' }}</dd></div>
                    <div><dt class="text-slate-500">Created</dt><dd class="mt-1">{{ $ownerAccountForDisplay->created_at->format('M d, Y') }}</dd></div>
                </dl>
                <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-200 pt-5">
                    <form method="POST" action="{{ route('super-admin.businesses.owner.password-reset', $business) }}">@csrf<button class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50"><i class="fas fa-key mr-2" aria-hidden="true"></i>Send password reset</button></form>
                    @if($ownerAccountForDisplay->status === 'active')
                        <form method="POST" action="{{ route('super-admin.businesses.owner.deactivate', $business) }}">@csrf<button class="min-h-11 rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Deactivate owner</button></form>
                    @else
                        <form method="POST" action="{{ route('super-admin.businesses.owner.activate', $business) }}">@csrf<button class="min-h-11 rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Activate owner</button></form>
                    @endif
                </div>
            @elseif($ownerDetailsHidden)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Owner details are hidden until the registered owner verifies their email.</p>
            @else
                <p class="mt-4 text-slate-500">No owner account is assigned.</p>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Platform-safe overview</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="rounded-lg bg-slate-50 p-5 text-center"><p class="text-3xl font-bold text-emerald-700">{{ $staffCount }}</p><p class="text-sm text-slate-600">Staff accounts</p></div><div class="rounded-lg bg-slate-50 p-5 text-center"><p class="text-xl font-bold capitalize text-emerald-700">{{ $business->status }}</p><p class="text-sm text-slate-600">Business status</p></div></div>
            <p class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">Financial records, transactions, products, stock, and goals remain private and unavailable to platform administrators.</p>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Subscription controls</h2>
            <a href="{{ route('super-admin.subscription-requests.index') }}" class="mt-2 inline-flex text-sm font-semibold text-emerald-700 hover:underline">Review subscription requests &rarr;</a>
            <p class="mt-1 text-sm text-slate-600">Business approval is separate. Free: 1 staff and 3 products; Basic: 2 and 5; Pro: 5 and 15; Enterprise: unlimited. Paid plans need an end date.</p>
            <p class="mt-3 text-sm font-semibold">Subscription access: {{ $business->subscriptionIsCurrent() ? 'Current' : 'Expired or suspended' }} <span class="font-normal text-slate-600">(business status: {{ $business->status }})</span></p>
            <form method="POST" action="{{ route('super-admin.businesses.subscription.update', $business) }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                @csrf @method('PUT')
                <label class="text-sm font-semibold">Plan
                    <select name="plan_tier" class="mt-1 w-full">@foreach(['free', 'basic', 'pro', 'enterprise'] as $tier)<option value="{{ $tier }}" @selected(old('plan_tier', $business->plan_tier) === $tier)>{{ ucfirst($tier) }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold">Subscription status
                    <select name="subscription_status" class="mt-1 w-full">@foreach(['active', 'suspended', 'expired'] as $status)<option value="{{ $status }}" @selected(old('subscription_status', $business->subscription_status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold">Ends on
                    <input type="date" name="subscription_ends_at" value="{{ old('subscription_ends_at', $business->subscription_ends_at?->copy()->timezone('Asia/Manila')->format('Y-m-d')) }}" class="mt-1 w-full">
                </label>
                <button class="min-h-11 rounded-lg bg-emerald-600 px-4 font-semibold text-white sm:col-span-3">Save subscription</button>
            </form>
            @if($business->plan_tier !== 'free')
                <form method="POST" action="{{ route('super-admin.businesses.subscription.extend', $business) }}" class="mt-3">@csrf<button class="min-h-11 rounded-lg border border-emerald-300 px-4 font-semibold text-emerald-700">Extend by 30 days</button></form>
            @endif
        </section>
    </div>

    <aside class="space-y-5">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Business access</h2>
            <div class="mt-5 space-y-3">
                @if($business->status === 'pending')
                    @if($ownerEmailVerified)
                        <form method="POST" action="{{ route('super-admin.businesses.approve', $business) }}">@csrf<button class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold text-white hover:bg-emerald-700"><i class="fas fa-circle-check mr-2"></i>Approve business</button></form>
                    @else
                        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $ownerAccountExists ? 'Owner email verification is required before approval.' : 'Assign a business owner and verify their email before approval.' }}</p>
                    @endif
                @endif
                @if(in_array($business->status, ['suspended', 'inactive']))
                    @if($ownerEmailVerified)
                        <form method="POST" action="{{ route('super-admin.businesses.reactivate', $business) }}">@csrf<button class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold text-white hover:bg-emerald-700"><i class="fas fa-rotate-left mr-2"></i>Reactivate business</button></form>
                    @else
                        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Owner email verification is required before reactivation.</p>
                    @endif
                @endif
                @if($business->status === 'active')
                    <details class="rounded-lg border border-amber-300 p-3"><summary class="flex min-h-11 cursor-pointer items-center font-semibold text-amber-800">Suspend business</summary><form method="POST" action="{{ route('super-admin.businesses.suspend', $business) }}" class="mt-3">@csrf<label for="suspension-reason" class="mb-2 block text-sm font-semibold">Reason for suspension</label><textarea id="suspension-reason" name="reason" required rows="3" placeholder="Explain why this business is being suspended" class="w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea><button class="mt-2 min-h-11 w-full rounded-lg bg-amber-700 px-4 py-2 font-semibold text-white">Confirm suspension</button></form></details>
                @endif
                @if($business->status !== 'inactive')
                    <details class="rounded-lg border border-red-300 p-3"><summary class="flex min-h-11 cursor-pointer items-center font-semibold text-red-700">Deactivate business</summary><form method="POST" action="{{ route('super-admin.businesses.deactivate', $business) }}" class="mt-3">@csrf<label for="deactivation-reason" class="mb-2 block text-sm font-semibold">Reason for deactivation</label><textarea id="deactivation-reason" name="reason" required rows="3" placeholder="Explain why this business is being deactivated" class="w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea><button class="mt-2 min-h-11 w-full rounded-lg bg-red-700 px-4 py-2 font-semibold text-white">Confirm deactivation</button></form></details>
                @endif
            </div>
            @if($business->suspension_reason)<div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800"><strong>Reason:</strong> {{ $business->suspension_reason }}</div>@endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Account timeline</h2>
            <div class="mt-5 border-l-2 border-emerald-200 pl-4 text-sm"><p class="font-semibold">Business registered</p><p class="text-slate-500">{{ $business->created_at->format('M d, Y · g:i A') }}</p>@if(!$business->updated_at->equalTo($business->created_at))<p class="mt-5 font-semibold">Last updated</p><p class="text-slate-500">{{ $business->updated_at->format('M d, Y · g:i A') }}</p>@endif</div>
        </section>

        <form method="POST" action="{{ route('super-admin.businesses.destroy', $business) }}" data-business-name="{{ $business->name }}" onsubmit="return confirm('Permanently delete ' + this.dataset.businessName + ', its owner and staff accounts, and all associated records? This cannot be undone.')">@csrf @method('DELETE')<button class="min-h-11 w-full rounded-lg border border-red-300 px-4 py-3 font-semibold text-red-700 hover:bg-red-50"><i class="fas fa-trash mr-2" aria-hidden="true"></i>Delete business permanently</button></form>
    </aside>
</div>
@endsection
