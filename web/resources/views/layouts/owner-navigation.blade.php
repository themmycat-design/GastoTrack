@php
    $ownerNavItems = [
        ['route' => 'dashboard', 'label' => 'Overview', 'icon' => '⌂'],
        ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => '₱'],
        ['route' => 'stock.index', 'label' => 'Inventory', 'icon' => '▣'],
        ['route' => 'staff.index', 'label' => 'Staff', 'icon' => '♙'],
        ['route' => 'analytics.index', 'label' => 'Analytics', 'icon' => '↗'],
    ];
@endphp

<style>
    :root { --brand: #00a87e; --brand-dark: #08745d; --canvas: #f4f8f7; }
    .brand-bg { background: var(--brand); }
    .brand-text { color: var(--brand-dark); }
    .brand-ring:focus { outline: 3px solid rgba(0,168,126,.25); outline-offset: 2px; }
</style>

<div class="border-b border-emerald-100 bg-white lg:hidden">
    <div class="flex items-center justify-between px-5 py-4">
        <a href="{{ route('dashboard') }}" class="text-xl font-extrabold tracking-tight brand-text">GastoTrack</a>
        <a href="{{ route('profile.edit') }}" class="flex h-9 w-9 items-center justify-center rounded-full brand-bg text-sm font-bold text-white" aria-label="Open profile">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
        </a>
    </div>
    <nav class="flex min-w-max gap-2 overflow-x-auto border-t border-gray-100 px-5 py-2" aria-label="Mobile owner navigation">
        @foreach($ownerNavItems as $item)
            <a href="{{ route($item['route']) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ request()->routeIs($item['route']) ? 'brand-bg text-white' : 'bg-gray-100 text-gray-600' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</div>

<aside class="hidden lg:flex lg:w-72 lg:flex-col lg:shrink-0 bg-white border-r border-emerald-100">
    <div class="px-7 py-7 border-b border-gray-100">
        <a href="{{ route('dashboard') }}" class="text-2xl font-extrabold tracking-tight brand-text">GastoTrack</a>
        <p class="mt-1 text-xs text-gray-500">Owner workspace</p>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-1" aria-label="Owner navigation">
        @foreach($ownerNavItems as $item)
            <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs($item['route']) ? 'brand-bg text-white shadow-sm' : 'text-gray-600 hover:bg-emerald-50 hover:text-emerald-800' }}">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ request()->routeIs($item['route']) ? 'bg-white/20' : 'bg-gray-100' }}">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-gray-100 px-5 py-5">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-full brand-bg font-bold text-white">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-gray-900">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500">{{ ucfirst(Auth::user()->role) }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="brand-ring w-full rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Sign out</button>
        </form>
    </div>
</aside>
