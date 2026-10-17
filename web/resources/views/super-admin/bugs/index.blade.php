@extends('layouts.super-admin')
@section('title', 'Bugs reports')
@section('content')
<div class="mb-8"><h1 class="text-3xl font-bold">Bugs reports</h1><p class="mt-1 text-slate-600">Review submitted issues and follow their progress.</p></div>

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @foreach(['all' => 'All reports', 'open' => 'Open', 'under_review' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label)
        <a href="{{ $key === 'all' ? route('super-admin.bug-reports.index') : route('super-admin.bug-reports.index', ['status' => $key]) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm {{ request('status') === $key || ($key === 'all' && !request('status')) ? 'ring-2 ring-emerald-500' : '' }}"><p class="text-2xl font-bold">{{ $counts[$key] }}</p><p class="text-sm text-slate-600">{{ $label }}</p></a>
    @endforeach
</div>

<form class="mb-6 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-5">
    <input name="search" value="{{ request('search') }}" placeholder="Search title, description, business, or reporter" class="min-w-64 flex-1 rounded-lg border-slate-300">
    <select name="priority" class="rounded-lg border-slate-300"><option value="">All priorities</option>@foreach(\App\Models\BugReport::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select>
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <button class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white">Filter</button>
</form>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Report</th><th class="p-4">Business / reporter</th><th class="p-4">Priority</th><th class="p-4">Status</th><th class="p-4">Created</th><th class="p-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($reports as $report)
            <tr>
                <td class="max-w-md p-4 font-semibold">{{ $report->subject }}
                    <div class="mt-1 text-xs font-normal capitalize text-slate-500">{{ $report->area ? str_replace('_', ' ', $report->area) : 'Area not classified' }}
                        @if($report->is_legacy)<span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">Legacy · {{ str_replace('_', ' ', $report->category) }}</span>@endif
                    </div>
                </td>
                <td class="p-4">{{ $report->business?->name ?? 'Archived business' }}<div class="text-xs text-slate-500">{{ $report->reporter?->name ?? 'Administrator' }}</div></td>
                <td class="p-4 capitalize">{{ $report->priority }}</td>
                <td class="p-4 capitalize">{{ str_replace('_', ' ', $report->status) }}</td>
                <td class="p-4 text-slate-600">{{ $report->created_at->format('M d, Y') }}</td>
                <td class="p-4"><a class="font-semibold text-emerald-700" href="{{ route('super-admin.bug-reports.show', $report) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-10 text-center text-slate-500">No bug reports found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($reports->hasPages())<div class="border-t p-4">{{ $reports->links() }}</div>@endif
</div>
@endsection
