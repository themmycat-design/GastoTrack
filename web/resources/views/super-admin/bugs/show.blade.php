@extends('layouts.super-admin')

@section('title', 'Bug report details')

@section('content')
@php
    $statusStyle = match ($report->status) {
        'resolved' => 'bg-emerald-50 text-emerald-800',
        'dismissed' => 'bg-slate-100 text-slate-700',
        'under_review' => 'bg-sky-50 text-sky-800',
        default => 'bg-amber-50 text-amber-800',
    };
    $priorityStyle = match ($report->priority) {
        'high', 'critical' => 'bg-rose-50 text-rose-800',
        'low' => 'bg-slate-100 text-slate-700',
        default => 'bg-amber-50 text-amber-800',
    };
@endphp
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        <a href="{{ route('super-admin.bug-reports.index') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-900">&larr; Bug reports</a>
        <p class="mb-1 mt-3 text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Report details</p>
        <h1 class="break-words">{{ $report->subject }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $report->business?->name ?? 'Archived business' }}</p>
    </div>
    <a href="{{ route('super-admin.bug-reports.edit', $report) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"><i class="fas fa-pen mr-2" aria-hidden="true"></i>Update report</a>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="space-y-5">
        @if($report->is_legacy)
            <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><i class="fas fa-circle-info mt-0.5" aria-hidden="true"></i><p>Legacy report &middot; original category: <strong class="capitalize">{{ str_replace('_', ' ', $report->category) }}</strong></p></div>
        @endif
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-extrabold">Issue description</h2>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $report->description }}</p>
            <div class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="font-bold text-slate-900">Steps to reproduce</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $report->steps_to_reproduce ?: 'No steps provided.' }}</p>
            </div>
            @if($report->has_screenshot)
                <a target="_blank" rel="noopener noreferrer" href="{{ route('super-admin.bug-reports.screenshot', $report) }}" class="mt-5 inline-flex min-h-10 items-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-800 transition hover:bg-emerald-50"><i class="fas fa-image mr-2" aria-hidden="true"></i>View attached screenshot</a>
            @endif
        </section>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6">
            <div class="flex items-center gap-2"><i class="fas fa-comment-dots text-emerald-700" aria-hidden="true"></i><h2 class="text-lg font-extrabold text-emerald-950">Update for reporter</h2></div>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-emerald-900">{{ $report->public_update ?: 'No public update yet.' }}</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center gap-2"><i class="fas fa-lock text-slate-500" aria-hidden="true"></i><h2 class="text-lg font-extrabold">Internal resolution notes</h2></div>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $report->resolution_notes ?: 'No internal notes.' }}</p>
            <p class="mt-3 text-xs text-slate-500">Visible to Super Admins only.</p>
        </section>
    </div>

    <aside class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-extrabold">Report summary</h2>
            <dl class="mt-5 divide-y divide-slate-100 text-sm">
                <div class="py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Area</dt><dd class="mt-1 font-semibold capitalize text-slate-800">{{ $report->area ? str_replace('_', ' ', $report->area) : 'Legacy report' }}</dd></div>
                <div class="py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1.5"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize {{ $statusStyle }}">{{ str_replace('_', ' ', $report->status) }}</span></dd></div>
                <div class="py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Priority</dt><dd class="mt-1.5"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize {{ $priorityStyle }}">{{ $report->priority }}</span></dd></div>
                <div class="py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reported by</dt><dd class="mt-1 font-semibold text-slate-800">{{ $report->reporter?->name ?? 'Administrator' }}</dd></div>
                <div class="py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created</dt><dd class="mt-1 font-semibold text-slate-800">{{ $report->created_at->format('M d, Y g:i A') }}</dd></div>
            </dl>
        </section>
        <form method="POST" action="{{ route('super-admin.bug-reports.destroy', $report) }}" onsubmit="return confirm('Delete this bug report?')">
            @csrf @method('DELETE')
            <button class="w-full rounded-xl border border-red-200 bg-white px-4 py-3 text-sm font-bold text-red-700 transition hover:bg-red-50"><i class="fas fa-trash-can mr-2" aria-hidden="true"></i>Delete report</button>
        </form>
    </aside>
</div>
@endsection
