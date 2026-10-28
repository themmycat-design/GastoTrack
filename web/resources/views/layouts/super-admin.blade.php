<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Super Admin') - GastoTrack</title>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sa-ink: #0A2E2A;
            --sa-green: #08745D;
            --sa-green-dark: #065C4B;
            --sa-canvas: #F4F8F7;
            --sa-border: #DDE9E5;
        }
        body { background: var(--sa-canvas); color: var(--sa-ink); font-family: Figtree, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .sa-sidebar { z-index: 40; color: #E8F4F0; background: #082F2B; }
        .sa-brand { display: flex; min-height: 4.25rem; align-items: center; gap: .8rem; padding: .65rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.1); }
        .sa-brand img { width: 2.65rem; height: 2.65rem; object-fit: contain; border-radius: .8rem; background: #EAF8F2; padding: .25rem; }
        .sa-brand-name { color: #fff; font-size: 1.1rem; font-weight: 800; letter-spacing: -.025em; line-height: 1.2; }
        .sa-brand-caption { margin-top: .2rem; color: #AFCAC1; font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
        .sa-sidebar-nav { display: flex; gap: .4rem; padding: .85rem; }
        .sa-nav-link { display: flex; min-height: 2.8rem; align-items: center; gap: .75rem; border: 1px solid transparent; border-radius: .8rem; padding: .7rem .85rem; color: #C5D8D2; font-size: .875rem; font-weight: 650; text-decoration: none; transition: background .18s ease, color .18s ease, border-color .18s ease; }
        .sa-nav-link i { width: 1.1rem; color: #93B5AA; text-align: center; }
        .sa-nav-link:hover { color: #fff; background: rgba(255,255,255,.07); }
        .sa-nav-link.is-active { color: #fff; border-color: rgba(0,200,151,.23); background: rgba(0,200,151,.15); box-shadow: inset 3px 0 #00C897; }
        .sa-nav-link.is-active i { color: #00D5A3; }
        .sa-sidebar-account { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: .75rem; border-top: 1px solid rgba(255,255,255,.1); padding: 1rem; }
        .sa-sidebar-account form { grid-column: 1 / -1; }
        .sa-avatar { display: inline-flex; width: 2.35rem; height: 2.35rem; align-items: center; justify-content: center; flex: 0 0 auto; border-radius: 50%; color: #064B3C; background: #B8F1DC; font-weight: 800; }
        .sa-account-name { overflow: hidden; color: #fff; font-size: .82rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .sa-account-role { margin-top: .15rem; color: #AFCAC1; font-size: .72rem; }
        .sa-signout { width: 100%; border: 1px solid rgba(255,255,255,.14); border-radius: .7rem; padding: .6rem .75rem; color: #D8E7E2; background: rgba(255,255,255,.04); text-align: left; font-size: .82rem; font-weight: 650; transition: background .18s ease; }
        .sa-signout:hover { background: rgba(255,255,255,.1); }
        .sa-main { min-height: 100vh; }
        .sa-topbar { display: flex; min-height: 4.25rem; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--sa-border); padding: .7rem 1.25rem; background: rgba(255,255,255,.92); }
        .sa-topbar-label { color: #607570; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .sa-topbar-user { display: flex; min-width: 0; align-items: center; gap: .65rem; }
        .sa-content { width: min(100%, 1600px); margin: 0 auto; padding: 1.25rem; }
        .sa-alert { margin-bottom: 1.25rem; border: 1px solid; border-radius: .9rem; padding: .9rem 1rem; font-size: .875rem; box-shadow: 0 4px 14px rgba(10,46,42,.035); }
        .sa-alert-success { border-color: #B7E7D5; color: #075D46; background: #ECFBF4; }
        .sa-alert-error { border-color: #F2C6C3; color: #9D2924; background: #FFF4F3; }
        .sa-main h1 { color: var(--sa-ink); font-size: clamp(1.65rem, 3vw, 2rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.2; }
        .sa-main h2 { color: var(--sa-ink); letter-spacing: -.015em; }
        .sa-main h3 { color: #123C36; letter-spacing: -.01em; }
        .sa-main p { overflow-wrap: break-word; }
        .sa-main [class~="border-slate-200"][class~="bg-white"] { border-color: var(--sa-border); border-radius: 1rem; box-shadow: 0 8px 24px rgba(10,46,42,.045); }
        .sa-main [class~="bg-emerald-600"] { background-color: var(--sa-green); }
        .sa-main [class~="hover:bg-emerald-700"]:hover { background-color: var(--sa-green-dark); }
        .sa-main [class~="bg-slate-900"] { background-color: #123C36; }
        .sa-main [class~="bg-amber-600"] { background-color: #B7791F; }
        .sa-main [class~="bg-red-600"] { background-color: #C2413A; }
        .sa-main [class~="text-emerald-700"] { color: var(--sa-green-dark); }
        .sa-main [class~="bg-emerald-100"] { background-color: #E4F7EF; }
        .sa-main [class~="bg-blue-50"] { background-color: #EFF8F5; }
        .sa-main [class~="border-blue-200"] { border-color: #C8DED7; }
        .sa-main [class~="text-blue-900"], .sa-main [class~="text-blue-800"] { color: #28564E; }
        .sa-main button { cursor: pointer; transition: background-color .16s ease, border-color .16s ease, color .16s ease, box-shadow .16s ease; }
        .sa-main button:disabled { cursor: not-allowed; opacity: .6; }
        .sa-main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), .sa-main select, .sa-main textarea { max-width: 100%; min-height: 2.7rem; border: 1px solid #CFDED9; border-radius: .7rem; padding: .6rem .75rem; background-color: #fff; color: var(--sa-ink); }
        .sa-main textarea { min-height: unset; }
        .sa-main input[type="file"] { min-height: unset; padding: .45rem; }
        .sa-main input:focus, .sa-main select:focus, .sa-main textarea:focus { border-color: var(--sa-green); outline: 3px solid rgba(0,168,126,.14); outline-offset: 1px; }
        .sa-main input[type="checkbox"] { accent-color: var(--sa-green); }
        .sa-main table { min-width: 760px; border-collapse: separate; border-spacing: 0; }
        .sa-main thead { background: #F4F8F7; color: #607570; }
        .sa-main thead th { padding-top: .85rem; padding-bottom: .85rem; font-size: .68rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; white-space: nowrap; }
        .sa-main tbody tr { transition: background .12s ease; }
        .sa-main tbody tr:hover, .sa-main tbody tr:focus-within { background: #ECF7F3; }
        .sa-main tbody td { vertical-align: middle; }
        .sa-main table th:first-child, .sa-main table td:first-child { padding-left: 1.25rem; }
        .sa-main table th:last-child, .sa-main table td:last-child { padding-right: 1.25rem; }
        .sa-main a:focus-visible, .sa-main button:focus-visible, .sa-sidebar a:focus-visible, .sa-sidebar button:focus-visible { outline: 3px solid #08745D; outline-offset: 2px; }
        .sa-main :is(input, select, textarea)::placeholder { color: #60756F; }
        .sa-content > :is(div, section, form, nav) { animation: sa-content-enter 220ms cubic-bezier(.2,.75,.25,1) both; }
        .sa-content > :is(div, section, form, nav):nth-child(2) { animation-delay: 35ms; }
        .sa-content > :is(div, section, form, nav):nth-child(3) { animation-delay: 70ms; }
        .sa-content > :is(div, section, form, nav):nth-child(n + 4) { animation-delay: 105ms; }
        @keyframes sa-content-enter {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sa-content > :is(div, section, form, nav) { animation: none; }
        }
        @media (min-width: 1024px) {
            .sa-sidebar { position: fixed; inset: 0 auto 0 0; display: flex; width: 17rem; height: 100vh; flex-direction: column; }
            .sa-sidebar-nav { flex: 1; min-height: 0; overflow-y: auto; flex-direction: column; padding: 1rem .85rem; }
            .sa-sidebar-account { margin-top: auto; padding: 1rem; }
            .sa-main { margin-left: 17rem; }
            .sa-topbar { position: sticky; top: 0; z-index: 20; padding-right: 2rem; padding-left: 2rem; }
            .sa-content { padding: 2rem; }
        }
        @media (max-width: 1023px) {
            .sa-sidebar { position: relative; }
            .sa-sidebar-nav { overflow-x: auto; white-space: nowrap; scrollbar-width: thin; }
            .sa-nav-link { min-width: max-content; }
            .sa-sidebar-account { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem 1rem; }
            .sa-account-copy { min-width: 0; }
            .sa-signout { width: auto; flex: 0 0 auto; }
        }
        @media (max-width: 639px) {
            .sa-content { padding: 1rem; }
            .sa-topbar { padding: .6rem 1rem; }
            .sa-topbar-label { font-size: .65rem; }
            .sa-main table { min-width: 680px; }
        }
    </style>
</head>
<body class="gt-admin-page">
<a class="gt-skip-link" href="#main-content">Skip to content</a>
<div class="min-h-screen">
    <aside class="sa-sidebar">
        <a href="{{ route('super-admin.dashboard') }}" class="sa-brand">
            <img src="{{ asset('images/gastotrack-logo.png') }}" alt="">
            <span><span class="sa-brand-name block">GastoTrack</span><span class="sa-brand-caption block">Platform administration</span></span>
        </a>
        <nav class="sa-sidebar-nav" aria-label="Super Admin navigation">
            @php
                $links = [
                    ['super-admin.dashboard', 'super-admin.dashboard', 'fa-chart-line', 'Overview'],
                    ['super-admin.businesses', 'super-admin.businesses*', 'fa-building', 'Businesses'],
                    ['super-admin.users.index', 'super-admin.users.*', 'fa-users', 'Users and staff'],
                    ['super-admin.activity-logs.index', 'super-admin.activity-logs.*', 'fa-list-check', 'Admin activity'],
                    ['super-admin.bug-reports.index', 'super-admin.bug-reports.*', 'fa-bug', 'Bug reports'],
                    ['super-admin.reports.index', 'super-admin.reports.*', 'fa-file-lines', 'Platform Reports'],
                ];
            @endphp
            @foreach($links as [$routeName, $pattern, $icon, $label])
                <a href="{{ route($routeName) }}" aria-current="{{ request()->routeIs($pattern) ? 'page' : 'false' }}" class="sa-nav-link {{ request()->routeIs($pattern) ? 'is-active' : '' }}">
                    <i class="fas {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <div class="sa-sidebar-account">
            <span class="sa-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="sa-account-copy min-w-0 flex-1">
                <p class="sa-account-name">{{ auth()->user()->name }}</p>
                <p class="sa-account-role">Super Administrator</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="sa-signout"><i class="fas fa-arrow-right-from-bracket mr-2" aria-hidden="true"></i><span class="lg:hidden">Sign out</span><span class="hidden lg:inline">Sign out</span></button>
            </form>
        </div>
    </aside>
    <main class="sa-main">
        <header class="sa-topbar">
            <p class="sa-topbar-label">GastoTrack <span class="mx-1 text-[#B8C9C4]">/</span> Platform console</p>
            <div class="sa-topbar-user"><span class="sa-avatar !h-8 !w-8 text-xs" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="hidden max-w-48 truncate text-sm font-bold text-[#0A2E2A] sm:inline">{{ auth()->user()->name }}</span></div>
        </header>
        <div id="main-content" tabindex="-1" class="sa-content">
            @if(session('success'))
                <div role="status" class="sa-alert sa-alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div role="alert" class="sa-alert sa-alert-error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div role="alert" class="sa-alert sa-alert-error">
                    <p class="font-semibold">Please correct the following:</p>
                    <ul class="mt-2 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
