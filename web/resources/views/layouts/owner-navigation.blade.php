@php
    $ownerNavItems = [
        ['route' => 'dashboard', 'label' => 'Overview', 'icon' => 'overview'],
        ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'transactions'],
        ['route' => 'orders.index', 'label' => 'Orders', 'icon' => 'orders'],
        ['route' => 'stock.index', 'label' => 'Inventory', 'icon' => 'inventory'],
        ['route' => 'staff.index', 'label' => 'Staff', 'icon' => 'staff'],
        ['route' => 'analytics.index', 'label' => 'Analytics', 'icon' => 'analytics'],
        ['route' => 'goals.index', 'label' => 'Goals', 'icon' => 'goals'],
        ['route' => 'owner.bug-reports.index', 'label' => 'Bug reports', 'icon' => 'bug'],
        ['route' => 'owner.reports.index', 'label' => 'Reports', 'icon' => 'reports'],
    ];
    $ownerReportSubNavItems = [
        ['route' => 'sales-orders', 'label' => 'Sales and orders'],
        ['route' => 'income-expenses', 'label' => 'Income and expenses'],
        ['route' => 'product-costs', 'label' => 'Product costs and margins'],
        ['route' => 'inventory-waste', 'label' => 'Inventory and waste'],
        ['route' => 'payment-review', 'label' => 'Payment review'],
        ['route' => 'staff-activity', 'label' => 'Staff activity'],
    ];
    $reportsSubNavOpen = request()->routeIs('owner.reports.*') && request()->boolean('reports_open');
    $ownerNavActive = fn ($item) => request()->routeIs(match ($item['route']) {
        'dashboard' => 'dashboard',
        'owner.bug-reports.index' => 'owner.bug-reports.*',
        'owner.reports.index' => 'owner.reports.*',
        default => str_replace('.index', '.*', $item['route']),
    }) || ($item['route'] === 'stock.index' && request()->routeIs('products.*', 'categories.*'));
@endphp

<style>
    :root { --brand: #00C897; --brand-dark: #00A87E; --brand-ink: #0A2E2A; --canvas: #F4F8F7; }
    .brand-bg { background: var(--brand); }
    .brand-text { color: var(--brand-dark); }
    .brand-ring:focus-visible { outline: 3px solid rgba(0, 200, 151, .38); outline-offset: 3px; }
    .reports-panel {
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        pointer-events: none;
        transform: translateY(-0.5rem);
        transition: max-height 320ms ease, opacity 220ms ease, transform 320ms ease;
    }
    .reports-panel[data-open="true"] {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }
    .reports-panel[data-open="false"] {
        padding-block: 0;
        border-block-width: 0;
    }
    [data-reports-toggle][aria-expanded="true"] [data-reports-chevron] { transform: rotate(180deg); }
    [data-report-nav][aria-current="page"] { background: #00A87E; border-color: #00A87E; color: #fff; }
    @media (prefers-reduced-motion: reduce) {
        .reports-panel, [data-reports-chevron] { transition-duration: 0.01ms; }
    }
</style>

<div data-owner-navigation class="border-b border-[#DDE9E5] bg-white lg:hidden">
    <div class="flex min-h-14 items-center justify-between gap-3 px-5 py-0 sm:px-8">
        <a href="{{ route('dashboard') }}" class="gt-focus inline-flex items-center gap-2 rounded-lg text-base font-extrabold tracking-tight text-[#0A2E2A]">
            <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-8 w-8 object-contain">
            GastoTrack
        </a>
        <a href="{{ route('profile.edit') }}" class="gt-focus flex h-8 w-8 items-center justify-center rounded-full bg-[#EAF8F2] text-xs font-extrabold text-[#08745D]" aria-label="Open profile">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
        </a>
    </div>
    <nav class="flex min-w-0 gap-1.5 overflow-x-auto border-t border-[#EAF2EF] px-3 py-2" aria-label="Owner navigation">
        @foreach($ownerNavItems as $item)
            @php
                $active = $ownerNavActive($item);
                $inventoryAlert = $item['icon'] === 'inventory' && ($ownerStockAlerts['total'] ?? 0) > 0;
            @endphp
            @if($item['icon'] === 'reports')
                <button type="button" data-reports-toggle data-report-page="{{ request()->routeIs('owner.reports.*') ? 'true' : 'false' }}" aria-controls="reports-mobile-panel" aria-expanded="{{ $reportsSubNavOpen ? 'true' : 'false' }}" class="gt-focus inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1.5 text-[11px] font-bold transition {{ $active || $reportsSubNavOpen ? 'bg-[#00A87E] text-white shadow-sm' : 'bg-[#F4F8F7] text-slate-600 hover:bg-[#EAF8F2] hover:text-[#08745D]' }}">
                    Reports
                    <svg data-reports-chevron class="h-3.5 w-3.5 transition-transform duration-300" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            @else
                <a href="{{ route($item['route']) }}" aria-current="{{ $active ? 'page' : 'false' }}" aria-label="{{ $inventoryAlert ? 'Inventory, '.$ownerStockAlerts['outOfStock'].' out of stock and '.$ownerStockAlerts['lowStock'].' low stock' : $item['label'] }}" class="gt-focus inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1.5 text-[11px] font-bold transition {{ $inventoryAlert ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' : ($active ? 'bg-[#00A87E] text-white shadow-sm' : 'bg-[#F4F8F7] text-slate-600 hover:bg-[#EAF8F2] hover:text-[#08745D]') }}">
                    {{ $item['label'] }}
                    @if($inventoryAlert)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[9px] font-extrabold text-white">{{ $ownerStockAlerts['total'] > 99 ? '99+' : $ownerStockAlerts['total'] }}</span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>
    <div id="reports-mobile-panel" data-reports-panel data-open="{{ $reportsSubNavOpen ? 'true' : 'false' }}" aria-hidden="{{ $reportsSubNavOpen ? 'false' : 'true' }}" @if(!$reportsSubNavOpen) inert @endif class="reports-panel border-t border-[#EAF2EF] bg-[#F8FBFA]">
        <nav class="grid gap-1.5 px-3 py-2" aria-label="Reports navigation">
            @foreach($ownerReportSubNavItems as $subItem)
                <a data-report-nav aria-current="{{ request()->routeIs('owner.reports.show') && request()->route('section') === $subItem['route'] ? 'page' : 'false' }}" href="{{ route('owner.reports.show', array_merge(['section' => $subItem['route'], 'reports_open' => 1], request()->only(['start_date', 'end_date']))) }}" class="gt-focus flex min-h-10 items-center justify-between rounded-lg border border-[#DDE9E5] bg-white px-3 py-2 text-[11px] font-bold text-slate-600 shadow-sm transition hover:border-[#B8DDD0] hover:bg-[#EAF8F2] hover:text-[#08745D]">
                    <span>{{ $subItem['label'] }}</span>
                    <svg class="h-3.5 w-3.5 text-[#00A87E]" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            @endforeach
        </nav>
    </div>
</div>

<aside data-owner-navigation class="hidden w-60 shrink-0 border-r border-[#DDE9E5] bg-white lg:flex lg:flex-col">
    <div class="flex min-h-14 items-center border-b border-[#EAF2EF] px-4 py-0">
        <a href="{{ route('dashboard') }}" class="gt-focus inline-flex items-center gap-2.5 rounded-lg">
            <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-9 w-9 object-contain">
            <span>
                <span class="block text-lg font-extrabold tracking-tight text-[#0A2E2A]">GastoTrack</span>
                <span class="block text-[11px] font-medium text-slate-500">Owner workspace</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 space-y-0.5 px-2.5 py-3" aria-label="Owner navigation">
        <p class="px-2.5 pb-1 text-[9px] font-extrabold uppercase tracking-[.16em] text-slate-400">Manage your shop</p>
        @foreach($ownerNavItems as $item)
            @php
                $active = $ownerNavActive($item);
                $inventoryAlert = $item['icon'] === 'inventory' && ($ownerStockAlerts['total'] ?? 0) > 0;
            @endphp
            @if($item['icon'] === 'reports')
                <button type="button" data-reports-toggle data-report-page="{{ request()->routeIs('owner.reports.*') ? 'true' : 'false' }}" aria-controls="reports-desktop-panel" aria-expanded="{{ $reportsSubNavOpen ? 'true' : 'false' }}" class="gt-focus group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-semibold transition {{ $active || $reportsSubNavOpen ? 'bg-[#EAF8F2] text-[#08745D] ring-1 ring-inset ring-[#C8DED7]' : 'text-slate-600 hover:bg-[#F4F8F7] hover:text-[#08745D]' }}">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F4F8F7] text-slate-500 transition group-hover:bg-[#EAF8F2] group-hover:text-[#08745D]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1-1.5Z"/><path d="M14 4v4h4M9 13h2v4H9zm4-3h2v7h-2z"/></svg>
                    </span>
                    <span class="flex-1">Reports</span>
                    <svg data-reports-chevron class="h-3.5 w-3.5 transition-transform duration-300" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            @else
            <a href="{{ route($item['route']) }}" aria-current="{{ $active ? 'page' : 'false' }}" aria-label="{{ $inventoryAlert ? 'Inventory, '.$ownerStockAlerts['outOfStock'].' out of stock and '.$ownerStockAlerts['lowStock'].' low stock' : $item['label'] }}" class="gt-focus group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-semibold transition {{ $inventoryAlert ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' : ($active ? 'bg-[#EAF8F2] text-[#08745D] ring-1 ring-inset ring-[#C8DED7]' : 'text-slate-600 hover:bg-[#F4F8F7] hover:text-[#08745D]') }}">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition {{ $inventoryAlert ? 'bg-rose-100 text-rose-700' : ($active ? 'bg-[#00A87E] text-white shadow-sm' : 'bg-[#F4F8F7] text-slate-500 group-hover:bg-[#EAF8F2] group-hover:text-[#08745D]') }}">
                    @if($item['icon'] === 'overview')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="4" rx="1.5"/><rect x="13.5" y="10.5" width="7" height="10" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                    @elseif($item['icon'] === 'transactions')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1-1.5Z"/><path d="M14 4v4h4M9 13h6M9 16.5h6"/></svg>
                    @elseif($item['icon'] === 'orders')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3.5h12A1.5 1.5 0 0 1 19.5 5v15.5l-3.5-2-4 2-4-2-3.5 2V5A1.5 1.5 0 0 1 6 3.5Z"/><path d="M8.5 8h7M8.5 12h7M8.5 16h3"/></svg>
                    @elseif($item['icon'] === 'inventory')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 12v8.5"/></svg>
                    @elseif($item['icon'] === 'staff')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.5a3.5 3.5 0 0 1 0 6.8M18 15a5.5 5.5 0 0 1 3.5 5"/></svg>
                    @elseif($item['icon'] === 'goals')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/></svg>
                    @elseif($item['icon'] === 'bug')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 9h8v8a4 4 0 0 1-8 0V9Z"/><path d="M10 9a2 2 0 1 1 4 0M4 12h4m8 0h4M5 7l3 2m11-2-3 2M6 18h3m6 0h3M12 13v3"/></svg>
                    @elseif($item['icon'] === 'reports')
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1-1.5Z"/><path d="M14 4v4h4M9 13h2v4H9zm4-3h2v7h-2z"/></svg>
                    @else
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-6"/><path d="M15 7h4v4"/></svg>
                    @endif
                </span>
                <span class="flex-1">{{ $item['label'] }}</span>
                @if($inventoryAlert)
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[11px] font-extrabold text-white">{{ $ownerStockAlerts['total'] > 99 ? '99+' : $ownerStockAlerts['total'] }}</span>
                @elseif($active)
                    <span class="h-1.5 w-1.5 rounded-full bg-[#00A87E]"></span>
                @endif
            </a>
            @endif
            @if($item['icon'] === 'reports')
                <div id="reports-desktop-panel" data-reports-panel data-open="{{ $reportsSubNavOpen ? 'true' : 'false' }}" aria-hidden="{{ $reportsSubNavOpen ? 'false' : 'true' }}" @if(!$reportsSubNavOpen) inert @endif class="reports-panel ml-11 rounded-lg border border-[#DDE9E5] bg-[#F8FBFA] p-1.5">
                    @foreach($ownerReportSubNavItems as $subItem)
                        <a data-report-nav aria-current="{{ request()->routeIs('owner.reports.show') && request()->route('section') === $subItem['route'] ? 'page' : 'false' }}" href="{{ route('owner.reports.show', array_merge(['section' => $subItem['route'], 'reports_open' => 1], request()->only(['start_date', 'end_date']))) }}" class="gt-focus mb-1.5 flex items-center justify-between rounded-lg border border-[#DDE9E5] bg-white px-2.5 py-2 text-[11px] font-semibold text-slate-600 shadow-sm transition last:mb-0 hover:border-[#B8DDD0] hover:bg-[#EAF8F2] hover:text-[#08745D]">
                            <span>{{ $subItem['label'] }}</span>
                            <svg class="h-3.5 w-3.5 text-[#00A87E]" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-[#EAF2EF] p-3">
        <div class="mb-2.5 flex items-center gap-2.5 rounded-lg bg-[#F4F8F7] p-2.5">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#00C897] text-xs font-extrabold text-white">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            <div class="min-w-0">
                <p class="truncate text-[13px] font-bold text-[#0A2E2A]">{{ Auth::user()->name }}</p>
                <p class="text-[11px] text-slate-500">{{ ucfirst(Auth::user()->role) }} account</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="gt-focus w-full rounded-lg border border-[#DDE9E5] bg-white px-3 py-2 text-left text-[13px] font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">Sign out</button>
        </form>
    </div>
</aside>

<script>
    (() => {
        document.querySelectorAll('[data-reports-toggle]').forEach((button) => {
            const panel = document.getElementById(button.getAttribute('aria-controls'));
            if (!panel) return;

            if (panel.dataset.open === 'true') {
                panel.style.maxHeight = 'none';
            }
            panel.addEventListener('keydown', event => {
                if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
                    button.click();
                    button.focus();
                }
            });

            button.addEventListener('click', () => {
                const isOpening = button.getAttribute('aria-expanded') !== 'true';
                button.setAttribute('aria-expanded', String(isOpening));
                panel.dataset.open = String(isOpening);
                panel.setAttribute('aria-hidden', String(!isOpening));

                if (isOpening) {
                    panel.inert = false;
                    panel.style.maxHeight = '0px';
                    const finishExpansion = (event) => {
                        if (event.target !== panel || event.propertyName !== 'max-height') return;
                        panel.removeEventListener('transitionend', finishExpansion);
                        if (panel.dataset.open === 'true') panel.style.maxHeight = 'none';
                    };
                    panel.addEventListener('transitionend', finishExpansion);
                    requestAnimationFrame(() => {
                        panel.style.maxHeight = `${panel.scrollHeight}px`;
                    });
                } else {
                    panel.inert = true;
                    panel.style.maxHeight = `${panel.scrollHeight}px`;
                    panel.getBoundingClientRect();
                    requestAnimationFrame(() => {
                        panel.style.maxHeight = '0px';
                    });
                    panel.addEventListener('transitionend', function finishCollapse(event) {
                        if (event.target !== panel || event.propertyName !== 'max-height') return;
                        panel.removeEventListener('transitionend', finishCollapse);
                        if (panel.dataset.open === 'false') panel.setAttribute('aria-hidden', 'true');
                    });
                }

                if (button.dataset.reportPage === 'true') {
                    const url = new URL(window.location.href);
                    if (isOpening) url.searchParams.set('reports_open', '1');
                    else url.searchParams.delete('reports_open');
                    window.history.replaceState({}, '', url);
                }
            });
        });

    })();
</script>
