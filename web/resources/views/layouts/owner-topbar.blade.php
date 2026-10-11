<header class="sticky top-0 z-20 border-b border-[#DDE9E5] bg-white/90 backdrop-blur-xl">
    <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:px-10">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">Owner workspace</p>
            <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-2xl">Good day, {{ Auth::user()->name }}</h1>
            <p class="mt-1 truncate text-sm text-slate-500">{{ Auth::user()->business?->name ?? 'Your business' }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <details class="relative">
                <summary class="gt-focus relative flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full border transition {{ ($ownerStockAlerts['total'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-[#DDE9E5] bg-white text-slate-600 hover:bg-[#F4F8F7]' }}" aria-label="Stock notifications{{ ($ownerStockAlerts['total'] ?? 0) > 0 ? ', '.$ownerStockAlerts['total'].' alerts' : ', no alerts' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>
                    @if(($ownerStockAlerts['total'] ?? 0) > 0)
                        <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-extrabold text-white">{{ $ownerStockAlerts['total'] > 99 ? '99+' : $ownerStockAlerts['total'] }}</span>
                    @endif
                </summary>
                <div class="absolute right-0 top-12 z-40 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-[#DDE9E5] bg-white shadow-2xl shadow-[#0A2E2A]/15">
                    <div class="border-b border-[#EAF2EF] px-5 py-4">
                        <p class="text-sm font-extrabold text-[#0A2E2A]">Stock notifications</p>
                        <p class="mt-1 text-xs text-slate-500">Current alerts for your business</p>
                    </div>
                    @if(($ownerStockAlerts['total'] ?? 0) > 0)
                        <div class="flex gap-2 px-5 py-3 text-xs font-bold">
                            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">{{ $ownerStockAlerts['outOfStock'] }} out of stock</span>
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-800">{{ $ownerStockAlerts['lowStock'] }} low stock</span>
                        </div>
                        <ul class="max-h-72 divide-y divide-[#EAF2EF] overflow-y-auto">
                            @foreach($ownerStockAlerts['items'] as $item)
                                @php $isOutOfStock = (float) $item->current_quantity <= 0; @endphp
                                <li>
                                    <a href="{{ route('stock.index', ['search' => $item->name, 'low_stock' => 1, 'active' => 1]) }}" class="gt-focus block px-5 py-3 transition hover:bg-[#F4F8F7]">
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="truncate text-sm font-bold text-[#0A2E2A]">{{ $item->name }}</span>
                                            <span class="shrink-0 text-xs font-bold {{ $isOutOfStock ? 'text-rose-700' : 'text-amber-800' }}">{{ $isOutOfStock ? 'Out of stock' : 'Low stock' }}</span>
                                        </span>
                                        <span class="mt-1 block text-xs text-slate-500">Current {{ number_format((float) $item->current_quantity, 2) }} {{ $item->unit }} · minimum {{ number_format((float) $item->minimum_quantity, 2) }} {{ $item->unit }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @if($ownerStockAlerts['total'] > $ownerStockAlerts['items']->count())
                            <p class="px-5 py-2 text-xs text-slate-500">{{ $ownerStockAlerts['total'] - $ownerStockAlerts['items']->count() }} more items need attention.</p>
                        @endif
                    @else
                        <p class="px-5 py-6 text-sm text-slate-500">No stock alerts right now.</p>
                    @endif
                    <a href="{{ route('stock.index', ['low_stock' => 1, 'active' => 1]) }}" class="gt-focus block border-t border-[#EAF2EF] px-5 py-3 text-center text-sm font-bold text-[#08745D] hover:bg-[#F4F8F7]">View inventory alerts →</a>
                </div>
            </details>
            <span class="hidden items-center gap-2 rounded-full border border-[#DDE9E5] bg-[#F4F8F7] px-3.5 py-2 text-sm font-semibold text-slate-600 sm:inline-flex">
                <svg class="h-4 w-4 text-[#00A87E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M7.5 3v4M16.5 3v4M3.5 10h17"/></svg>
                {{ now()->format('F Y') }}
            </span>
            <a href="{{ route('profile.edit') }}" class="gt-focus flex h-10 w-10 items-center justify-center rounded-full bg-[#00C897] text-sm font-extrabold text-white shadow-sm transition hover:bg-[#00A87E] lg:hidden" aria-label="Open profile">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </a>
        </div>
    </div>
</header>
