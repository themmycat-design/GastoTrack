@php
    $ownerNavItems = [
        ['route' => 'dashboard', 'label' => 'Overview', 'icon' => 'overview'],
        ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'transactions'],
        ['route' => 'stock.index', 'label' => 'Inventory', 'icon' => 'inventory'],
        ['route' => 'staff.index', 'label' => 'Staff', 'icon' => 'staff'],
        ['route' => 'analytics.index', 'label' => 'Analytics', 'icon' => 'analytics'],
    ];
@endphp

<style>
    :root { --brand: #00C897; --brand-dark: #00A87E; --brand-ink: #0A2E2A; --canvas: #F4F8F7; }
    .brand-bg { background: var(--brand); }
    .brand-text { color: var(--brand-dark); }
    .brand-ring:focus-visible { outline: 3px solid rgba(0, 200, 151, .38); outline-offset: 3px; }
</style>

<div class="border-b border-[#DDE9E5] bg-white lg:hidden">
    <div class="flex items-center justify-between gap-4 px-5 py-3.5">
        <a href="{{ route('dashboard') }}" class="gt-focus inline-flex items-center gap-2.5 rounded-lg text-lg font-extrabold tracking-tight text-[#0A2E2A]">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#00C897] text-white">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 8h12v8a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V8Z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17M8 4c0 1 1 1.2 1 2.2M12 3c0 1 1 1.2 1 2.2"/></svg>
            </span>
            GastoTrack
        </a>
        <a href="{{ route('profile.edit') }}" class="gt-focus flex h-9 w-9 items-center justify-center rounded-full bg-[#EAF8F2] text-sm font-extrabold text-[#08745D]" aria-label="Open profile">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
        </a>
    </div>
    <nav class="flex min-w-0 gap-2 overflow-x-auto border-t border-[#EAF2EF] px-4 py-2.5" aria-label="Owner navigation">
        @foreach($ownerNavItems as $item)
            <a href="{{ route($item['route']) }}" aria-current="{{ request()->routeIs($item['route']) ? 'page' : 'false' }}" class="gt-focus shrink-0 rounded-full px-3.5 py-2 text-xs font-bold transition {{ request()->routeIs($item['route']) ? 'bg-[#00A87E] text-white shadow-sm' : 'bg-[#F4F8F7] text-slate-600 hover:bg-[#EAF8F2] hover:text-[#08745D]' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</div>

<aside class="hidden w-64 shrink-0 border-r border-[#DDE9E5] bg-white lg:flex lg:flex-col">
    <div class="border-b border-[#EAF2EF] px-6 py-6">
        <a href="{{ route('dashboard') }}" class="gt-focus inline-flex items-center gap-3 rounded-lg">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#00C897] text-white shadow-sm">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M5 8h12v8a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V8Z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17M8 4c0 1 1 1.2 1 2.2M12 3c0 1 1 1.2 1 2.2"/></svg>
            </span>
            <span>
                <span class="block text-xl font-extrabold tracking-tight text-[#0A2E2A]">GastoTrack</span>
                <span class="mt-0.5 block text-xs font-medium text-slate-500">Owner workspace</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 space-y-1 px-3 py-5" aria-label="Owner navigation">
        <p class="px-3 pb-2 text-[10px] font-extrabold uppercase tracking-[.16em] text-slate-400">Manage your shop</p>
        @foreach($ownerNavItems as $item)
            @php $active = request()->routeIs($item['route']); @endphp
            <a href="{{ route($item['route']) }}" aria-current="{{ $active ? 'page' : 'false' }}" class="gt-focus group flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition {{ $active ? 'bg-[#EAF8F2] text-[#08745D] ring-1 ring-inset ring-[#C8DED7]' : 'text-slate-600 hover:bg-[#F4F8F7] hover:text-[#08745D]' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition {{ $active ? 'bg-[#00A87E] text-white shadow-sm' : 'bg-[#F4F8F7] text-slate-500 group-hover:bg-[#EAF8F2] group-hover:text-[#08745D]' }}">
                    @if($item['icon'] === 'overview')
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="4" rx="1.5"/><rect x="13.5" y="10.5" width="7" height="10" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                    @elseif($item['icon'] === 'transactions')
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1-1.5Z"/><path d="M14 4v4h4M9 13h6M9 16.5h6"/></svg>
                    @elseif($item['icon'] === 'inventory')
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 12v8.5"/></svg>
                    @elseif($item['icon'] === 'staff')
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.5a3.5 3.5 0 0 1 0 6.8M18 15a5.5 5.5 0 0 1 3.5 5"/></svg>
                    @else
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-6"/><path d="M15 7h4v4"/></svg>
                    @endif
                </span>
                <span class="flex-1">{{ $item['label'] }}</span>
                @if($active)<span class="h-1.5 w-1.5 rounded-full bg-[#00A87E]"></span>@endif
            </a>
        @endforeach
    </nav>

    <div class="border-t border-[#EAF2EF] p-4">
        <div class="mb-4 flex items-center gap-3 rounded-xl bg-[#F4F8F7] p-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#00C897] text-sm font-extrabold text-white">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-[#0A2E2A]">{{ Auth::user()->name }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ ucfirst(Auth::user()->role) }} account</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-2.5 text-left text-sm font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">Sign out</button>
        </form>
    </div>
</aside>
