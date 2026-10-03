@extends('layouts.super-admin')

@section('title', $business->name)

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-6">
    <div>
        <a href="{{ route('super-admin.businesses') }}" class="text-sm font-medium text-emerald-700">&larr; Businesses</a>
        <div class="mt-2 flex flex-wrap items-center gap-3"><h1 class="text-3xl font-bold">{{ $business->name }}</h1><span class="rounded-full px-3 py-1 text-xs font-semibold capitalize {{ $business->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($business->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $business->status }}</span></div>
        <p class="mt-1 text-slate-600">Registered {{ $business->created_at->format('F d, Y') }}</p>
    </div>
    <a href="{{ route('super-admin.businesses.edit', $business) }}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700"><i class="fas fa-pen mr-2"></i>Edit details</a>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Business information</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2 text-sm">
                <div><dt class="text-slate-500">Business name</dt><dd class="mt-1 font-semibold">{{ $business->name }}</dd></div>
                <div><dt class="text-slate-500">Business type</dt><dd class="mt-1 font-semibold capitalize">{{ $business->business_type }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="mt-1">{{ $business->email ?: 'Not provided' }}</dd></div>
                <div><dt class="text-slate-500">Phone</dt><dd class="mt-1">{{ $business->phone ?: 'Not provided' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Location</dt><dd class="mt-1">{{ $business->address ?: $business->location ?: 'Not provided' }}@if($business->city || $business->province)<span class="block text-slate-500">{{ collect([$business->city, $business->province])->filter()->join(', ') }}</span>@endif</dd></div>
                @if($business->description)<div class="sm:col-span-2"><dt class="text-slate-500">Description</dt><dd class="mt-1">{{ $business->description }}</dd></div>@endif
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Owner account</h2>@if($business->owner_account)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize">{{ $business->owner_account->status }}</span>@endif</div>
            @if($business->owner_account)
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 text-sm">
                    <div><dt class="text-slate-500">Name</dt><dd class="mt-1 font-semibold">{{ $business->owner_account->name }}</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd class="mt-1">{{ $business->owner_account->email }}</dd></div>
                    <div><dt class="text-slate-500">Phone</dt><dd class="mt-1">{{ $business->owner_account->phone ?: 'Not provided' }}</dd></div>
                    <div><dt class="text-slate-500">Created</dt><dd class="mt-1">{{ $business->owner_account->created_at->format('M d, Y') }}</dd></div>
                </dl>
                <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-200 pt-5">
                    <form method="POST" action="{{ route('super-admin.businesses.owner.password-reset', $business) }}">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50"><i class="fas fa-key mr-2"></i>Send password reset</button></form>
                    @if($business->owner_account->status === 'active')
                        <form method="POST" action="{{ route('super-admin.businesses.owner.deactivate', $business) }}">@csrf<button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Deactivate owner</button></form>
                    @else
                        <form method="POST" action="{{ route('super-admin.businesses.owner.activate', $business) }}">@csrf<button class="rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Activate owner</button></form>
                    @endif
                </div>
            @else
                <p class="mt-4 text-slate-500">No owner account is assigned.</p>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Platform-safe overview</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="rounded-lg bg-slate-50 p-5 text-center"><p class="text-3xl font-bold text-emerald-700">{{ $staffCount }}</p><p class="text-sm text-slate-600">Staff accounts</p></div><div class="rounded-lg bg-slate-50 p-5 text-center"><p class="text-xl font-bold capitalize text-emerald-700">{{ $business->status }}</p><p class="text-sm text-slate-600">Business status</p></div></div>
            <p class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">Financial records, transactions, products, stock, and goals remain private and unavailable to platform administrators.</p>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Business access</h2>
            <div class="mt-5 space-y-3">
                @if($business->status === 'pending')
                    <form method="POST" action="{{ route('super-admin.businesses.approve', $business) }}">@csrf<button class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold text-white hover:bg-emerald-700"><i class="fas fa-circle-check mr-2"></i>Approve business</button></form>
                @endif
                @if(in_array($business->status, ['suspended', 'inactive']))
                    <form method="POST" action="{{ route('super-admin.businesses.reactivate', $business) }}">@csrf<button class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold text-white hover:bg-emerald-700"><i class="fas fa-rotate-left mr-2"></i>Reactivate business</button></form>
                @endif
                @if($business->status === 'active')
                    <details class="rounded-lg border border-amber-300 p-3"><summary class="cursor-pointer font-semibold text-amber-800">Suspend business</summary><form method="POST" action="{{ route('super-admin.businesses.suspend', $business) }}" class="mt-3">@csrf<textarea name="reason" required rows="3" placeholder="Reason for suspension" class="w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea><button class="mt-2 w-full rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Confirm suspension</button></form></details>
                @endif
                @if($business->status !== 'inactive')
                    <details class="rounded-lg border border-red-300 p-3"><summary class="cursor-pointer font-semibold text-red-700">Deactivate business</summary><form method="POST" action="{{ route('super-admin.businesses.deactivate', $business) }}" class="mt-3">@csrf<textarea name="reason" required rows="3" placeholder="Reason for deactivation" class="w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea><button class="mt-2 w-full rounded-lg bg-red-600 px-4 py-2 font-semibold text-white">Confirm deactivation</button></form></details>
                @endif
            </div>
            @if($business->suspension_reason)<div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800"><strong>Reason:</strong> {{ $business->suspension_reason }}</div>@endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Account timeline</h2>
            <div class="mt-5 border-l-2 border-emerald-200 pl-4 text-sm"><p class="font-semibold">Business registered</p><p class="text-slate-500">{{ $business->created_at->format('M d, Y · g:i A') }}</p>@if(!$business->updated_at->equalTo($business->created_at))<p class="mt-5 font-semibold">Last updated</p><p class="text-slate-500">{{ $business->updated_at->format('M d, Y · g:i A') }}</p>@endif</div>
        </section>

        <form method="POST" action="{{ route('super-admin.businesses.destroy', $business) }}" onsubmit="return confirm('Archive this business? Access will be disabled, but private records will be preserved.')">@csrf @method('DELETE')<button class="w-full rounded-lg border border-red-300 px-4 py-3 font-semibold text-red-700 hover:bg-red-50"><i class="fas fa-box-archive mr-2"></i>Archive business</button></form>
    </aside>
</div>
@endsection
