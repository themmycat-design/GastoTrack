@extends('layouts.super-admin')

@section('title', 'User details')

@section('content')
@php($hiddenOwner = $user->isOwner() && ! $user->hasVerifiedEmail())
<a href="{{ route('super-admin.users.index') }}" class="inline-flex min-h-11 items-center font-semibold text-emerald-700">&larr; Users and staff</a>
<div class="mt-3 mb-6"><h1>{{ $hiddenOwner ? 'Email verification pending' : $user->name }}</h1><p class="mt-1 text-sm text-slate-600">Platform account #{{ $user->id }}</p></div>
<div class="grid gap-5 lg:grid-cols-[2fr_1fr]">
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-bold">Account details</h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Role</dt><dd class="mt-1 font-semibold capitalize">{{ str_replace('_', ' ', $user->role) }}</dd></div>
            <div><dt class="text-slate-500">Status</dt><dd class="mt-1 font-semibold capitalize">{{ $user->status }}</dd></div>
            <div><dt class="text-slate-500">Business</dt><dd class="mt-1 font-semibold">{{ $user->business?->name ?? 'Platform account' }}</dd></div>
            <div><dt class="text-slate-500">Joined</dt><dd class="mt-1 font-semibold">{{ $user->created_at?->format('M d, Y') }}</dd></div>
            @unless($hiddenOwner)
                <div><dt class="text-slate-500">Email</dt><dd class="mt-1 font-semibold">{{ $user->email }}</dd></div>
                <div><dt class="text-slate-500">Phone</dt><dd class="mt-1 font-semibold">{{ $user->phone ?: 'Not provided' }}</dd></div>
            @endunless
        </dl>
        @if($hiddenOwner)<p class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">Owner identity and contact details stay hidden until email verification.</p>@endif
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-bold">Account actions</h2>
        <p class="mt-1 text-sm text-slate-600">Locking revokes mobile tokens. A new web request will end a locked platform session.</p>
        <div class="mt-5 space-y-3">
            @if(! $hiddenOwner && $user->id !== auth()->id())
                <form method="POST" action="{{ route('super-admin.users.password-reset', $user) }}" onsubmit="return confirm('Require this account to reset its password and revoke mobile sessions?')">@csrf<button class="min-h-11 w-full rounded-lg border border-slate-300 px-4 font-semibold hover:bg-slate-50">Require password reset</button></form>
            @endif
            @if($user->status === 'active' && $user->id !== auth()->id())
                <form method="POST" action="{{ route('super-admin.users.lock', $user) }}" onsubmit="return confirm('Lock this account and revoke its mobile access?')">@csrf<button class="min-h-11 w-full rounded-lg border border-red-300 px-4 font-semibold text-red-700 hover:bg-red-50">Lock account</button></form>
            @elseif($user->status !== 'active')
                <form method="POST" action="{{ route('super-admin.users.unlock', $user) }}">@csrf<button class="min-h-11 w-full rounded-lg bg-emerald-600 px-4 font-semibold text-white">Unlock account</button></form>
            @else
                <p class="text-sm text-slate-500">You cannot lock your own account.</p>
            @endif
        </div>
    </section>
</div>
@endsection
