@extends('layouts.super-admin')

@section('title', 'Platform users')

@section('content')
<div class="mb-6">
    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Platform directory</p>
    <h1>Users and staff</h1>
    <p class="mt-2 text-sm text-slate-600">Find accounts across businesses. Financial records remain private.</p>
</div>

<form method="GET" action="{{ route('super-admin.users.index') }}" class="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
    <label class="text-sm font-semibold">Search name, email, role, or business
        <input name="search" value="{{ request('search') }}" maxlength="100" class="mt-1 w-full" placeholder="Search accounts">
    </label>
    <label class="text-sm font-semibold">Role
        <select name="role" class="mt-1 w-full"><option value="">All roles</option>@foreach(['super_admin' => 'Super Admin', 'owner' => 'Owner', 'staff' => 'Staff'] as $value => $label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select>
    </label>
    <label class="text-sm font-semibold">Business ID
        <input name="business_id" type="number" min="1" value="{{ request('business_id') }}" class="mt-1 w-full" placeholder="All businesses">
    </label>
    <label class="text-sm font-semibold">Sort by
        <select name="sort" class="mt-1 w-full">@foreach(['created_at' => 'Joined', 'name' => 'Name', 'email' => 'Email', 'role' => 'Role'] as $value => $label)<option value="{{ $value }}" @selected(request('sort', 'created_at') === $value)>{{ $label }}</option>@endforeach</select>
    </label>
    <div class="flex items-end gap-2"><select name="direction" aria-label="Sort direction" class="min-w-0 flex-1"><option value="desc" @selected(request('direction', 'desc') === 'desc')>Newest / Z–A</option><option value="asc" @selected(request('direction') === 'asc')>Oldest / A–Z</option></select><button class="min-h-11 rounded-lg bg-emerald-600 px-4 font-semibold text-white">Apply</button></div>
</form>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead><tr><th scope="col" class="p-4">Account</th><th scope="col" class="p-4">Role</th><th scope="col" class="p-4">Business</th><th scope="col" class="p-4">Status</th><th scope="col" class="p-4">Joined</th><th scope="col" class="p-4">Action</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($users as $user)
            @php($hiddenOwner = $user->isOwner() && ! $user->hasVerifiedEmail())
            <tr>
                <td class="p-4"><span class="font-semibold">{{ $hiddenOwner ? 'Email verification pending' : $user->name }}</span>@unless($hiddenOwner)<span class="block text-xs text-slate-500">{{ $user->email }}</span>@endunless</td>
                <td class="p-4 capitalize">{{ str_replace('_', ' ', $user->role) }}</td>
                <td class="p-4">{{ $user->business?->name ?? 'Platform' }}</td>
                <td class="p-4 capitalize">{{ $user->status }}</td>
                <td class="p-4">{{ $user->created_at?->format('M d, Y') }}</td>
                <td class="p-4"><div class="flex flex-wrap items-center gap-2">
                    <a class="inline-flex min-h-11 items-center font-bold text-emerald-700" href="{{ route('super-admin.users.show', $user) }}">View details</a>
                    @if(! $hiddenOwner && $user->id !== auth()->id())<form method="POST" action="{{ route('super-admin.users.password-reset', $user) }}" onsubmit="return confirm('Require this account to reset its password and revoke mobile sessions?')">@csrf<button class="min-h-11 rounded-lg px-2 font-semibold text-emerald-700 hover:bg-emerald-50">Reset password</button></form>@endif
                    @if($user->status === 'active' && $user->id !== auth()->id())
                        <form method="POST" action="{{ route('super-admin.users.lock', $user) }}" onsubmit="return confirm('Lock this account and revoke mobile access?')">@csrf<button class="min-h-11 rounded-lg px-2 font-semibold text-red-700 hover:bg-red-50">Lock</button></form>
                    @elseif($user->status !== 'active')
                        <form method="POST" action="{{ route('super-admin.users.unlock', $user) }}">@csrf<button class="min-h-11 rounded-lg px-2 font-semibold text-emerald-700 hover:bg-emerald-50">Unlock</button></form>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-8 text-center text-slate-500">No matching accounts.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($users->hasPages())<div class="border-t border-slate-200 p-4">{{ $users->links() }}</div>@endif
</section>
@endsection
