@extends('layouts.super-admin')
@section('title', 'Bug report details')
@section('content')
<div class="mb-8 flex flex-wrap items-start justify-between gap-6"><div><a href="{{ route('super-admin.bug-reports.index') }}" class="text-sm font-medium text-emerald-700">&larr; Bugs reports</a><h1 class="mt-2 text-3xl font-bold">{{ $report->subject }}</h1><p class="mt-1 text-slate-600">{{ $report->business?->name ?? 'Archived business' }}</p></div><a href="{{ route('super-admin.bug-reports.edit', $report) }}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Edit report</a></div>
<div class="grid gap-6 lg:grid-cols-3">
    <section class="space-y-6 lg:col-span-2">
        @if($report->is_legacy)<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Legacy report · original category: <strong>{{ str_replace('_', ' ', $report->category) }}</strong></div>@endif
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-bold">Description</h2><p class="mt-3 whitespace-pre-line text-slate-700">{{ $report->description }}</p><h2 class="mt-7 font-bold">Steps to reproduce</h2><p class="mt-3 whitespace-pre-line text-slate-700">{{ $report->steps_to_reproduce ?: 'No steps provided.' }}</p>
            @if($report->has_screenshot)<a target="_blank" href="{{ route('super-admin.bug-reports.screenshot', $report) }}" class="mt-5 inline-flex rounded-lg border border-emerald-200 px-4 py-2 font-semibold text-emerald-800">View attached screenshot</a>@endif
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6"><h2 class="font-bold text-emerald-950">Update for reporter</h2><p class="mt-3 whitespace-pre-line text-emerald-900">{{ $report->public_update ?: 'No public update yet.' }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-bold">Internal resolution notes</h2><p class="mt-3 whitespace-pre-line text-slate-700">{{ $report->resolution_notes ?: 'No internal notes.' }}</p></div>
    </section>
    <aside class="space-y-6"><section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><dl class="space-y-4 text-sm"><div><dt class="text-slate-500">Area</dt><dd class="font-semibold capitalize">{{ $report->area ? str_replace('_', ' ', $report->area) : 'Legacy report' }}</dd></div><div><dt class="text-slate-500">Status</dt><dd class="font-semibold capitalize">{{ str_replace('_', ' ', $report->status) }}</dd></div><div><dt class="text-slate-500">Priority</dt><dd class="font-semibold capitalize">{{ $report->priority }}</dd></div><div><dt class="text-slate-500">Reported by</dt><dd>{{ $report->reporter?->name ?? 'Administrator' }}</dd></div><div><dt class="text-slate-500">Created</dt><dd>{{ $report->created_at->format('M d, Y g:i A') }}</dd></div></dl></section><form method="POST" action="{{ route('super-admin.bug-reports.destroy', $report) }}" onsubmit="return confirm('Delete this bug report?')">@csrf @method('DELETE')<button class="w-full rounded-lg border border-red-300 px-4 py-3 font-semibold text-red-700 hover:bg-red-50">Delete report</button></form></aside>
</div>
@endsection
