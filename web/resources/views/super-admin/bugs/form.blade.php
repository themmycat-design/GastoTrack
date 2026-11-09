@extends('layouts.super-admin')

@section('title', 'Update bug report')

@section('content')
<div class="mb-6">
    <a href="{{ route('super-admin.bug-reports.show', $report) }}" class="inline-flex min-h-11 items-center rounded-lg text-sm font-bold text-emerald-700 hover:text-emerald-900">&larr; Report details</a>
    <p class="mb-1 mt-3 text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Issue triage</p>
    <h1>Update bug report</h1>
    <p class="mt-2 text-sm text-slate-600">{{ $report->subject }}</p>
</div>

<form data-submit-feedback method="POST" enctype="multipart/form-data" action="{{ route('super-admin.bug-reports.update', $report) }}" class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
    @csrf @method('PUT')
    @if($report->is_legacy)<div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><i class="fas fa-circle-info mt-0.5" aria-hidden="true"></i><p>Converted from a previous violation report. Original category: <strong class="capitalize">{{ str_replace('_', ' ', $report->category) }}</strong></p></div>@endif
    <div class="grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-bold text-slate-700">Business
            <select name="business_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal"><option value="">Select business</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected((string) old('business_id', $report->business_id) === (string) $business->id)>{{ $business->name }}{{ $business->trashed() ? ' (previously removed)' : '' }}</option>@endforeach</select>
        </label>
        <label class="text-sm font-bold text-slate-700">Area
            <select name="area" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal"><option value="">Select area</option>@foreach(\App\Models\BugReport::AREAS as $area)<option value="{{ $area }}" @selected(old('area', $report->area) === $area)>{{ ucwords(str_replace('_', ' ', $area)) }}</option>@endforeach</select>
        </label>
        <label class="sm:col-span-2 text-sm font-bold text-slate-700">Title
            <input name="subject" required maxlength="255" value="{{ old('subject', $report->subject) }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal">
        </label>
        <label class="sm:col-span-2 text-sm font-bold text-slate-700">Description
            <textarea name="description" required maxlength="5000" rows="5" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal">{{ old('description', $report->description) }}</textarea>
        </label>
        <label class="sm:col-span-2 text-sm font-bold text-slate-700">Steps to reproduce <span class="font-normal text-slate-500">(optional)</span>
            <textarea name="steps_to_reproduce" maxlength="5000" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal">{{ old('steps_to_reproduce', $report->steps_to_reproduce) }}</textarea>
        </label>
        <label class="sm:col-span-2 text-sm font-bold text-slate-700">Screenshot <span class="font-normal text-slate-500">(optional, JPEG/PNG/WebP, up to 5 MB)</span>
            <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" class="mt-1.5 block w-full rounded-xl border border-slate-300 p-2 text-sm font-normal">
            <span class="mt-1.5 block text-xs font-normal text-slate-500">Screenshots are private and visible only to authorized report viewers.</span>
            @if($report->has_screenshot)<span class="mt-2 block"><a target="_blank" rel="noopener noreferrer" href="{{ route('super-admin.bug-reports.screenshot', $report) }}" class="text-sm font-bold text-emerald-700 underline">View current screenshot</a></span>@endif
        </label>
        <label class="text-sm font-bold text-slate-700">Priority
            <select name="priority" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal">@foreach(\App\Models\BugReport::PRIORITIES as $value)<option value="{{ $value }}" @selected(old('priority', $report->priority ?: 'medium') === $value)>{{ ucfirst($value) }}</option>@endforeach</select>
        </label>
        <label class="text-sm font-bold text-slate-700">Status
            <select name="status" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal">@foreach(\App\Models\BugReport::STATUSES as $value)<option value="{{ $value }}" @selected(old('status', $report->status ?: 'open') === $value)>{{ ucwords(str_replace('_', ' ', $value)) }}</option>@endforeach</select>
        </label>
        <label class="sm:col-span-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-950">Update for reporter
            <textarea name="public_update" maxlength="5000" rows="3" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal">{{ old('public_update', $report->public_update) }}</textarea>
            <span class="mt-1.5 block text-xs font-normal text-emerald-900">Visible to the owner and the staff member who submitted the report.</span>
        </label>
        <label class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-800">Internal resolution notes
            <textarea name="resolution_notes" maxlength="5000" rows="3" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm font-normal">{{ old('resolution_notes', $report->resolution_notes) }}</textarea>
            <span class="mt-1.5 block text-xs font-normal text-slate-600">Visible to Super Admins only.</span>
        </label>
    </div>
    <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
        <a href="{{ route('super-admin.bug-reports.show', $report) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Cancel</a>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700"><i class="fas fa-floppy-disk mr-2" aria-hidden="true"></i><span data-submit-label>Save report</span></button>
    </div>
</form>
@endsection
