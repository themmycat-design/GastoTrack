<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Subscription | GastoTrack</title>
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1400px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <header>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-[#08745D]">Your business plan</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Subscription</h1>
                <p class="mt-2 text-sm text-slate-600">Choose a paid plan and send proof of your offline payment for Super Admin review.</p>
            </header>

            @if(session('success'))
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                    <p class="font-bold">Please review your request.</p>
                    <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Current subscription">
                <div class="rounded-2xl border border-[#DDE9E5] bg-white p-5 shadow-sm"><p class="text-xs font-bold text-slate-500">Current plan</p><p class="mt-2 text-2xl font-extrabold capitalize">{{ $business->plan_tier ?? 'free' }}</p></div>
                <div class="rounded-2xl border border-[#DDE9E5] bg-white p-5 shadow-sm"><p class="text-xs font-bold text-slate-500">Status</p><p class="mt-2 text-2xl font-extrabold capitalize">{{ $business->subscriptionIsCurrent() ? 'Active' : 'Expired or suspended' }}</p></div>
                <div class="rounded-2xl border border-[#DDE9E5] bg-white p-5 shadow-sm"><p class="text-xs font-bold text-slate-500">Paid plan ends</p><p class="mt-2 text-xl font-extrabold">{{ $business->subscription_ends_at?->timezone('Asia/Manila')->format('M d, Y') ?? 'No end date' }}</p></div>
            </section>

            <section class="rounded-2xl border border-[#DDE9E5] bg-white p-5 shadow-sm sm:p-6" aria-labelledby="plan-limits-title">
                <h2 id="plan-limits-title" class="text-lg font-extrabold">Plan limits</h2>
                <p class="mt-1 text-sm text-slate-600">Your business uses {{ $staffCount }} of {{ $currentLimits['staff'] ?? 'unlimited' }} staff accounts and {{ $productCount }} of {{ $currentLimits['products'] ?? 'unlimited' }} product slots. Existing records stay available if you switch to a smaller plan; new staff and products wait until you are below its limit.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead><tr class="border-b border-[#DDE9E5] text-slate-600"><th scope="col" class="py-2 pr-4">Plan</th><th scope="col" class="py-2 pr-4">Staff accounts</th><th scope="col" class="py-2 pr-4">Products</th><th scope="col" class="py-2">Monthly price</th></tr></thead>
                        <tbody>
                            @foreach($tiers as $tier => $limits)
                                <tr class="border-b border-[#EAF2EF] last:border-0"><th scope="row" class="py-2 pr-4 font-bold capitalize">{{ $tier }}</th><td class="py-2 pr-4">{{ $limits['staff'] ?? 'Unlimited' }}</td><td class="py-2 pr-4">{{ $limits['products'] ?? 'Unlimited' }}</td><td class="py-2 font-semibold">₱{{ number_format($limits['monthly_price_php'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(300px,.9fr)]">
                <section class="rounded-2xl border border-[#DDE9E5] bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-lg font-extrabold">Request a subscription</h2>
                    <p class="mt-1 text-sm text-slate-600">Agree on a payment method with the GastoTrack team, then upload its receipt or screenshot. Sending this form does not activate a plan.</p>
                    @if($hasPendingRequest)
                        <p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm font-semibold text-amber-900">Your request is waiting for review. You can send another after it has been reviewed or removed.</p>
                    @else
                        <form method="POST" action="{{ route('owner.subscriptions.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                            @csrf
                            <div>
                                <label for="plan_tier" class="block text-sm font-bold">Preferred plan</label>
                                <select id="plan_tier" name="plan_tier" required class="mt-1 w-full rounded-xl border-[#CBDDD7] text-sm" aria-describedby="plan-help">
                                    <option value="">Choose a plan</option>
                                    @foreach(\App\Models\SubscriptionRequest::PAID_PLANS as $plan)
                                        <option value="{{ $plan }}" @selected(old('plan_tier') === $plan)>{{ ucfirst($plan) }}</option>
                                    @endforeach
                                </select>
                                <p id="plan-help" class="mt-1 text-xs text-slate-500">Approved paid plans run for 30 days. The Free tier needs no payment request.</p>
                            </div>
                            <div>
                                <label for="payment_method" class="block text-sm font-bold">Payment method used</label>
                                <select id="payment_method" name="payment_method" required class="mt-1 w-full rounded-xl border-[#CBDDD7] text-sm">
                                    <option value="">Choose a method</option>
                                    @foreach(['gcash' => 'GCash', 'maya' => 'Maya', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="payment_reference" class="block text-sm font-bold">Payment reference</label>
                                <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="120" required class="mt-1 w-full rounded-xl border-[#CBDDD7] text-sm" placeholder="Enter the payment reference" aria-describedby="payment-reference-help">
                                <p id="payment-reference-help" class="mt-1 text-xs text-slate-500">Enter the reference shown on your payment receipt.</p>
                            </div>
                            <div>
                                <label for="owner_note" class="block text-sm font-bold">Note for the Super Admin <span class="font-normal text-slate-500">(optional)</span></label>
                                <textarea id="owner_note" name="owner_note" maxlength="1000" rows="3" class="mt-1 w-full rounded-xl border-[#CBDDD7] text-sm" placeholder="Any details needed to check your payment">{{ old('owner_note') }}</textarea>
                            </div>
                            <div>
                                <label for="payment_proof" class="block text-sm font-bold">Proof of payment</label>
                                <input id="payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp" required class="mt-1 block w-full rounded-xl border border-[#CBDDD7] p-3 text-sm" aria-describedby="proof-help">
                                <p id="proof-help" class="mt-1 text-xs text-slate-500">JPEG, PNG, or WebP image, up to 5 MB. Only your business owner and Super Admin can view it.</p>
                            </div>
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#08745D] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#065B4B]">Send request</button>
                        </form>
                    @endif
                </section>

                <section class="overflow-hidden rounded-2xl border border-[#DDE9E5] bg-white shadow-sm">
                    <div class="border-b border-[#DDE9E5] px-5 py-4"><h2 class="text-lg font-extrabold">Your requests</h2><p class="mt-1 text-xs text-slate-500">Super Admin approval is required before your plan changes.</p></div>
                    @forelse($requests as $subscriptionRequest)
                        <article class="border-b border-[#EAF2EF] p-5 last:border-0">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="font-bold capitalize">{{ $subscriptionRequest->plan_tier }} plan</p><p class="mt-1 text-xs text-slate-500">Sent {{ $subscriptionRequest->created_at->timezone('Asia/Manila')->format('M d, Y, g:i A') }}</p></div>
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $subscriptionRequest->status === 'accepted' ? 'bg-emerald-50 text-emerald-800' : ($subscriptionRequest->status === 'rejected' ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800') }}">{{ ucfirst($subscriptionRequest->status) }}</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ str_replace('_', ' ', ucfirst($subscriptionRequest->payment_method)) }} @if($subscriptionRequest->payment_reference)· Ref: {{ $subscriptionRequest->payment_reference }}@endif</p>
                            <a href="{{ route('owner.subscriptions.proof', $subscriptionRequest) }}" target="_blank" rel="noopener" class="mt-3 inline-flex text-sm font-bold text-[#08745D] underline underline-offset-2">View proof image</a>
                        </article>
                    @empty
                        <p class="p-6 text-sm text-slate-600">No subscription requests yet.</p>
                    @endforelse
                    @if($requests->hasPages())<div class="border-t border-[#DDE9E5] p-4">{{ $requests->links() }}</div>@endif
                </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
