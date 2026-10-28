@extends('layouts.super-admin')

@section('title', 'Admin activity')

@section('content')
<div class="mb-6"><h1>Admin activity</h1><p class="mt-2 text-sm text-slate-600">Critical platform actions. Passwords and private business records are never stored here.</p></div>
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead><tr><th scope="col" class="p-4">When</th><th scope="col" class="p-4">Administrator</th><th scope="col" class="p-4">Action</th><th scope="col" class="p-4">Target</th><th scope="col" class="p-4">IP address</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($logs as $log)
            <tr><td class="p-4">{{ $log->created_at?->format('M d, Y g:i A') }}</td><td class="p-4">{{ $log->admin?->name ?? 'Deleted administrator' }}</td><td class="p-4">{{ str_replace(['.', '_'], ' ', $log->action) }}</td><td class="p-4">{{ ucfirst($log->target_type) }} #{{ $log->target_id }}</td><td class="p-4">{{ $log->ip_address ?? 'Unknown' }}</td></tr>
        @empty
            <tr><td colspan="5" class="p-8 text-center text-slate-500">No administrative actions recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($logs->hasPages())<div class="border-t border-slate-200 p-4">{{ $logs->links() }}</div>@endif
</section>
@endsection
