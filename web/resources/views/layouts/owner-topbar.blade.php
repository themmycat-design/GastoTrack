<header class="sticky top-0 z-20 border-b border-[#DDE9E5] bg-white/90 backdrop-blur-xl">
    <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:px-10">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-[#00A87E]">Owner workspace</p>
            <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-2xl">Good day, {{ Auth::user()->name }}</h1>
            <p class="mt-1 truncate text-sm text-slate-500">{{ Auth::user()->business?->name ?? 'Your business' }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            @php
                $unreadNotifications = (int) ($ownerUnreadNotificationCount ?? 0);
                $recentNotifications = $ownerUnreadNotifications ?? collect();
            @endphp
            <details class="relative">
                <summary data-owner-notification-trigger data-unread-count="{{ $unreadNotifications }}" data-read-all-url="{{ route('owner.notifications.read-all') }}" data-csrf-token="{{ csrf_token() }}" class="gt-focus relative flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full border transition {{ $unreadNotifications > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-[#DDE9E5] bg-white text-slate-600 hover:bg-[#F4F8F7]' }}" aria-label="Notifications{{ $unreadNotifications > 0 ? ', '.$unreadNotifications.' unread' : ', none unread' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>
                    @if($unreadNotifications > 0)
                        <span data-notification-count class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-extrabold text-white">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </summary>
                <div class="absolute right-0 top-12 z-40 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-[#DDE9E5] bg-white shadow-2xl shadow-[#0A2E2A]/15">
                    <div class="border-b border-[#EAF2EF] px-5 py-4">
                        <p class="text-sm font-extrabold text-[#0A2E2A]">Notifications</p>
                        <p data-notification-unread-label class="mt-1 text-xs text-slate-500">{{ $unreadNotifications }} unread</p>
                    </div>
                    @if($recentNotifications->isNotEmpty())
                        <ul class="max-h-72 divide-y divide-[#EAF2EF] overflow-y-auto">
                            @foreach($recentNotifications as $notification)
                                @php $isRead = $notification->read_at !== null; @endphp
                                <li class="px-5 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-bold text-[#0A2E2A]">{{ $notification->title }}</p>
                                        <span data-notification-read-state class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $isRead ? 'bg-slate-100 text-slate-600' : 'bg-rose-50 text-rose-700' }}">{{ $isRead ? 'Read' : 'Unread' }}</span>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">{{ $notification->message }}</p>
                                    <div class="mt-2">
                                        @if($notification->type === 'low_stock')
                                            <a href="{{ route('stock.index', ['low_stock' => 1, 'active' => 1]) }}" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">View inventory</a>
                                        @elseif($notification->type === 'goal_deadline')
                                            <a href="{{ route('goals.index') }}" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">View goals</a>
                                        @else
                                            <span class="text-xs text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="px-5 py-6 text-sm text-slate-500">No notifications yet. Active stock alerts remain on the Inventory tab until resolved.</p>
                    @endif
                    <a href="{{ route('stock.index', ['low_stock' => 1, 'active' => 1]) }}" class="gt-focus block border-t border-[#EAF2EF] px-5 py-3 text-center text-sm font-bold text-[#08745D] hover:bg-[#F4F8F7]">View inventory alerts &rarr;</a>
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

<script>
(() => {
    const trigger = document.querySelector('[data-owner-notification-trigger]');
    if (!trigger) return;

    let isMarkingRead = false;
    trigger.addEventListener('click', () => {
        if (isMarkingRead || Number(trigger.dataset.unreadCount || 0) < 1) return;

        const csrfToken = trigger.dataset.csrfToken;
        if (!csrfToken) return;

        isMarkingRead = true;
        fetch(trigger.dataset.readAllUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(response => {
                if (!response.ok) throw new Error('Could not mark notifications as read.');
                return response.json();
            })
            .then(() => {
                trigger.dataset.unreadCount = '0';
                trigger.setAttribute('aria-label', 'Notifications, none unread');
                trigger.querySelector('[data-notification-count]')?.remove();
                trigger.classList.remove('border-rose-200', 'bg-rose-50', 'text-rose-700', 'hover:bg-rose-100');
                trigger.classList.add('border-[#DDE9E5]', 'bg-white', 'text-slate-600', 'hover:bg-[#F4F8F7]');
                const unreadLabel = document.querySelector('[data-notification-unread-label]');
                if (unreadLabel) unreadLabel.textContent = '0 unread';
                document.querySelectorAll('[data-notification-read-state]').forEach(state => {
                    state.textContent = 'Read';
                    state.classList.remove('bg-rose-50', 'text-rose-700');
                    state.classList.add('bg-slate-100', 'text-slate-600');
                });
            })
            .catch(() => {
                const unreadLabel = document.querySelector('[data-notification-unread-label]');
                if (unreadLabel) unreadLabel.textContent = 'Could not update notifications. Try again.';
            })
            .finally(() => { isMarkingRead = false; });
    });
})();
</script>
