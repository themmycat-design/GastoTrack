<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Track sales, expenses, inventory, and staff activity for coffee, milk tea, and bake shops with GastoTrack.">
    <title>GastoTrack | Run your shop with clarity</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
    <header class="sticky top-0 z-30 border-b border-[#DDE9E5] bg-white/90 backdrop-blur-xl">
        <nav class="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between gap-4 px-5 sm:px-8" aria-label="Main navigation">
            <a href="{{ url('/') }}" class="gt-focus inline-flex items-center gap-2.5 rounded-lg text-xl font-extrabold tracking-tight text-[#0A2E2A]">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#00C897] text-white shadow-sm">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 8h12v8a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V8Z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17M8 4c0 1 1 1.2 1 2.2M12 3c0 1 1 1.2 1 2.2"/></svg>
                </span>
                GastoTrack
            </a>

            <div class="hidden items-center gap-7 md:flex">
                <a href="#features" class="gt-focus rounded-md text-sm font-semibold text-slate-600 transition hover:text-[#08745D]">Features</a>
                <a href="#workflow" class="gt-focus rounded-md text-sm font-semibold text-slate-600 transition hover:text-[#08745D]">How it works</a>
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
                            <button class="gt-focus rounded-xl bg-[#EAF2EF] px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-[#DDE9E5]">Sign out</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="gt-focus rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-[#F4F8F7] hover:text-[#08745D]">Sign in</a>
                    <a href="{{ route('register') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#08745D]">Get started</a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        <section class="gt-hero-glow relative isolate overflow-hidden border-b border-[#DDE9E5]">
            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[1.02fr_.98fr] lg:gap-16 lg:py-24">
                <div class="gt-enter">
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#C8DED7] bg-white/90 px-3.5 py-2 text-xs font-bold text-[#08745D] shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-[#00C897]"></span>
                        Made for independent food and beverage shops
                    </div>
                    <h1 class="mt-6 max-w-2xl text-4xl font-extrabold leading-[1.08] tracking-tight text-[#0A2E2A] sm:text-5xl lg:text-[3.65rem]">
                        Run your shop with <span class="text-[#00A87E]">clarity.</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">Bring daily sales, expenses, stock, and staff activity into one practical workspace—so you can spend less time piecing together records and more time running your shop.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        @guest
                            <a href="{{ route('register') }}" class="gt-focus inline-flex items-center justify-center gap-2 rounded-xl bg-[#00A87E] px-6 py-3.5 text-sm font-extrabold text-white shadow-lg shadow-[#08745D]/15 transition hover:-translate-y-0.5 hover:bg-[#08745D]">Create owner workspace <span aria-hidden="true">&#8594;</span></a>
                            <a href="{{ route('login') }}" class="gt-focus inline-flex items-center justify-center rounded-xl border border-[#C8DED7] bg-white/90 px-6 py-3.5 text-sm font-extrabold text-[#08745D] transition hover:bg-white">Sign in</a>
                        @else
                            @if(Auth::user()->isSuperAdmin())
                                <a href="{{ route('super-admin.dashboard') }}" class="gt-focus inline-flex items-center justify-center gap-2 rounded-xl bg-[#00A87E] px-6 py-3.5 text-sm font-extrabold text-white shadow-lg shadow-[#08745D]/15 transition hover:-translate-y-0.5 hover:bg-[#08745D]">Go to your workspace <span aria-hidden="true">&#8594;</span></a>
                            @elseif(Auth::user()->isOwner())
                                <a href="{{ route('dashboard') }}" class="gt-focus inline-flex items-center justify-center gap-2 rounded-xl bg-[#00A87E] px-6 py-3.5 text-sm font-extrabold text-white shadow-lg shadow-[#08745D]/15 transition hover:-translate-y-0.5 hover:bg-[#08745D]">Go to your workspace <span aria-hidden="true">&#8594;</span></a>
                            @else
                                <span class="inline-flex items-center rounded-xl border border-[#C8DED7] bg-white/90 px-5 py-3.5 text-sm font-bold text-slate-600">Staff tools are available in the mobile app</span>
                            @endif
                        @endauth
                        <a href="#features" class="gt-focus inline-flex items-center justify-center rounded-xl px-5 py-3.5 text-sm font-bold text-slate-600 transition hover:bg-white/70 hover:text-[#08745D]">Explore features</a>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-slate-500">
                        <span class="inline-flex items-center gap-2"><span class="text-[#00A87E]">&#10003;</span> Coffee shops</span>
                        <span class="inline-flex items-center gap-2"><span class="text-[#00A87E]">&#10003;</span> Milk tea shops</span>
                        <span class="inline-flex items-center gap-2"><span class="text-[#00A87E]">&#10003;</span> Bake shops</span>
                    </div>
                </div>

                <div class="gt-enter gt-enter-2 relative mx-auto w-full max-w-xl lg:max-w-none">
                    <div class="absolute -right-5 -top-7 h-28 w-28 rounded-full bg-[#00C897]/20 blur-3xl" aria-hidden="true"></div>
                    <div class="gt-card relative rounded-[1.75rem] p-3 shadow-2xl shadow-[#0A2E2A]/10 sm:p-5">
                        <div class="flex items-center justify-between gap-3 px-2 pb-4 pt-2 sm:px-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#EAF8F2] text-[#08745D]">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3.5 20V9l8.5-5 8.5 5v11M8 20v-6h8v6M3.5 10h17"/></svg>
                                </span>
                                <div>
                                    <p class="text-sm font-extrabold text-[#0A2E2A]">Business overview</p>
                                    <p class="mt-0.5 text-xs text-slate-500">A sample dashboard preview</p>
                                </div>
                            </div>
                            <span class="rounded-full border border-[#C8DED7] bg-[#F4F8F7] px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-[#08745D]">Example data</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-[#DDE9E5] bg-[#F8FCFA] p-4 sm:p-5">
                                <p class="text-xs font-semibold text-slate-500">Revenue</p>
                                <p class="mt-2 text-xl font-extrabold tracking-tight text-[#08745D] sm:text-2xl">&#8369;12,450</p>
                                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-[#E1EFE9]"><div class="h-full w-4/5 rounded-full bg-[#00C897]"></div></div>
                            </div>
                            <div class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4 sm:p-5">
                                <p class="text-xs font-semibold text-slate-500">Expenses</p>
                                <p class="mt-2 text-xl font-extrabold tracking-tight text-rose-600 sm:text-2xl">&#8369;3,200</p>
                                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-rose-100"><div class="h-full w-1/3 rounded-full bg-rose-400"></div></div>
                            </div>
                        </div>

                        <div class="gt-brand-panel mt-3 overflow-hidden rounded-2xl p-5 text-white sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold text-white/70">Example net profit</p>
                                    <p class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">&#8369;9,250</p>
                                </div>
                                <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white/90">Income − expenses</span>
                            </div>
                            <div class="mt-5 flex h-12 items-end gap-1.5" aria-hidden="true">
                                <span class="h-[34%] flex-1 rounded-t bg-white/25"></span><span class="h-[48%] flex-1 rounded-t bg-white/35"></span><span class="h-[40%] flex-1 rounded-t bg-white/30"></span><span class="h-[68%] flex-1 rounded-t bg-white/45"></span><span class="h-[57%] flex-1 rounded-t bg-white/40"></span><span class="h-[82%] flex-1 rounded-t bg-white/60"></span><span class="h-full flex-1 rounded-t bg-white/85"></span><span class="h-[74%] flex-1 rounded-t bg-white/55"></span><span class="h-[91%] flex-1 rounded-t bg-white/75"></span><span class="h-[85%] flex-1 rounded-t bg-white/60"></span><span class="h-full flex-1 rounded-t bg-white/90"></span><span class="h-[78%] flex-1 rounded-t bg-white/60"></span>
                            </div>
                            <div class="mt-2 flex justify-between text-[10px] font-semibold text-white/60"><span>Monthly activity</span><span>Illustrative preview</span></div>
                        </div>

                        <div class="mt-3 flex items-center gap-3 rounded-2xl border border-amber-100 bg-amber-50/80 p-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 9 16H3l9-16Z"/><path d="M12 9v4m0 3h.01"/></svg>
                            </span>
                            <div class="min-w-0 flex-1"><p class="text-sm font-bold text-[#0A2E2A]">Stock needs attention</p><p class="mt-0.5 text-xs text-slate-600">Low-stock items are easier to spot.</p></div>
                            <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[10px] font-extrabold text-amber-700">Inventory</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="scroll-mt-24 bg-white py-16 sm:py-20 lg:py-24">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
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
                        <article class="gt-card gt-lift gt-enter gt-enter-{{ min($index + 1, 4) }} group p-5 sm:p-6">
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
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="max-w-2xl">
                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#00A87E]">A practical setup</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-4xl">Start simple. Build with real records.</h2>
                </div>
                <div class="mt-9 grid gap-4 lg:grid-cols-3">
                    @foreach([
                        ['step' => '01', 'title' => 'Create your workspace', 'text' => 'Register your business and open the owner dashboard.'],
                        ['step' => '02', 'title' => 'Set up shop operations', 'text' => 'Add ingredients, stock limits, products, and staff accounts.'],
                        ['step' => '03', 'title' => 'Review and improve', 'text' => 'Use current financial records and stock status to guide decisions.'],
                    ] as $step)
                        <article class="gt-card gt-lift p-5 sm:p-6">
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full bg-[#00C897] px-3 text-xs font-extrabold text-white">{{ $step['step'] }}</span>
                            <h3 class="mt-4 text-lg font-extrabold text-[#0A2E2A]">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $step['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="px-5 py-16 sm:px-8 sm:py-20">
            <div class="gt-brand-panel mx-auto max-w-7xl overflow-hidden rounded-[1.75rem] px-6 py-10 text-white shadow-xl shadow-[#0A2E2A]/10 sm:px-10 sm:py-14 lg:px-14">
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
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <p class="text-slate-500"><span class="font-extrabold text-[#0A2E2A]">GastoTrack</span> <span class="px-1 text-[#9BB9AF]">·</span> Financial management for growing shops.</p>
            <p class="text-slate-400">&copy; {{ now()->year }} GastoTrack</p>
        </div>
    </footer>
</body>
</html>
