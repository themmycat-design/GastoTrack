@extends('layouts.super-admin')
@section('title', $report->exists ? 'Edit Violation' : 'New Violation')
@section('content')
<div class="mb-8"><a href="{{ route('super-admin.violations.index') }}" class="text-sm font-medium text-emerald-700">&larr; Violation reports</a><h1 class="mt-2 text-3xl font-bold">{{ $report->exists ? 'Edit violation report' : 'New violation report' }}</h1></div>
<form method="POST" action="{{ $report->exists ? route('super-admin.violations.update', $report) : route('super-admin.violations.store') }}" class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf @if($report->exists) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <label class="sm:col-span-2 text-sm font-medium">Business<select name="business_id" required class="mt-1 w-full rounded-lg border-slate-300"><option value="">Select business</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected((string) old('business_id', $report->business_id) === (string) $business->id)>{{ $business->name }}</option>@endforeach</select></label>
        <label class="sm:col-span-2 text-sm font-medium">Subject<input name="subject" required maxlength="255" value="{{ old('subject', $report->subject) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">Category<select name="category" class="mt-1 w-full rounded-lg border-slate-300">@foreach(['complaint','policy_violation','fraud_risk','account_issue','other'] as $value)<option value="{{ $value }}" @selected(old('category', $report->category ?: 'complaint') === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Priority<select name="priority" class="mt-1 w-full rounded-lg border-slate-300">@foreach(['low','medium','high','critical'] as $value)<option value="{{ $value }}" @selected(old('priority', $report->priority ?: 'medium') === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded-lg border-slate-300">@foreach(['open','under_review','resolved','dismissed'] as $value)<option value="{{ $value }}" @selected(old('status', $report->status ?: 'open') === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select></label>
        <div></div>
        <label class="sm:col-span-2 text-sm font-medium">Description<textarea name="description" required rows="6" class="mt-1 w-full rounded-lg border-slate-300">{{ old('description', $report->description) }}</textarea></label>
        <label class="sm:col-span-2 text-sm font-medium">Resolution notes<textarea name="resolution_notes" rows="4" class="mt-1 w-full rounded-lg border-slate-300">{{ old('resolution_notes', $report->resolution_notes) }}</textarea></label>
    </div>
    <div class="mt-6 flex justify-end gap-3"><a href="{{ route('super-admin.violations.index') }}" class="rounded-lg border px-5 py-3 font-medium">Cancel</a><button class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Save report</button></div>
</form>
@endsection
