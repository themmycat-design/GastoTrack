@extends('layouts.super-admin')
@section('title', 'Violation Reports')
@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-6">
    <div><h1 class="text-3xl font-bold">Violation reports</h1><p class="mt-1 text-slate-600">Record and resolve platform-level complaints and policy issues.</p></div>
    <a href="{{ route('super-admin.violations.create') }}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white"><i class="fas fa-plus mr-2"></i>New report</a>
</div>
<div class="mb-6 grid gap-6 sm:grid-cols-4">
    @foreach(['all' => 'All', 'open' => 'Open', 'under_review' => 'Under review', 'resolved' => 'Resolved'] as $key => $label)
        <a href="{{ $key === 'all' ? route('super-admin.violations.index') : route('super-admin.violations.index', ['status' => $key]) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-2xl font-bold">{{ $counts[$key] }}</p><p class="text-sm text-slate-600">{{ $label }}</p></a>
    @endforeach
</div>
<form class="mb-6 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-5">
    <input name="search" value="{{ request('search') }}" placeholder="Search business or subject" class="min-w-64 flex-1 rounded-lg border-slate-300">
    <select name="priority" class="rounded-lg border-slate-300"><option value="">All priorities</option>@foreach(['low','medium','high','critical'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select>
    <button class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white">Filter</button>
</form>
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Report</th><th class="p-4">Business</th><th class="p-4">Priority</th><th class="p-4">Status</th><th class="p-4">Created</th><th class="p-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($reports as $report)
            <tr><td class="p-4 font-medium">{{ $report->subject }}<div class="text-xs font-normal capitalize text-slate-500">{{ str_replace('_', ' ', $report->category) }}</div></td><td class="p-4">{{ $report->business?->name ?? 'Archived business' }}</td><td class="p-4 capitalize">{{ $report->priority }}</td><td class="p-4 capitalize">{{ str_replace('_', ' ', $report->status) }}</td><td class="p-4 text-slate-600">{{ $report->created_at->format('M d, Y') }}</td><td class="p-4"><a class="font-semibold text-emerald-700" href="{{ route('super-admin.violations.show', $report) }}">View</a></td></tr>
        @empty<tr><td colspan="6" class="p-10 text-center text-slate-500">No violation reports found.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($reports->hasPages())<div class="border-t p-4">{{ $reports->links() }}</div>@endif
</div>
@endsection
