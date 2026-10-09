<section id="owner-activity" class="gt-card gt-enter gt-enter-4 relative overflow-hidden" aria-live="polite" aria-busy="false">
    <div data-activity-loading class="hidden border-b border-[#C8DED7] bg-[#EAF8F2] px-5 py-3 text-sm font-semibold text-[#08745D] sm:px-6" role="status">Updating activity…</div>
    <div class="flex items-center justify-between gap-3 border-b border-[#EAF2EF] px-5 py-5 sm:px-6">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.14em] text-[#00A87E]">Activity</p>
            <h3 class="mt-1 text-lg font-extrabold text-[#0A2E2A]">Transaction records</h3>
        </div>
        <span class="rounded-full bg-[#F4F8F7] px-3 py-1.5 text-xs font-bold text-slate-500">{{ $transactions->total() }} records</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#EAF2EF]">
            <thead class="bg-[#F8FCFA]">
                <tr>
                    <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Date</th>
                    <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Category</th>
                    <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Source</th>
                    <th class="min-w-56 px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Products / Description</th>
                    <th class="whitespace-nowrap px-5 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Staff member</th>
                    <th class="whitespace-nowrap px-5 py-3 text-right text-[11px] font-extrabold uppercase tracking-wider text-slate-500 sm:px-6">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#EAF2EF] bg-white">
                @forelse($transactions as $transaction)
                    <tr class="transition hover:bg-[#FBFDFC]">
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600 sm:px-6">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('M d, Y') }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#0A2E2A]">{{ $transaction->category ?? 'Uncategorized' }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ match(strtolower($transaction->source ?? '')) { 'cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya', 'bank', 'bank transfer' => 'Bank Transfer', 'credit_card', 'credit card' => 'Credit Card', default => $transaction->source ?? 'Unknown' } }}</td>
                        <td class="px-5 py-4 text-sm text-slate-600 sm:px-6">{{ $transaction->description ?? 'No description' }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600 sm:px-6">{{ $transaction->user->name ?? 'N/A' }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-extrabold sm:px-6 {{ $transaction->type === 'income' ? 'text-[#08745D]' : 'text-rose-600' }}">{{ $transaction->type === 'income' ? '+' : '-' }}&#8369;{{ number_format($transaction->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-14 text-center">
                            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF8F2] text-xl text-[#08745D]" aria-hidden="true">↗</span>
                            <p class="mt-3 text-sm font-bold text-[#0A2E2A]">No transactions found</p>
                            <p class="mt-1 text-sm text-slate-500">Try adjusting your filters. New staff records will appear here.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-[#EAF2EF] bg-[#FBFDFC] px-5 py-4 sm:px-6">{{ $transactions->links() }}</div>
</section>
