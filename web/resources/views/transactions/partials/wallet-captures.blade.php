<p class="border-b border-[#EAF2EF] bg-[#F8FCFA] px-5 py-3 text-xs leading-5 text-slate-600 sm:px-6">Wallet notifications and staff-confirmed payment screenshots. Screenshot payments are counted once through their completed orders; raw records do not add income again.</p>
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-[#EAF2EF]">
        <thead class="bg-[#F8FCFA]">
            <tr>
                <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Date and time</th>
                <th class="min-w-52 px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Sender / recipient</th>
                <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Payment app</th>
                <th class="min-w-48 px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Reference number</th>
                <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Staff device</th>
                <th class="whitespace-nowrap px-5 py-3 text-right text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#EAF2EF] bg-white">
            @forelse($captures as $capture)
                <tr class="align-top transition hover:bg-[#FBFDFC]">
                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600 sm:px-6">{{ $capture->captured_at->timezone(config('business.timezone'))->format('M d, Y g:i A') }}</td>
                    <td class="px-5 py-4 text-sm text-slate-700">
                        <p class="font-bold text-[#0A2E2A]">{{ $capture->display_counterparty ?: 'Not detected' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $capture->direction === 'received' ? 'Sender · Incoming' : 'Recipient · Outgoing' }}</p>
                        @if($capture->screenshot_path)
                            <p class="mt-1 text-xs text-slate-500">{{ $capture->capture_method === 'notification_with_screenshot' ? 'Notification and staff-confirmed screenshot' : 'Staff-confirmed screenshot' }}</p>
                            <a href="{{ route('transactions.wallet-screenshot', $capture) }}" target="_blank" rel="noopener" class="gt-focus mt-2 inline-block text-xs font-bold text-[#08745D] underline">View payment screenshot</a>
                        @endif
                        @if($capture->capture_method !== 'staff_screenshot' && $capture->message)
                            <details class="mt-2 text-xs text-slate-500">
                                <summary class="cursor-pointer font-semibold text-[#08745D]">Notification details</summary>
                                <p class="mt-2 max-w-xl whitespace-pre-wrap break-words">{{ $capture->message }}</p>
                            </details>
                        @endif
                        <span class="mt-2 inline-flex rounded-full bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700">{{ $capture->screenshot_path ? 'Staff confirmed · provider unverified' : 'Unverified' }}</span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#0A2E2A]">{{ match($capture->source) { 'gcash' => 'GCash', 'maya' => 'Maya', 'maribank' => 'MariBank', 'gotyme' => 'GoTyme', default => 'Unknown' } }}</td>
                    <td class="max-w-56 px-5 py-4 font-mono text-xs text-slate-600">
                        @if($capture->display_reference)
                            <span class="break-all" title="{{ $capture->display_reference }}">{{ $capture->display_reference }}</span>
                        @else
                            <span class="font-sans text-slate-400">Not detected</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $capture->capturedBy?->name ?? 'Former staff' }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-extrabold text-[#0A2E2A] sm:px-6">{{ $capture->amount !== null ? ($capture->currency ?: 'PHP').' '.number_format($capture->amount, 2) : 'Not detected' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">No wallet notifications or staff payment screenshots match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="border-t border-[#EAF2EF] bg-[#FBFDFC] px-5 py-4 sm:px-6">{{ $captures->links() }}</div>
