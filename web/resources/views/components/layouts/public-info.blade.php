<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? 'Information about GastoTrack.' }}">
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <title>{{ $title }} | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Skip to content</a>
    <header class="border-b border-white/10 bg-[#071C19] text-white">
        <nav class="gt-welcome-frame flex min-h-20 flex-wrap items-center justify-between gap-4 py-4" aria-label="Main navigation">
            <a href="{{ url('/') }}" class="gt-focus inline-flex items-center gap-2.5 rounded-lg text-xl font-extrabold tracking-tight text-white">
                <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-9 w-9 object-contain">
                GastoTrack
            </a>
            <div class="flex flex-wrap items-center gap-3 text-sm font-semibold sm:gap-5">
                <a href="{{ url('/') }}" class="gt-focus text-white/80 hover:text-[#72E6C4]">Home</a>
                <a href="{{ route('contact') }}" class="gt-focus text-white/80 hover:text-[#72E6C4]">Contact</a>
                <a href="{{ route('login') }}" class="gt-focus rounded-xl bg-[#00A87E] px-4 py-2.5 text-white hover:bg-[#08745D]">Sign in</a>
            </div>
        </nav>
    </header>

    <main id="main-content" class="gt-welcome-frame py-10 sm:py-16">
        <div class="mx-auto max-w-3xl">
            <a href="{{ url('/') }}" class="gt-focus text-sm font-bold text-[#08745D] hover:underline">&larr; Back to home</a>
            <div class="mt-7 rounded-[1.75rem] border border-[#DDE9E5] bg-white p-6 shadow-sm sm:p-10">
                <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#008F70]">GastoTrack information</p>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $title }}</h1>
                <p class="mt-4 text-sm leading-7 text-slate-600 sm:text-base">{{ $intro }}</p>
                <div class="mt-8 space-y-7 border-t border-[#DDE9E5] pt-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </main>

    <footer class="border-t border-[#DDE9E5] bg-white py-7">
        <div class="gt-welcome-frame flex flex-col gap-5 text-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ url('/') }}" class="gt-focus text-lg font-extrabold text-[#0A2E2A]">GastoTrack<span class="text-[#00A87E]">.</span></a>
                <p class="mt-1 text-xs text-slate-500">&copy; {{ now()->year }} GastoTrack</p>
            </div>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 text-xs sm:text-sm" aria-label="Information links">
                <a href="{{ route('contact') }}" class="gt-focus text-slate-600 hover:text-[#08745D] hover:underline">Contact</a>
                <a href="{{ route('terms') }}" class="gt-focus text-slate-600 hover:text-[#08745D] hover:underline">Terms and Conditions</a>
                <a href="{{ route('privacy') }}" class="gt-focus text-slate-600 hover:text-[#08745D] hover:underline">Privacy Policy</a>
            </nav>
        </div>
    </footer>
</body>
</html>
