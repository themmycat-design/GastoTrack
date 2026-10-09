<header class="sticky top-0 z-20 border-b border-[#DDE9E5] bg-white/90 backdrop-blur-xl">
    <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:px-10">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">Owner workspace</p>
            <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-2xl">Good day, {{ Auth::user()->name }}</h1>
            <p class="mt-1 truncate text-sm text-slate-500">{{ Auth::user()->business?->name ?? 'Your business' }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <span class="hidden items-center gap-2 rounded-full border border-[#DDE9E5] bg-[#F4F8F7] px-3.5 py-2 text-sm font-semibold text-slate-600 sm:inline-flex">
                <svg class="h-4 w-4 text-[#00A87E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M7.5 3v4M16.5 3v4M3.5 10h17"/></svg>
                {{ now()->format('F Y') }}
            </span>
            <a href="{{ route('profile.edit') }}" class="gt-focus flex h-10 w-10 items-center justify-center rounded-full bg-[#00C897] text-sm font-extrabold text-white shadow-sm transition hover:bg-[#00A87E] lg:hidden" aria-label="Open profile">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </a>
        </div>
    </div>
</header>
