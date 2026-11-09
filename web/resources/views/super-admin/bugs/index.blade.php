@extends('layouts.super-admin')

@section('title', 'Bug reports')

@section('content')
@php
    $priorityStyles = [
        'low' => 'bg-slate-100 text-slate-700',
        'medium' => 'bg-amber-50 text-amber-800',
        'high' => 'bg-rose-50 text-rose-800',
        'critical' => 'bg-rose-50 text-rose-800',
    ];
    $statusStyles = [
        'open' => 'bg-amber-50 text-amber-800',
        'under_review' => 'bg-sky-50 text-sky-800',
        'resolved' => 'bg-emerald-50 text-emerald-800',
        'dismissed' => 'bg-slate-100 text-slate-700',
    ];
@endphp
<div class="mb-7">
    <p class="mb-1 text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Issue triage</p>
    <h1>Bug reports</h1>
    <p class="mt-2 text-sm text-slate-600">Review submitted issues, track their status, and keep reporters updated.</p>
</div>

<nav aria-label="Filter bug reports by status" class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
    @foreach(['all' => 'All reports', 'open' => 'Open', 'under_review' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label)
        @php
            $isCurrent = request('status') === $key || ($key === 'all' && !request('status'));
            $filterQuery = array_merge(request()->only(['search', 'priority']), $key === 'all' ? [] : ['status' => $key]);
        @endphp
        <a href="{{ route('super-admin.bug-reports.index', $filterQuery) }}" aria-current="{{ $isCurrent ? 'page' : 'false' }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 {{ $isCurrent ? 'ring-2 ring-emerald-500 ring-offset-1' : '' }}">
            <span class="block text-2xl font-extrabold tracking-tight">{{ number_format((int) $counts[$key]) }}</span>
            <span class="mt-1 block text-xs font-semibold text-slate-600">{{ $label }}</span>
        </a>
    @endforeach
</nav>

<form method="GET" action="{{ route('super-admin.bug-reports.index') }}" role="search" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_minmax(10rem,auto)_auto] sm:items-end">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <label class="min-w-0 text-xs font-bold text-slate-600">Search reports
        <input name="search" value="{{ request('search') }}" placeholder="Title, description, business, or reporter" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm font-normal">
    </label>
    <label class="text-xs font-bold text-slate-600">Priority
        <select name="priority" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-sm font-normal">
            <option value="">All priorities</option>
            @foreach(\App\Models\BugReport::PRIORITIES as $priority)
                <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white"><i class="fas fa-filter mr-2" aria-hidden="true"></i>Apply filters</button>
</form>
@if(request()->filled('search') || request()->filled('priority') || request()->filled('status'))
    <a href="{{ route('super-admin.bug-reports.index') }}" class="mb-5 inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-bold text-emerald-700 underline underline-offset-4">Clear filters</a>
@endif

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
        <h2 class="font-extrabold">Report queue</h2>
        <p class="text-xs font-medium text-slate-500">{{ number_format($reports->total()) }} {{ \Illuminate\Support\Str::plural('report', $reports->total()) }}</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="p-4">Report</th><th class="p-4">Business / reporter</th><th class="p-4">Priority</th><th class="p-4">Status</th><th class="p-4">Created</th><th class="p-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @if($reports->isEmpty())
                <tr><td colspan="6" class="p-10 text-center"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><i class="fas fa-inbox" aria-hidden="true"></i></span><p class="mt-3 font-bold text-slate-800">No reports match these filters</p><p class="mt-1 text-sm text-slate-500">Try changing your search or priority filter.</p></td></tr>
            @else
            @foreach($reports as $report)
                <tr>
                    <td class="max-w-md p-4">
                        <p class="font-bold text-slate-900">{{ $report->subject }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span class="capitalize">{{ $report->area ? str_replace('_', ' ', $report->area) : 'Area not classified' }}</span>
                            @if($report->is_legacy)<span class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-800">Legacy &middot; {{ str_replace('_', ' ', $report->category) }}</span>@endif
                        </div>
                    </td>
                    <td class="p-4"><p class="font-medium text-slate-800">{{ $report->business?->name ?? 'Unavailable business' }}</p><p class="mt-1 text-xs text-slate-500">{{ $report->reporter?->name ?? 'Administrator' }}</p></td>
                    <td class="p-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize {{ $priorityStyles[$report->priority] ?? 'bg-amber-50 text-amber-800' }}">{{ $report->priority }}</span></td>
                    <td class="p-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize {{ $statusStyles[$report->status] ?? 'bg-amber-50 text-amber-800' }}">{{ str_replace('_', ' ', $report->status) }}</span></td>
                    <td class="whitespace-nowrap p-4 text-slate-600">{{ $report->created_at->format('M d, Y') }}</td>
                    <td class="p-4"><a class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600" href="{{ route('super-admin.bug-reports.show', $report) }}">Review<span class="ml-1" aria-hidden="true">&rarr;</span></a></td>
                </tr>
            @endforeach
            @endif
            </tbody>
        </table>
    </div>
    @if($reports->hasPages())<div class="border-t border-slate-200 p-4">{{ $reports->links() }}</div>@endif
</section>
@endsection
