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
        <nav class="gt-welcome-frame flex h-20 items-center justify-between gap-4" aria-label="Main navigation">
            <a href="{{ url('/') }}" class="gt-focus inline-flex items-center gap-2.5 rounded-lg text-xl font-extrabold tracking-tight text-white">
                <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-9 w-9 object-contain">
                GastoTrack
            </a>

            <div class="hidden items-center gap-4 lg:flex">
                <a href="#top" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Overview</a>
                <a href="#features" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Features</a>
                <a href="#workflow" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">How it works</a>
                <a href="#get-started" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Get started</a>
                <a href="#testimonial" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Testimonial</a>
                <a href="#download" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Download app</a>
                <a href="#contact" class="gt-focus rounded-md text-sm font-semibold text-white/70 transition hover:text-[#72E6C4]">Contact</a>
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
            <a href="#testimonial" class="gt-focus shrink-0 hover:text-[#72E6C4]">Testimonial</a>
            <a href="#download" class="gt-focus shrink-0 hover:text-[#72E6C4]">Download app</a>
            <a href="#contact" class="gt-focus shrink-0 hover:text-[#72E6C4]">Contact</a>
        </nav>
    </header>

    <main>
        <section id="top" class="gt-welcome-hero scroll-mt-32 lg:scroll-mt-24 relative isolate overflow-hidden border-b border-white/10 text-white">
            <div id="welcome-cursor-glow" class="gt-welcome-cursor-glow" aria-hidden="true"></div>
            <div class="gt-welcome-sparks gt-welcome-sparks-left" aria-hidden="true">
                @for($spark = 0; $spark < 10; $spark++)
                    <span></span>
                @endfor
            </div>
            <div class="gt-welcome-sparks gt-welcome-sparks-right" aria-hidden="true">
                @for($spark = 0; $spark < 10; $spark++)
                    <span></span>
                @endfor
            </div>
            <div class="gt-welcome-frame relative z-10 grid min-h-[620px] items-center gap-x-12 gap-y-12 py-16 lg:min-h-[720px] lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-x-16 lg:py-24">
                <div class="min-w-0 text-center lg:text-left">
                <div class="gt-enter inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-2 text-xs font-semibold tracking-wide text-[#9BE9D0]">
                    <span class="h-2 w-2 rounded-full bg-[#00C897]"></span>
                    A clearer view of your shop
                </div>
                <h1 class="gt-welcome-title gt-enter gt-enter-1 mx-auto mt-8 text-white lg:mx-0">
                    Turn everyday records into <em class="not-italic text-[#65DDB6]">business clarity.</em>
                </h1>
                <p class="gt-enter gt-enter-2 mx-auto mt-7 max-w-2xl text-base leading-7 text-white/70 sm:text-lg sm:leading-8 lg:mx-0">Bring sales, expenses, inventory, and staff activity together in one practical workspace for your food and beverage shop.</p>
                <div class="gt-enter gt-enter-3 mt-10 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row lg:justify-start">
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
                <div class="gt-enter gt-enter-4 mt-12 grid w-full max-w-4xl grid-cols-2 gap-0 border-t border-white/15 text-xs font-medium text-white/60 sm:grid-cols-3 sm:text-sm lg:mt-16">
                    <span class="border-b border-white/15 py-4">Sales &amp; expenses</span>
                    <span class="border-b border-l border-white/15 py-4">Inventory visibility</span>
                    <span class="border-b border-white/15 py-4 sm:border-l">Staff operations</span>
                    <span class="border-b border-l border-white/15 py-4 sm:border-b-0 sm:border-l-0">E-wallet review</span>
                    <span class="border-b border-white/15 py-4 sm:border-b-0 sm:border-l">Receipt scanning</span>
                    <span class="border-b border-l border-white/15 py-4 sm:border-b-0">Business insights</span>
                </div>
                </div>
                <div class="gt-enter gt-enter-2 gt-welcome-illustration mx-auto w-full max-w-[640px]">
                    <img src="{{ asset('images/gastotrack-hero.svg') }}?v={{ filemtime(public_path('images/gastotrack-hero.svg')) }}" alt="Preview of the GastoTrack owner Overview and staff Home screens with illustrative data" width="640" height="560" class="h-auto w-full" decoding="async">
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
                    <p class="max-w-md text-sm leading-6 text-slate-500">Staff manage daily operations while owners get a clearer view of the business, without mixing platform administration into private shop records.</p>
                </div>

                @php
                    $features = [
                        ['number' => '01', 'title' => 'Money tracking', 'text' => 'Organize sales and expenses by category, source, and date.', 'icon' => 'money'],
                        ['number' => '02', 'title' => 'Inventory control', 'text' => 'Watch ingredient levels and catch low stock before service is affected.', 'icon' => 'inventory'],
                        ['number' => '03', 'title' => 'Staff operations', 'text' => 'Give your team focused tools for orders, stock, and daily records.', 'icon' => 'staff'],
                        ['number' => '04', 'title' => 'E-wallet capture', 'text' => 'Review supported payment notifications and reduce manual encoding.', 'icon' => 'wallet'],
                        ['number' => '05', 'title' => 'Receipt OCR', 'text' => 'Read receipt details and automatically categorize an expense when scanning succeeds.', 'icon' => 'receipt'],
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
                        ['step' => '01', 'title' => 'Create your workspace', 'text' => 'Register, verify your email, and wait for business approval.'],
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

        <section id="testimonial" class="scroll-mt-32 border-t border-[#DDE9E5] bg-white lg:scroll-mt-24" aria-labelledby="welcome-feedback-title">
            <div class="gt-welcome-frame gt-scroll-reveal py-12 text-center sm:py-16">
                <p class="text-xs font-extrabold uppercase tracking-[.2em] text-[#008F70]">Made for your kind of shop</p>
                <h2 id="welcome-feedback-title" class="mx-auto mt-4 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight text-[#0A2E2A] sm:text-5xl">From the first order to the final count.</h2>
                <p class="mx-auto mt-5 max-w-2xl text-base leading-8 text-slate-600">Milk tea, coffee, and bake shops each have their own rhythm. GastoTrack helps bring the day's work together.</p>
            </div>

            <div class="grid grid-cols-1 gap-1 bg-[#071C19] sm:grid-cols-3" role="group" aria-label="Milk tea, coffee, and bake shop products">
                <button type="button" class="gt-focus gt-scroll-reveal gt-testimonial-photo-frame relative block aspect-[3/2] w-full overflow-hidden bg-[#071C19] p-0 text-left sm:aspect-square" data-gt-flip-card data-gt-reveal-once="true" aria-label="Show details about milk tea" aria-pressed="false">
                    <span class="gt-testimonial-flip-inner block h-full w-full">
                        <span class="gt-testimonial-flip-face absolute inset-0 block" aria-hidden="true">
                            <img src="{{ asset('images/shop-milk-tea.jpg') }}" alt="" class="gt-testimonial-photo block h-full w-full object-cover" width="724" height="724" loading="lazy" decoding="async">
                            <span class="gt-testimonial-photo-shade" aria-hidden="true"></span>
                            <span class="absolute inset-x-0 bottom-0 block px-5 pb-4 text-white sm:pb-5">
                                <span data-gt-product class="block text-lg font-extrabold">Milk tea</span>
                                <span class="mt-1 block text-xs font-semibold text-white/85">View details &#8599;</span>
                            </span>
                        </span>
                        <span class="gt-testimonial-flip-face gt-testimonial-flip-back absolute inset-0 flex flex-col items-center justify-center bg-[#0A2E2A] px-5 py-4 text-center text-white sm:px-3 sm:py-3 lg:px-8 lg:py-4" aria-hidden="true">
                            <span class="block text-xl font-extrabold sm:text-lg lg:text-2xl">Milk tea</span>
                            <span data-gt-description class="mt-3 block max-w-xs text-sm leading-snug text-white/85 sm:mt-2 sm:text-xs lg:mt-3 lg:text-base">Creamy iced tea and chewy tapioca pearls, made for a refreshing break between busy moments.</span>
                        </span>
                    </span>
                </button>
                <button type="button" class="gt-focus gt-scroll-reveal gt-testimonial-photo-frame relative block aspect-[3/2] w-full overflow-hidden bg-[#071C19] p-0 text-left sm:aspect-square" data-gt-flip-card data-gt-reveal-once="true" style="--gt-reveal-delay: 90ms" aria-label="Show details about coffee" aria-pressed="false">
                    <span class="gt-testimonial-flip-inner block h-full w-full">
                        <span class="gt-testimonial-flip-face absolute inset-0 block" aria-hidden="true">
                            <img src="{{ asset('images/shop-coffee.jpg') }}" alt="" class="gt-testimonial-photo block h-full w-full object-cover" width="724" height="724" loading="lazy" decoding="async">
                            <span class="gt-testimonial-photo-shade" aria-hidden="true"></span>
                            <span class="absolute inset-x-0 bottom-0 block px-5 pb-4 text-white sm:pb-5">
                                <span data-gt-product class="block text-lg font-extrabold">Coffee</span>
                                <span class="mt-1 block text-xs font-semibold text-white/85">View details &#8599;</span>
                            </span>
                        </span>
                        <span class="gt-testimonial-flip-face gt-testimonial-flip-back absolute inset-0 flex flex-col items-center justify-center bg-[#0A2E2A] px-5 py-4 text-center text-white sm:px-3 sm:py-3 lg:px-8 lg:py-4" aria-hidden="true">
                            <span class="block text-xl font-extrabold sm:text-lg lg:text-2xl">Coffee</span>
                            <span data-gt-description class="mt-3 block max-w-xs text-sm leading-snug text-white/85 sm:mt-2 sm:text-xs lg:mt-3 lg:text-base">A warm latte with smooth espresso, steamed milk, and a little art on top.</span>
                        </span>
                    </span>
                </button>
                <button type="button" class="gt-focus gt-scroll-reveal gt-testimonial-photo-frame relative block aspect-[3/2] w-full overflow-hidden bg-[#071C19] p-0 text-left sm:aspect-square" data-gt-flip-card data-gt-reveal-once="true" style="--gt-reveal-delay: 180ms" aria-label="Show details about fresh bakes" aria-pressed="false">
                    <span class="gt-testimonial-flip-inner block h-full w-full">
                        <span class="gt-testimonial-flip-face absolute inset-0 block" aria-hidden="true">
                            <img src="{{ asset('images/shop-bake-shop.jpg') }}" alt="" class="gt-testimonial-photo block h-full w-full object-cover" width="724" height="724" loading="lazy" decoding="async">
                            <span class="gt-testimonial-photo-shade" aria-hidden="true"></span>
                            <span class="absolute inset-x-0 bottom-0 block px-5 pb-4 text-white sm:pb-5">
                                <span data-gt-product class="block text-lg font-extrabold">Fresh bakes</span>
                                <span class="mt-1 block text-xs font-semibold text-white/85">View details &#8599;</span>
                            </span>
                        </span>
                        <span class="gt-testimonial-flip-face gt-testimonial-flip-back absolute inset-0 flex flex-col items-center justify-center bg-[#0A2E2A] px-5 py-4 text-center text-white sm:px-3 sm:py-3 lg:px-8 lg:py-4" aria-hidden="true">
                            <span class="block text-xl font-extrabold sm:text-lg lg:text-2xl">Fresh bakes</span>
                            <span data-gt-description class="mt-3 block max-w-xs text-sm leading-snug text-white/85 sm:mt-2 sm:text-xs lg:mt-3 lg:text-base">Flaky croissants and fresh pastries, ready for a quick treat or an unhurried morning.</span>
                        </span>
                    </span>
                </button>
            </div>

            <div class="relative overflow-hidden bg-[#071C19] py-16 text-white sm:py-20 lg:py-24">
                <div class="pointer-events-none absolute left-1/2 top-0 h-64 w-96 -translate-x-1/2 rounded-full bg-[#00A87E]/15 blur-3xl" aria-hidden="true"></div>
                <div class="gt-welcome-frame relative">
                    <figure class="gt-scroll-reveal mx-auto max-w-4xl text-center">
                        <p class="text-xs font-extrabold uppercase tracking-[.2em] text-[#72E6C4]">Why we built GastoTrack</p>
                        <span class="mt-5 block font-serif text-7xl leading-none text-[#72E6C4]" aria-hidden="true">&ldquo;</span>
                        <blockquote class="mt-2 text-2xl font-semibold leading-snug tracking-tight sm:text-3xl lg:text-4xl">Every order tells a story. GastoTrack brings sales, ingredients, expenses, and staff activity together so shop owners can see the day clearly and decide what comes next.</blockquote>
                        <figcaption class="mt-9 flex items-center justify-center gap-3 border-t border-white/15 pt-7">
                            <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-11 w-11 object-contain" width="44" height="44" loading="lazy">
                            <span class="text-left"><strong class="block text-sm font-bold">GastoTrack team</strong><span class="mt-0.5 block text-xs text-white/60">Our purpose for everyday shop operations</span></span>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section id="download" class="scroll-mt-24 border-t border-[#DDE9E5] bg-white py-12 sm:py-16 lg:py-20" aria-labelledby="download-title">
            <div class="gt-welcome-frame grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)] lg:gap-20">
                <div class="gt-scroll-reveal max-w-2xl">
                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#008F70]">Staff Android app</p>
                    <h2 id="download-title" class="mt-4 text-5xl font-extrabold leading-[1.04] tracking-tight text-[#0A2E2A] sm:text-6xl lg:text-7xl">Ready for a smoother shop day?</h2>
                    <p class="mt-7 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">Install GastoTrack on an Android phone to handle orders, expenses, and stock during service. Staff accounts are created by the business owner.</p>
                    <p class="mt-7 flex items-start gap-2 text-sm leading-6 text-slate-500">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#08745D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        Built for participating food and beverage shops in Calasiao, Pangasinan.
                    </p>
                </div>
                <div class="gt-scroll-reveal rounded-2xl border border-[#DDE9E5] bg-white p-6 shadow-[0_18px_45px_rgba(10,46,42,0.08)] sm:p-8" style="--gt-reveal-delay: 90ms">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-[#DDE9E5] bg-[#F4F8F7]">
                            <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-11 w-11 object-contain">
                        </div>
                        <div>
                            <h3 class="text-xl font-extrabold text-[#0A2E2A]">GastoTrack Staff</h3>
                            <p class="mt-1 text-sm text-slate-500">Android APK for testers</p>
                        </div>
                    </div>
                    <div class="mt-8 space-y-3">
                        <a href="{{ route('download-app') }}" class="gt-focus flex min-h-14 items-center justify-between gap-3 rounded-xl bg-[#0A2E2A] px-5 text-sm font-bold text-white transition hover:bg-[#08745D]">
                            Download APK
                            <span aria-hidden="true">&#8594;</span>
                        </a>
                    </div>
                    <p class="mt-6 text-xs leading-5 text-slate-500">Follow the download and installation steps for testers. Read the <a href="{{ route('terms') }}" class="gt-focus underline underline-offset-2 hover:text-[#08745D]">Terms</a> and <a href="{{ route('privacy') }}" class="gt-focus underline underline-offset-2 hover:text-[#08745D]">Privacy Policy</a>.</p>
                </div>
            </div>
        </section>
        <section id="contact" class="scroll-mt-24 border-t border-[#DDE9E5] bg-[#F4F8F7] py-16 sm:py-20 lg:py-24" aria-labelledby="contact-title">
            <div class="gt-welcome-frame grid items-start gap-10 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1fr)] lg:gap-20">
                <div class="gt-scroll-reveal max-w-xl">
                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#008F70]">Questions and support</p>
                    <h2 id="contact-title" class="mt-4 text-4xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-5xl">Contact the GastoTrack team.</h2>
                    <p class="mt-5 text-base leading-8 text-slate-600">Ask about the project or your account. Your message will be emailed to the team, and we'll send a thank-you note to your email address.</p>
                    <p class="mt-5 text-sm leading-6 text-slate-500">Please leave out passwords, payment codes, and private transaction details.</p>
                </div>
                <div class="gt-scroll-reveal rounded-2xl border border-[#DDE9E5] bg-white p-6 shadow-[0_18px_45px_rgba(10,46,42,0.08)] sm:p-8" style="--gt-reveal-delay: 90ms">
                    <h3 class="text-xl font-extrabold text-[#0A2E2A]">Send a message</h3>
                    <div class="mt-5">@include('public.contact-form', ['source' => 'landing'])</div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-[#DDE9E5] bg-white py-8 sm:py-10">
        <div class="gt-welcome-frame">
            <div class="flex flex-col gap-5 text-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <a href="{{ url('/') }}" class="gt-focus text-lg font-extrabold text-[#0A2E2A]">GastoTrack<span class="text-[#00A87E]">.</span></a>
                    <p class="mt-1 text-xs text-slate-500">&copy; {{ now()->year }} GastoTrack</p>
                </div>
                <nav class="flex flex-wrap gap-x-6 gap-y-2 text-xs sm:text-sm" aria-label="Information links">
                    <a href="{{ route('terms') }}" class="gt-focus text-slate-600 hover:text-[#08745D] hover:underline">Terms and Conditions</a>
                    <a href="{{ route('privacy') }}" class="gt-focus text-slate-600 hover:text-[#08745D] hover:underline">Privacy Policy</a>
                </nav>
            </div>
        </div>
    </footer>
    <button id="welcome-back-to-top" type="button" class="gt-welcome-back-to-top gt-focus" aria-label="Back to top" hidden>
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg>
        <span>Top</span>
    </button>
    @if($errors->any() || session('contact_error'))
        <script>window.addEventListener('load', () => document.getElementById('contact')?.scrollIntoView());</script>
    @endif
</body>
</html>
