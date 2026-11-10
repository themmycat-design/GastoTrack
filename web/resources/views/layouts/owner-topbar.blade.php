<header data-owner-live-url="{{ route('owner.live-state') }}" data-owner-login-url="{{ route('login') }}" class="sticky top-0 z-20 border-b border-[#DDE9E5] bg-white/90 backdrop-blur-xl">
    <div class="mx-auto flex min-h-14 max-w-[1600px] items-center justify-between gap-3 px-5 py-2 sm:px-8 lg:px-10">
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-[#0A2E2A]">Good day, {{ Auth::user()->name }}</p>
            <p class="truncate text-xs text-slate-600">{{ Auth::user()->business?->name ?? 'Your business' }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            @php
                $unreadNotifications = (int) ($ownerUnreadNotificationCount ?? 0);
                $recentNotifications = $ownerUnreadNotifications ?? collect();
            @endphp
            <button type="button" data-owner-theme-toggle aria-pressed="false" aria-label="Switch to dark mode" title="Switch to dark mode" class="gt-focus flex h-10 w-10 items-center justify-center rounded-full border border-[#DDE9E5] bg-white text-slate-600 transition hover:bg-[#F4F8F7] hover:text-[#08745D]">
                <svg data-theme-moon class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.4 15.5A8.5 8.5 0 0 1 8.5 3.6 8.5 8.5 0 1 0 20.4 15.5Z"/></svg>
                <svg data-theme-sun class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
            </button>
            <details class="relative">
                <summary data-owner-notification-trigger data-unread-count="{{ $unreadNotifications }}" data-read-all-url="{{ route('owner.notifications.read-all') }}" data-csrf-token="{{ csrf_token() }}" class="gt-focus relative flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full border transition {{ $unreadNotifications > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-[#DDE9E5] bg-white text-slate-600 hover:bg-[#F4F8F7]' }}" aria-label="Notifications{{ $unreadNotifications > 0 ? ', '.$unreadNotifications.' unread' : ', none unread' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>
                    @if($unreadNotifications > 0)
                        <span data-notification-count class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-extrabold text-white">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </summary>
                <div class="fixed inset-x-4 top-auto z-40 mt-2 overflow-hidden rounded-2xl border border-[#DDE9E5] bg-white shadow-2xl shadow-[#0A2E2A]/15 sm:absolute sm:inset-x-auto sm:right-0 sm:top-12 sm:mt-0 sm:w-96 sm:max-w-[calc(100vw-2rem)]">
                    <div class="border-b border-[#EAF2EF] px-5 py-4">
                        <p class="text-sm font-extrabold text-[#0A2E2A]">Notifications</p>
                        <p data-notification-unread-label class="mt-1 text-xs text-slate-500">{{ $unreadNotifications }} unread</p>
                        <p data-notification-action-error role="alert" class="mt-2 hidden text-xs font-semibold text-rose-700">Could not delete the notification. Try again.</p>
                    </div>
                    <div data-owner-notification-list>
                    @if($recentNotifications->isNotEmpty())
                        <ul data-notification-items class="max-h-72 divide-y divide-[#EAF2EF] overflow-y-auto">
                            @foreach($recentNotifications as $notification)
                                @php $isRead = $notification->read_at !== null; @endphp
                                <li data-notification-item class="px-5 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="min-w-0 flex-1 text-sm font-bold text-[#0A2E2A]">{{ $notification->title }}</p>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <span data-notification-read-state class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $isRead ? 'bg-slate-100 text-slate-600' : 'bg-rose-50 text-rose-700' }}">{{ $isRead ? 'Read' : 'Unread' }}</span>
                                            <form method="POST" action="{{ route('owner.notifications.destroy', $notification->id) }}" data-notification-delete-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="gt-focus inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-rose-50 hover:text-rose-700" aria-label="Delete notification: {{ $notification->title }}" title="Delete notification">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v6m4-6v6"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">{{ $notification->message }}</p>
                                    <div class="mt-2">
                                        @if($notification->type === 'low_stock')
                                            <a href="{{ route('stock.index', ['low_stock' => 1, 'active' => 1]) }}" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">View inventory</a>
                                        @elseif($notification->type === 'goal_deadline')
                                            <a href="{{ route('goals.index') }}" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">View goals</a>
                                        @elseif($notification->type === 'wallet_payment_review')
                                            <a href="{{ route('transactions.index', ['view' => 'raw']) }}" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">Review raw transactions</a>
                                        @elseif(in_array($notification->type, ['order_payment_review', 'order_payment_failed', 'order_refund_pending', 'order_cancelled'], true) && !empty($notification->data['order_id']))
                                            <a href="{{ route('orders.show', ['order' => $notification->data['order_id']]) }}" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">View order</a>
                                        @elseif($notification->type === 'bug_report_update')
                                            <a href="{{ route('owner.bug-reports.index') }}" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">View bug reports</a>
                                        @elseif($notification->type === 'product_edit_request' && empty($notification->data['resolved_at']))
                                            <a href="{{ route('products.index') }}#product-edit-requests" class="gt-focus rounded-md py-1 text-xs font-bold text-[#08745D] hover:underline">Review product edit</a>
                                        @else
                                            <span class="text-xs text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <p data-notification-empty class="hidden px-5 py-6 text-sm text-slate-500">No notifications yet. Active stock alerts remain on the Inventory tab until resolved.</p>
                    @else
                        <p class="px-5 py-6 text-sm text-slate-500">No notifications yet. Active stock alerts remain on the Inventory tab until resolved.</p>
                    @endif
                    </div>
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
@if(auth()->user()?->business?->subscriptionNeedsRenewal())
    <div role="alert" class="border-b border-amber-300 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-900">
        Your subscription has expired or is suspended. Records remain available to read. Contact the platform administrator to renew.
    </div>
@endif

<script>
(() => {
    const themeToggle = document.querySelector('[data-owner-theme-toggle]');
    const themeKey = 'gastotrack-owner-theme';
    const applyTheme = theme => {
        const isDark = theme === 'dark';
        document.documentElement.classList.toggle('gt-dark', isDark);
        window.dispatchEvent(new CustomEvent('gastotrack:theme-change', { detail: { theme } }));
        if (!themeToggle) return;

        themeToggle.setAttribute('aria-pressed', String(isDark));
        themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        themeToggle.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
        themeToggle.querySelector('[data-theme-moon]')?.classList.toggle('hidden', isDark);
        themeToggle.querySelector('[data-theme-sun]')?.classList.toggle('hidden', !isDark);
    };

    let savedTheme = 'light';
    try { savedTheme = localStorage.getItem(themeKey) === 'dark' ? 'dark' : 'light'; } catch (_) {}
    applyTheme(savedTheme);

    themeToggle?.addEventListener('click', () => {
        const nextTheme = document.documentElement.classList.contains('gt-dark') ? 'light' : 'dark';
        applyTheme(nextTheme);
        try { localStorage.setItem(themeKey, nextTheme); } catch (_) {}
    });

    const trigger = document.querySelector('[data-owner-notification-trigger]');
    if (!trigger) return;

    const panel = trigger.closest('details');
    document.addEventListener('click', event => {
        if (!panel.contains(event.target)) panel.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && panel.open) {
            panel.open = false;
            trigger.focus();
        }
    });

    let isMarkingRead = false;
    const updateUnreadState = count => {
        const unreadCount = Math.max(0, Number(count) || 0);
        trigger.dataset.unreadCount = String(unreadCount);
        trigger.setAttribute('aria-label', unreadCount ? `Notifications, ${unreadCount} unread` : 'Notifications, none unread');
        let badge = trigger.querySelector('[data-notification-count]');
        if (unreadCount) {
            if (!badge) {
                badge = document.createElement('span');
                badge.dataset.notificationCount = '';
                badge.className = 'absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-extrabold text-white';
                trigger.appendChild(badge);
            }
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            trigger.classList.remove('border-[#DDE9E5]', 'bg-white', 'text-slate-600', 'hover:bg-[#F4F8F7]');
            trigger.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700', 'hover:bg-rose-100');
        } else {
            badge?.remove();
            trigger.classList.remove('border-rose-200', 'bg-rose-50', 'text-rose-700', 'hover:bg-rose-100');
            trigger.classList.add('border-[#DDE9E5]', 'bg-white', 'text-slate-600', 'hover:bg-[#F4F8F7]');
        }
        const unreadLabel = document.querySelector('[data-notification-unread-label]');
        if (unreadLabel) unreadLabel.textContent = `${unreadCount} unread`;
    };

    panel.addEventListener('submit', event => {
        const form = event.target.closest('[data-notification-delete-form]');
        if (!form) return;
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (button?.disabled) return;
        button.disabled = true;
        document.querySelector('[data-notification-action-error]')?.classList.add('hidden');

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: new FormData(form),
        })
            .then(response => {
                if (!response.ok) throw new Error('Could not delete notification.');
                return response.json();
            })
            .then(data => {
                form.closest('[data-notification-item]')?.remove();
                const list = panel.querySelector('[data-notification-items]');
                if (list && !list.querySelector('[data-notification-item]')) {
                    list.classList.add('hidden');
                    panel.querySelector('[data-notification-empty]')?.classList.remove('hidden');
                }
                updateUnreadState(data.unread_count);
            })
            .catch(() => {
                document.querySelector('[data-notification-action-error]')?.classList.remove('hidden');
            })
            .finally(() => { button.disabled = false; });
    });

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
                updateUnreadState(0);
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
