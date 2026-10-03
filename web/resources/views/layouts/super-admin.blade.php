<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Super Admin') - GastoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-900">
<div class="min-h-screen lg:flex">
    <aside class="bg-emerald-600 text-white lg:fixed lg:inset-y-0 lg:w-64">
        <div class="p-6 border-b border-white/15">
            <p class="text-2xl font-bold">GastoTrack</p>
            <p class="text-sm text-white/75">Platform Administration</p>
        </div>
        <nav class="p-4 grid grid-cols-2 gap-2 lg:block">
            @php
                $links = [
                    ['super-admin.dashboard', 'super-admin.dashboard', 'fa-chart-line', 'Overview'],
                    ['super-admin.businesses', 'super-admin.businesses*', 'fa-building', 'Businesses'],
                    ['super-admin.violations.index', 'super-admin.violations.*', 'fa-shield-halved', 'Violations'],
                    ['super-admin.reports.index', 'super-admin.reports.*', 'fa-file-lines', 'Platform Reports'],
                ];
            @endphp
            @foreach($links as [$routeName, $pattern, $icon, $label])
                <a href="{{ route($routeName) }}" class="mb-2 flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium {{ request()->routeIs($pattern) ? 'bg-white/20' : 'text-white/85 hover:bg-white/10' }}">
                    <i class="fas {{ $icon }} w-5"></i>{{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="p-4 lg:absolute lg:bottom-0 lg:w-full border-t border-white/15">
            <p class="px-4 text-sm font-semibold">{{ auth()->user()->name }}</p>
            <p class="px-4 text-xs text-white/70">Super Administrator</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf
                <button class="w-full rounded-lg px-4 py-2 text-left text-sm hover:bg-white/10"><i class="fas fa-sign-out-alt mr-2"></i>Sign out</button>
            </form>
        </div>
    </aside>
    <main class="flex-1 lg:ml-64">
        <div class="p-4 sm:p-6 lg:p-8">
            @if(session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
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
