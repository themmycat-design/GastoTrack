<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="gt-welcome-scroll">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Track sales, expenses, inventory, and staff activity for coffee, milk tea, and bake shops with GastoTrack.">
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <title>GastoTrack | Run your shop with clarity</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-welcome bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
    <header class="sticky top-0 z-30 border-b border-white/10 bg-[#071C19]/95 text-white backdrop-blur-xl">
        <div class="border-b border-white/10 bg-[#00A87E] px-4 py-2 text-center text-[11px] font-bold tracking-wide text-[#062C24] sm:text-xs">Built for coffee, milk tea, and bake shops <span class="mx-2 opacity-50" aria-hidden="true">·</span> <a href="#features" class="gt-focus underline underline-offset-2 hover:text-white">Explore GastoTrack <span aria-hidden="true">→</span></a></div>
        <nav class="gt-welcome-frame flex h-20 items-center justify-between gap-4" aria-label="Main navigation">
            <a href="{{ url('/') }}" class="gt-focus inline-flex items-center gap-2.5 rounded-lg text-xl font-extrabold tracking-tight text-white">
                <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-9 w-9 object-contain">
                GastoTrack
            </a>

            <div class="hidden items-center gap-6 lg:flex">
                <a href="#top" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Overview</a>
                <a href="#features" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Features</a>
                <a href="#workflow" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">How it works</a>
                <a href="#get-started" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Get started</a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                @auth
                    @if(Auth::user()->isSuperAdmin())
                        <a href="{{ route('super-admin.dashboard') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#08745D]">Open dashboard</a>
                    @elseif(Auth::user()->isOwner())
                        <a href="{{ route('dashboard') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#08745D]">Open dashboard</a>
                    @else
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="gt-focus rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/10">Sign out</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="gt-focus rounded-xl px-3 py-2.5 text-sm font-bold text-white/80 transition hover:bg-white/10 hover:text-white">Sign in</a>
                    <a href="{{ route('register') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#08745D]">Get started</a>
                @endauth
            </div>
        </nav>
        <nav class="gt-welcome-frame flex gap-5 overflow-x-auto border-t border-white/10 py-2.5 text-xs font-semibold text-white/75 lg:hidden" aria-label="Page sections">
            <a href="#top" class="gt-focus shrink-0 hover:text-[#72E6C4]">Overview</a>
            <a href="#features" class="gt-focus shrink-0 hover:text-[#72E6C4]">Features</a>
            <a href="#workflow" class="gt-focus shrink-0 hover:text-[#72E6C4]">How it works</a>
            <a href="#get-started" class="gt-focus shrink-0 hover:text-[#72E6C4]">Get started</a>
        </nav>
    </header>

    <main>
        <section id="top" class="gt-welcome-hero relative isolate overflow-hidden border-b border-white/10 text-white">
            <div class="gt-welcome-frame relative z-10 flex min-h-[620px] flex-col items-center justify-center py-20 text-center sm:min-h-[720px] sm:py-28">
                <div class="gt-enter inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-2 text-xs font-semibold tracking-wide text-[#9BE9D0]">
                    <span class="h-2 w-2 rounded-full bg-[#00C897]"></span>
                    A clearer view of your shop
                </div>
                <h1 class="gt-welcome-title gt-enter gt-enter-1 mx-auto mt-8 text-white">
                    Turn everyday records into <em class="not-italic text-[#65DDB6]">business clarity.</em>
                </h1>
                <p class="gt-enter gt-enter-2 mx-auto mt-7 max-w-2xl text-base leading-7 text-white/65 sm:text-lg sm:leading-8">Bring sales, expenses, inventory, and staff activity together in one practical workspace for your food and beverage shop.</p>
                <div class="gt-enter gt-enter-3 mt-10 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row">
                    @guest
                        <a href="{{ route('register') }}" class="gt-focus inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#00C897] px-7 py-3.5 text-sm font-extrabold text-[#062C24] transition hover:-translate-y-0.5 hover:bg-[#65DDB6] sm:w-auto">Create owner workspace <span aria-hidden="true">&#8594;</span></a>
                        <a href="{{ route('login') }}" class="gt-focus inline-flex w-full items-center justify-center rounded-xl border border-white/25 px-7 py-3.5 text-sm font-bold text-white transition hover:bg-white/10 sm:w-auto">Sign in</a>
                    @else
                        @if(Auth::user()->isSuperAdmin())
                            <a href="{{ route('super-admin.dashboard') }}" class="gt-focus inline-flex items-center justify-center gap-2 rounded-xl bg-[#00C897] px-7 py-3.5 text-sm font-extrabold text-[#062C24] transition hover:bg-[#65DDB6]">Go to your workspace <span aria-hidden="true">&#8594;</span></a>
                        @elseif(Auth::user()->isOwner())
                            <a href="{{ route('dashboard') }}" class="gt-focus inline-flex items-center justify-center gap-2 rounded-xl bg-[#00C897] px-7 py-3.5 text-sm font-extrabold text-[#062C24] transition hover:bg-[#65DDB6]">Go to your workspace <span aria-hidden="true">&#8594;</span></a>
                        @else
                            <span class="rounded-xl border border-white/20 bg-white/5 px-5 py-3.5 text-sm font-semibold text-white/75">Staff tools are available in the mobile app</span>
                        @endif
                    @endauth
                </div>
                <div class="gt-enter gt-enter-4 mt-16 grid w-full max-w-4xl grid-cols-2 gap-0 border-t border-white/15 text-xs font-medium text-white/60 sm:mt-24 sm:grid-cols-3 sm:text-sm">
                    <span class="border-b border-white/15 py-4">Sales &amp; expenses</span>
                    <span class="border-b border-l border-white/15 py-4">Inventory visibility</span>
                    <span class="border-b border-white/15 py-4 sm:border-l">Staff operations</span>
                    <span class="border-b border-l border-white/15 py-4 sm:border-b-0 sm:border-l-0">E-wallet review</span>
                    <span class="border-b border-white/15 py-4 sm:border-b-0 sm:border-l">Receipt scanning</span>
                    <span class="border-b border-l border-white/15 py-4 sm:border-b-0">Business insights</span>
                </div>
            </div>
        </section>

        <section id="features" class="scroll-mt-24 bg-white py-16 sm:py-20 lg:py-24">
            <div class="gt-welcome-frame">
                <div class="gt-scroll-reveal flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div class="max-w-2xl">
                        <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#00A87E]">One connected workspace</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-4xl">The tools behind a smoother shop day.</h2>
                    </div>
                    <p class="max-w-md text-sm leading-6 text-slate-500">Staff manage daily operations while owners get a clearer view of the business—without mixing platform administration into private shop records.</p>
                </div>

                @php
                    $features = [
                        ['number' => '01', 'title' => 'Money tracking', 'text' => 'Organize sales and expenses by category, source, and date.', 'icon' => 'money'],
                        ['number' => '02', 'title' => 'Inventory control', 'text' => 'Watch ingredient levels and catch low stock before service is affected.', 'icon' => 'inventory'],
                        ['number' => '03', 'title' => 'Staff operations', 'text' => 'Give your team focused tools for orders, stock, and daily records.', 'icon' => 'staff'],
                        ['number' => '04', 'title' => 'E-wallet capture', 'text' => 'Review supported payment notifications and reduce manual encoding.', 'icon' => 'wallet'],
                        ['number' => '05', 'title' => 'Receipt OCR', 'text' => 'Extract receipt details, review them, then record an expense.', 'icon' => 'receipt'],
                        ['number' => '06', 'title' => 'Business insights', 'text' => 'Use sales, expenses, and stock data to guide everyday decisions.', 'icon' => 'insights'],
                    ];
                @endphp
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($features as $index => $feature)
                        <article class="gt-card gt-lift gt-welcome-feature gt-scroll-reveal group p-5 sm:p-6" style="--gt-reveal-delay: {{ ($index % 3) * 80 }}ms">
                            <div class="flex items-center justify-between">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#08745D] transition group-hover:bg-[#00C897] group-hover:text-white">
                                    @if($feature['icon'] === 'money')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="12" cy="12" r="3"/><path d="M7 9h.01M17 15h.01"/></svg>
                                    @elseif($feature['icon'] === 'inventory')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 12v8.5"/></svg>
                                    @elseif($feature['icon'] === 'staff')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.5a3.5 3.5 0 0 1 0 6.8M18 15a5.5 5.5 0 0 1 3.5 5"/></svg>
                                    @elseif($feature['icon'] === 'wallet')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6.5h15a2 2 0 0 1 2 2V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h13"/><path d="M16 11h5v5h-5a2.5 2.5 0 0 1 0-5Z"/><path d="M17.5 13.5h.01"/></svg>
                                    @elseif($feature['icon'] === 'receipt')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3.5h12V21l-3-1.8-3 1.8-3-1.8L6 21V3.5Z"/><path d="M9 8h6m-6 4h6m-6 4h3"/></svg>
                                    @else
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-6"/><path d="M15 7h4v4"/></svg>
                                    @endif
                                </span>
                                <span class="text-xs font-extrabold tracking-[.16em] text-[#9BB9AF]">{{ $feature['number'] }}</span>
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold text-[#0A2E2A]">{{ $feature['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $feature['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="workflow" class="scroll-mt-24 border-y border-[#DDE9E5] bg-[#F4F8F7] py-16 sm:py-20">
            <div class="gt-welcome-frame">
                <div class="gt-scroll-reveal max-w-2xl">
                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#00A87E]">A practical setup</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-4xl">Start simple. Build with real records.</h2>
                </div>
                <div class="mt-9 grid gap-4 lg:grid-cols-3">
                    @foreach([
                        ['step' => '01', 'title' => 'Create your workspace', 'text' => 'Register your business and open the owner dashboard.'],
                        ['step' => '02', 'title' => 'Set up shop operations', 'text' => 'Add ingredients, stock limits, products, and staff accounts.'],
                        ['step' => '03', 'title' => 'Review and improve', 'text' => 'Use current financial records and stock status to guide decisions.'],
                    ] as $step)
                        <article class="gt-card gt-lift gt-welcome-step gt-scroll-reveal p-5 sm:p-6" style="--gt-reveal-delay: {{ (int) $step['step'] * 80 }}ms">
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full bg-[#00C897] px-3 text-xs font-extrabold text-white">{{ $step['step'] }}</span>
                            <h3 class="mt-4 text-lg font-extrabold text-[#0A2E2A]">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $step['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="get-started" class="gt-welcome-frame scroll-mt-32 py-16 sm:py-20">
            <div class="gt-brand-panel gt-welcome-cta gt-scroll-reveal overflow-hidden rounded-[1.75rem] px-6 py-10 text-white sm:px-10 sm:py-14 lg:px-14">
                <div class="flex flex-col justify-between gap-7 lg:flex-row lg:items-center">
                    <div class="max-w-2xl">
                        <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#B8F2DE]">Bring your shop into focus</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Give your business numbers a useful home.</h2>
                        <p class="mt-4 max-w-xl text-sm leading-6 text-white/75 sm:text-base">Set up inventory and staff, keep daily records organized, and review your business from one workspace.</p>
                    </div>
                    @guest
                        <a href="{{ route('register') }}" class="gt-focus inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-extrabold text-[#08745D] transition hover:-translate-y-0.5 hover:bg-[#EAF8F2]">Create owner workspace <span aria-hidden="true">&#8594;</span></a>
                    @else
                        @if(Auth::user()->isSuperAdmin())
                            <a href="{{ route('super-admin.dashboard') }}" class="gt-focus inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-extrabold text-[#08745D] transition hover:-translate-y-0.5 hover:bg-[#EAF8F2]">Go to your workspace <span aria-hidden="true">&#8594;</span></a>
                        @elseif(Auth::user()->isOwner())
                            <a href="{{ route('dashboard') }}" class="gt-focus inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-extrabold text-[#08745D] transition hover:-translate-y-0.5 hover:bg-[#EAF8F2]">Go to your workspace <span aria-hidden="true">&#8594;</span></a>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-xl border border-white/25 bg-white/10 px-5 py-3.5 text-sm font-bold text-white">Staff tools are available in the mobile app</span>
                        @endif
                    @endauth
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-[#DDE9E5] bg-white py-7">
        <div class="gt-welcome-frame flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="text-slate-500"><span class="font-extrabold text-[#0A2E2A]">GastoTrack</span> <span class="px-1 text-[#9BB9AF]">·</span> Financial management for growing shops.</p>
            <p class="text-slate-400">&copy; {{ now()->year }} GastoTrack</p>
        </div>
    </footer>
    <button id="welcome-back-to-top" type="button" class="gt-welcome-back-to-top gt-focus" aria-label="Back to top" hidden>
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg>
        <span>Top</span>
    </button>
</body>
</html>
