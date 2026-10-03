<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GastoTrack | Financial clarity for growing shops</title>
    <meta name="description" content="Track sales, expenses, inventory, and staff activity for coffee, milk tea, and bake shops.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f4f8f7] font-sans text-gray-900 antialiased">
    <nav class="sticky top-0 z-20 border-b border-emerald-100 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-8">
            <a href="{{ url('/') }}" class="text-2xl font-extrabold tracking-tight text-emerald-700">GastoTrack</a>
            <div class="flex items-center gap-2 sm:gap-3">
                @auth
                    @if(Auth::user()->isSuperAdmin())
                        <a href="{{ route('super-admin.dashboard') }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Open dashboard</a>
                    @elseif(Auth::user()->isOwner())
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Open dashboard</a>
                    @else
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-200">Sign out</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="rounded-xl px-3 py-2.5 text-sm font-bold text-gray-600 hover:bg-gray-100 hover:text-emerald-700">Sign in</a>
                    <a href="{{ route('register') }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Create account</a>
                @endauth
            </div>
        </div>
    </nav>

    <main>
        <section class="relative overflow-hidden border-b border-emerald-100 bg-gradient-to-br from-white via-emerald-50 to-[#dff7ef]">
            <div class="absolute -right-40 top-10 h-96 w-96 rounded-full bg-emerald-200/30 blur-3xl"></div>
            <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.05fr_.95fr] lg:py-28">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Built for small food and beverage businesses
                    </div>
                    <h1 class="mt-6 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight text-gray-950 sm:text-6xl">
                        Understand your money. Control your stock. <span class="text-emerald-700">Grow with confidence.</span>
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-gray-600">GastoTrack brings sales, expenses, inventory, staff activity, e-wallet payments, and business insights into one practical workspace.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="rounded-xl bg-emerald-600 px-6 py-3.5 text-center text-sm font-extrabold text-white shadow-sm hover:bg-emerald-700">Create your owner workspace</a>
                        <a href="#features" class="rounded-xl border border-emerald-200 bg-white px-6 py-3.5 text-center text-sm font-extrabold text-emerald-800 hover:bg-emerald-50">See what it manages</a>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-gray-500">
                        <span>✓ Coffee shops</span>
                        <span>✓ Milk tea shops</span>
                        <span>✓ Bake shops</span>
                    </div>
                </div>

                <div class="relative">
                    <div class="rounded-[2rem] border border-white bg-white/90 p-4 shadow-2xl shadow-emerald-900/10 backdrop-blur sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div><p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Business overview</p><p class="mt-1 text-lg font-extrabold">Today at a glance</p></div>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Live</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-emerald-50 p-4"><p class="text-xs font-semibold text-gray-500">Revenue</p><p class="mt-2 text-2xl font-extrabold text-emerald-700">&#8369;12,450</p></div>
                            <div class="rounded-2xl bg-rose-50 p-4"><p class="text-xs font-semibold text-gray-500">Expenses</p><p class="mt-2 text-2xl font-extrabold text-rose-600">&#8369;3,200</p></div>
                        </div>
                        <div class="mt-3 rounded-2xl bg-gray-900 p-5 text-white">
                            <p class="text-xs font-semibold text-gray-400">Estimated net profit</p>
                            <div class="mt-2 flex items-end justify-between gap-4"><p class="text-3xl font-extrabold">&#8369;9,250</p><p class="text-xs font-bold text-emerald-300">Healthy day</p></div>
                        </div>
                        <div class="mt-3 flex items-center justify-between rounded-2xl border border-amber-100 bg-amber-50 p-4">
                            <div><p class="text-sm font-bold text-gray-900">2 ingredients need attention</p><p class="mt-1 text-xs text-gray-500">Milk and matcha are below minimum stock.</p></div>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700">Low stock</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="bg-white py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-extrabold uppercase tracking-[.18em] text-emerald-700">One connected system</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Built around the way your shop actually works.</h2>
                    <p class="mt-4 text-lg leading-8 text-gray-600">Staff handle daily operations. Owners see the financial picture and make better decisions.</p>
                </div>

                @php
                    $features = [
                        ['number' => '01', 'title' => 'Money tracking', 'text' => 'Keep sales and expenses organized by category, payment source, and date.'],
                        ['number' => '02', 'title' => 'Inventory control', 'text' => 'Monitor ingredient levels and catch low stock before it interrupts service.'],
                        ['number' => '03', 'title' => 'Staff operations', 'text' => 'Give staff focused access to ordering, transactions, stock, and receipt scanning.'],
                        ['number' => '04', 'title' => 'E-wallet capture', 'text' => 'Use supported payment notifications to reduce manual transaction encoding.'],
                        ['number' => '05', 'title' => 'Receipt OCR', 'text' => 'Scan receipts and review extracted details before recording an expense.'],
                        ['number' => '06', 'title' => 'Business insights', 'text' => 'Turn your sales, spending, and inventory data into useful explanations and actions.'],
                    ];
                @endphp
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($features as $feature)
                        <article class="rounded-2xl border border-gray-100 bg-[#f8fbfa] p-6 transition hover:-translate-y-1 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-900/5">
                            <p class="text-xs font-extrabold tracking-[.18em] text-emerald-600">{{ $feature['number'] }}</p>
                            <h3 class="mt-5 text-xl font-extrabold text-gray-900">{{ $feature['title'] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-gray-600">{{ $feature['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="border-y border-emerald-100 bg-[#f4f8f7] py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="grid gap-10 lg:grid-cols-3">
                    <div><p class="text-sm font-extrabold text-emerald-700">Step 1</p><h3 class="mt-3 text-xl font-extrabold">Create the owner workspace</h3><p class="mt-3 text-sm leading-6 text-gray-600">Register your business and open the management dashboard.</p></div>
                    <div><p class="text-sm font-extrabold text-emerald-700">Step 2</p><h3 class="mt-3 text-xl font-extrabold">Configure your daily operations</h3><p class="mt-3 text-sm leading-6 text-gray-600">Add ingredients, stock limits, and staff accounts.</p></div>
                    <div><p class="text-sm font-extrabold text-emerald-700">Step 3</p><h3 class="mt-3 text-xl font-extrabold">Use real data to improve</h3><p class="mt-3 text-sm leading-6 text-gray-600">Review financial results, stock risks, and business insights regularly.</p></div>
                </div>
            </div>
        </section>

        <section class="bg-emerald-700 py-20 text-white">
            <div class="mx-auto max-w-4xl px-5 text-center sm:px-8">
                <p class="text-sm font-extrabold uppercase tracking-[.18em] text-emerald-200">Start with clarity</p>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl">Give your business numbers a useful home.</h2>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-emerald-100">Create your owner account, then set up inventory, and staff from one workspace.</p>
                <a href="{{ route('register') }}" class="mt-8 inline-block rounded-xl bg-white px-6 py-3.5 text-sm font-extrabold text-emerald-800 hover:bg-emerald-50">Create owner workspace</a>
            </div>
        </section>
    </main>

    <footer class="bg-gray-950 py-8 text-gray-400">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <p><span class="font-extrabold text-white">GastoTrack</span> · Financial management for growing shops.</p>
            <p>&copy; {{ now()->year }} GastoTrack.</p>
        </div>
    </footer>
</body>
</html>
