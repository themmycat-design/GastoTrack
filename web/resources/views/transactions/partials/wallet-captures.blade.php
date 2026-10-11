<p class="border-b border-[#EAF2EF] bg-[#F8FCFA] px-5 py-3 text-xs leading-5 text-slate-600 sm:px-6">Captured GCash and Maya notifications from staff devices. These signals are unverified and are not counted as income.</p>
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-[#EAF2EF]">
        <thead class="bg-[#F8FCFA]">
            <tr>
                <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Captured</th>
                <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Wallet</th>
                <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Direction</th>
                <th class="min-w-64 px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Notification</th>
                <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Staff device</th>
                <th class="whitespace-nowrap px-5 py-3 text-right text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Detected amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#EAF2EF] bg-white">
            @forelse($captures as $capture)
                <tr class="align-top transition hover:bg-[#FBFDFC]">
                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600 sm:px-6">{{ $capture->captured_at->timezone(config('business.timezone'))->format('M d, Y g:i A') }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#0A2E2A]">{{ $capture->source === 'gcash' ? 'GCash' : 'Maya' }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ ucfirst($capture->direction) }}</td>
                    <td class="px-5 py-4 text-sm text-slate-600"><p class="max-w-xl whitespace-pre-wrap break-words">{{ $capture->message ?: 'No message text supplied' }}</p><span class="mt-2 inline-flex rounded-full bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700">Unverified notification</span></td>
                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $capture->capturedBy?->name ?? 'Former staff' }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-extrabold text-[#0A2E2A] sm:px-6">{{ $capture->amount !== null ? '₱'.number_format($capture->amount, 2) : 'Not detected' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">No captured GCash or Maya notifications match these filters. Capture begins when a signed-in Android staff device grants notification access.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="border-t border-[#EAF2EF] bg-[#FBFDFC] px-5 py-4 sm:px-6">{{ $captures->links() }}</div>
