<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Bug reports | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
@php
    $statusStyles = [
        'open' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'under_review' => 'bg-sky-50 text-sky-800 ring-sky-200',
        'resolved' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'dismissed' => 'bg-slate-100 text-slate-700 ring-slate-200',
    ];
@endphp

<div class="gt-enter space-y-6 pb-10">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-[#E6F7F0] px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[.14em] text-[#08745D]">
                <span class="h-1.5 w-1.5 rounded-full bg-[#00A87E]"></span>
                Support center
            </span>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-4xl">Bug reports</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Tell us when something is not working as expected and follow updates from the GastoTrack team.</p>
        </div>
        <div class="inline-flex items-center gap-2 self-start rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm font-semibold text-[#0A2E2A] shadow-sm sm:self-auto">
            <svg class="h-5 w-5 text-[#00A87E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 9h8v8a4 4 0 0 1-8 0V9Z"/><path d="M10 9a2 2 0 1 1 4 0M4 12h4m8 0h4M5 7l3 2m11-2-3 2M6 18h3m6 0h3"/></svg>
            {{ $counts['total'] }} {{ $counts['total'] === 1 ? 'report' : 'reports' }}
        </div>
    </header>

    @if(session('success'))
        <div role="status" class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 shadow-sm">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
            <p>{{ session('success') }}</p>
        </div>
    @endif
    @if($errors->any())
        <div role="alert" class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 shadow-sm">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></svg>
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <section aria-label="Report status summary" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([
            ['All reports', $counts['total'], 'text-[#0A2E2A]', 'bg-[#F0F8F5]', 'file-document-multiple-outline'],
            ['Open', $counts['open'], 'text-amber-700', 'bg-amber-50', 'alert-circle-outline'],
            ['Under review', $counts['under_review'], 'text-sky-700', 'bg-sky-50', 'magnify'],
            ['Resolved', $counts['resolved'], 'text-emerald-700', 'bg-emerald-50', 'check-circle-outline'],
        ] as [$label, $value, $color, $iconBg, $icon])
            <article class="rounded-2xl border border-[#DDE9E5] bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 sm:text-sm">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-extrabold {{ $color }}">{{ $value }}</p>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $iconBg }}">
                        @if($icon === 'file-document-multiple-outline')
                            <svg class="h-5 w-5 {{ $color }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 4h9l3 3v13H8z"/><path d="M17 4v4h4M5 7H4v14h12v-1M11 12h6m-6 3h6"/></svg>
                        @elseif($icon === 'alert-circle-outline')
                            <svg class="h-5 w-5 {{ $color }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></svg>
                        @elseif($icon === 'magnify')
                            <svg class="h-5 w-5 {{ $color }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5M8.5 10.8h4.6m-2.3-2.3v4.6"/></svg>
                        @else
                            <svg class="h-5 w-5 {{ $color }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></svg>
                        @endif
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,.75fr)]">
        <section class="overflow-hidden rounded-2xl border border-[#DDE9E5] bg-white shadow-sm">
            <div class="border-b border-[#EAF2EF] bg-gradient-to-r from-[#F0FBF6] to-white px-5 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#00A87E] text-white shadow-sm">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 9h8v8a4 4 0 0 1-8 0V9Z"/><path d="M10 9a2 2 0 1 1 4 0M4 12h4m8 0h4M5 7l3 2m11-2-3 2M6 18h3m6 0h3"/></svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-extrabold text-[#0A2E2A]">Report an issue</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-600">Share what happened so our team can investigate.</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('owner.bug-reports.store') }}" enctype="multipart/form-data" class="space-y-5 p-5 sm:p-7">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-bold text-[#173F39]">
                        Report title <span class="text-rose-600">*</span>
                        <input name="subject" required maxlength="255" value="{{ old('subject') }}" placeholder="Example: Inventory count does not update" class="mt-2 block w-full rounded-xl border border-[#D4E2DE] bg-white px-4 py-3 text-sm font-normal text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#00A87E] focus:outline-none focus:ring-2 focus:ring-[#00C897]/20">
                    </label>
                    <label class="block text-sm font-bold text-[#173F39]">
                        Area <span class="text-rose-600">*</span>
                        <select name="area" required class="mt-2 block w-full rounded-xl border border-[#D4E2DE] bg-white px-4 py-3 text-sm font-normal text-[#0A2E2A] focus:border-[#00A87E] focus:outline-none focus:ring-2 focus:ring-[#00C897]/20">
                            <option value="">Choose the affected area</option>
                            @foreach(\App\Models\BugReport::AREAS as $area)
                                <option value="{{ $area }}" @selected(old('area') === $area)>{{ ucwords(str_replace('_', ' ', $area)) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="block text-sm font-bold text-[#173F39]">
                    What happened? <span class="text-rose-600">*</span>
                    <textarea name="description" required maxlength="5000" rows="5" placeholder="Describe what you were doing, what you expected, and what happened instead." class="mt-2 block w-full resize-y rounded-xl border border-[#D4E2DE] bg-white px-4 py-3 text-sm font-normal leading-6 text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#00A87E] focus:outline-none focus:ring-2 focus:ring-[#00C897]/20">{{ old('description') }}</textarea>
                </label>

                <label class="block text-sm font-bold text-[#173F39]">
                    Steps to reproduce <span class="font-normal text-slate-500">(optional)</span>
                    <textarea name="steps_to_reproduce" maxlength="5000" rows="3" placeholder="1. Open the page  2. Tap the button  3. Describe what you see" class="mt-2 block w-full resize-y rounded-xl border border-[#D4E2DE] bg-white px-4 py-3 text-sm font-normal leading-6 text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#00A87E] focus:outline-none focus:ring-2 focus:ring-[#00C897]/20">{{ old('steps_to_reproduce') }}</textarea>
                </label>

                <div>
                    <label for="bug-screenshot" class="block text-sm font-bold text-[#173F39]">Screenshot <span class="font-normal text-slate-500">(optional)</span></label>
                    <div class="mt-2 rounded-xl border border-dashed border-[#BBD9CF] bg-[#F8FCFA] p-4 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E6F7F0] text-[#08745D]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-[#0A2E2A]">Attach a screenshot</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">A screenshot can help explain the issue. JPEG, PNG, or WebP, up to 5 MB.</p>
                            </div>
                        </div>
                        <input id="bug-screenshot" type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" class="mt-4 block w-full cursor-pointer text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#E6F7F0] file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-[#08745D] hover:file:bg-[#D7F1E6]">
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-[#EAF2EF] pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-slate-500">Your report is shared with the Super Admin support team. You can follow its status below.</p>
                    <button type="submit" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#00A87E] px-5 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-[#08745D] focus:outline-none focus:ring-4 focus:ring-[#00C897]/25">
                        Submit report
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            </form>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-[#DDE9E5] bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-extrabold text-[#0A2E2A]">What happens next?</h2>
                <p class="mt-1 text-sm text-slate-500">You can track each step here.</p>
                <ol class="mt-5 space-y-5">
                    <li class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#E6F7F0] text-xs font-extrabold text-[#08745D]">1</span>
                        <div><p class="text-sm font-bold text-[#173F39]">Report submitted</p><p class="mt-1 text-xs leading-5 text-slate-500">Your report is saved for the support team to review.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-50 text-xs font-extrabold text-sky-700">2</span>
                        <div><p class="text-sm font-bold text-[#173F39]">Under review</p><p class="mt-1 text-xs leading-5 text-slate-500">The team may investigate and post an update.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-extrabold text-emerald-700">3</span>
                        <div><p class="text-sm font-bold text-[#173F39]">Resolution posted</p><p class="mt-1 text-xs leading-5 text-slate-500">The final status and any public update appear in your report list.</p></div>
                    </li>
                </ol>
            </section>

            <section class="rounded-2xl border border-[#CDE9DC] bg-[#EAF8F2] p-5 sm:p-6">
                <div class="flex gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#08745D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg>
                    <div>
                        <h2 class="text-sm font-extrabold text-[#0A2E2A]">A few helpful details</h2>
                        <p class="mt-1 text-xs leading-5 text-[#315F55]">Include the page or feature involved and the steps that led to the issue. Do not attach passwords, payment details, or other sensitive information.</p>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <section class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-extrabold text-[#0A2E2A]">Your reports</h2>
                <p class="mt-1 text-sm text-slate-500">Reports submitted by your business, with the latest status and updates.</p>
            </div>
            <p class="text-xs font-semibold text-slate-500">Showing {{ $reports->firstItem() ?? 0 }}–{{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }}</p>
        </div>

        @forelse($reports as $report)
            @php
                $statusStyle = $statusStyles[$report->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                $areaLabel = $report->area ? ucwords(str_replace('_', ' ', $report->area)) : 'Legacy report';
            @endphp
            <article class="overflow-hidden rounded-2xl border border-[#DDE9E5] bg-white shadow-sm transition hover:border-[#C8DED7] hover:shadow-md">
                <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#F0F8F5] text-[#08745D]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 9h8v8a4 4 0 0 1-8 0V9Z"/><path d="M10 9a2 2 0 1 1 4 0M4 12h4m8 0h4M5 7l3 2m11-2-3 2M6 18h3m6 0h3"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-extrabold text-[#0A2E2A]">{{ $report->subject }}</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $areaLabel }} <span class="mx-1 text-slate-300">·</span> {{ ucfirst($report->priority) }} priority <span class="mx-1 text-slate-300">·</span> {{ $report->created_at->format('M d, Y · g:i A') }}</p>
                                @if($report->reporter)<p class="mt-1 text-xs text-slate-500">Submitted by {{ $report->reporter->name }}</p>@endif
                            </div>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full px-3 py-1.5 text-xs font-extrabold capitalize ring-1 ring-inset {{ $statusStyle }}">
                            @if($report->status === 'resolved')
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.2 7.26a1 1 0 0 1-1.42.004l-3.8-3.76a1 1 0 1 1 1.408-1.422l3.09 3.058 6.494-6.548a1 1 0 0 1 1.422-.006Z" clip-rule="evenodd"/></svg>
                            @elseif($report->status === 'under_review')
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11a.75.75 0 0 0-1.5 0v3.5c0 .2.08.39.22.53l2.25 2.25a.75.75 0 1 0 1.06-1.06l-2.03-2.03V7Z" clip-rule="evenodd"/></svg>
                            @else
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                            @endif
                            {{ str_replace('_', ' ', $report->status) }}
                        </span>
                    </div>

                    @if($report->is_legacy)
                        <div class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                            Converted legacy report <span class="text-amber-500">·</span> Original category: {{ ucwords(str_replace('_', ' ', $report->category)) }}
                        </div>
                    @endif

                    <div class="mt-4 rounded-xl bg-[#F8FAF9] p-4 sm:p-5">
                        <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ $report->description }}</p>
                        @if($report->steps_to_reproduce)
                            <details class="mt-4 border-t border-[#E5EEEA] pt-3">
                                <summary class="cursor-pointer text-xs font-bold text-[#08745D]">Steps to reproduce</summary>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $report->steps_to_reproduce }}</p>
                            </details>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        @if($report->has_screenshot)
                            <a target="_blank" rel="noopener noreferrer" href="{{ route('owner.bug-reports.screenshot', $report) }}" class="inline-flex items-center gap-2 rounded-lg border border-[#C8DED7] bg-white px-3.5 py-2.5 text-xs font-bold text-[#08745D] transition hover:bg-[#F0F8F5]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>
                                View screenshot
                            </a>
                        @endif
                    </div>

                    @if($report->public_update)
                        <div class="mt-5 flex gap-3 rounded-xl border border-emerald-100 bg-[#F0FAF5] p-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-[#00A87E] shadow-sm">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 10h8m-8 4h5m-8 6-2 1 1-4a8 8 0 1 1 3 2Z"/></svg>
                            </span>
                            <div>
                                <p class="text-xs font-extrabold text-[#08745D]">Update from GastoTrack</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-[#315F55]">{{ $report->public_update }}</p>
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-xs text-slate-400">No update has been posted yet.</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-[#BBD9CF] bg-white px-6 py-12 text-center shadow-sm">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E6F7F0] text-[#08745D]">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 9h8v8a4 4 0 0 1-8 0V9Z"/><path d="M10 9a2 2 0 1 1 4 0M4 12h4m8 0h4M5 7l3 2m11-2-3 2"/></svg>
                </span>
                <h3 class="mt-4 text-base font-extrabold text-[#0A2E2A]">No reports yet</h3>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">When your business submits a bug report, its status and support updates will appear here.</p>
            </div>
        @endforelse

        @if($reports->hasPages())
            <div class="rounded-xl border border-[#DDE9E5] bg-white p-4 shadow-sm">{{ $reports->links() }}</div>
        @endif
    </section>
</div>
        </div>
    </main>
</div>
</body>
</html>
