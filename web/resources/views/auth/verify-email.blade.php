<x-guest-layout>
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F4F8F7] px-5 py-10 sm:px-8">
        <div aria-hidden="true" class="pointer-events-none absolute -left-28 -top-32 h-80 w-80 rounded-full bg-[#00C897]/10 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -right-24 h-96 w-96 rounded-full bg-emerald-300/20 blur-3xl"></div>

        <div class="relative w-full max-w-5xl">
            <a href="{{ url('/') }}" class="mb-7 inline-flex items-center gap-2.5 text-xl font-extrabold tracking-tight text-[#08745D]">
                <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-10 w-10 object-contain">
                GastoTrack
            </a>

            <div class="grid overflow-hidden rounded-[2rem] border border-[#DDE9E5] bg-white shadow-[0_24px_70px_rgba(10,46,42,0.10)] lg:grid-cols-[1.15fr_.85fr]">
                <section class="p-7 sm:p-10 lg:p-12">
                    <div class="mb-7 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#00A87E]">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="3" />
                            <path d="m4 7 8 6 8-6" />
                        </svg>
                    </div>

                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#00A87E]">Secure your owner account</p>
                    <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-4xl">Verify your email</h1>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-slate-600 sm:text-base sm:leading-7">
                        We sent a verification link to the email below. Open the message and follow its link to confirm it’s yours.
                    </p>

                    <div class="mt-6 flex items-center gap-3 rounded-xl border border-[#DDE9E5] bg-[#F8FBF9] px-4 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[#08745D] shadow-sm">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Verification email sent to</p>
                            <p class="mt-0.5 break-all text-sm font-bold text-[#0A2E2A]">{{ auth()->user()->email }}</p>
                        </div>
                    </div>

                    @if (session('status') === 'verification-link-sent')
                        <div role="status" class="mt-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                            <p>A fresh verification link has been sent. Check your inbox and spam folder.</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('verification.send') }}" class="mt-7">
                        @csrf
                        <button type="submit" class="gt-focus flex w-full items-center justify-center gap-2 rounded-xl bg-[#00A87E] px-5 py-3.5 text-sm font-extrabold text-white shadow-sm transition hover:bg-[#08745D]">
                            Resend verification email
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>
                        </button>
                    </form>

                    <div class="mt-5 flex flex-col items-center justify-between gap-4 border-t border-[#EAF2EF] pt-5 text-sm sm:flex-row">
                        <p class="text-slate-500">Check spam or junk if it’s not in your inbox.</p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="gt-focus rounded-lg px-3 py-2 font-bold text-slate-600 transition hover:bg-[#F4F8F7] hover:text-[#08745D]">Sign out</button>
                        </form>
                    </div>
                </section>

                <aside class="relative overflow-hidden bg-gradient-to-br from-[#073B32] via-[#08745D] to-[#00A87E] p-7 text-white sm:p-10 lg:p-12">
                    <div aria-hidden="true" class="absolute -right-16 -top-16 h-56 w-56 rounded-full border border-white/10"></div>
                    <div aria-hidden="true" class="absolute -bottom-24 -left-16 h-64 w-64 rounded-full border border-white/10"></div>

                    <div class="relative">
                        <p class="text-xs font-extrabold uppercase tracking-[.18em] text-emerald-100">Your account setup</p>
                        <h2 class="mt-3 text-2xl font-extrabold tracking-tight">Almost ready to get started.</h2>
                        <p class="mt-3 text-sm leading-6 text-emerald-50/90">Email verification helps protect your business workspace and keeps registration details accurate.</p>

                        <ol class="mt-9 space-y-6">
                            <li class="flex gap-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-sm font-extrabold text-[#08745D]">1</span>
                                <div class="pt-0.5">
                                    <p class="text-sm font-extrabold">Verify your email</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-100">You’re here — confirm the address you registered.</p>
                                </div>
                            </li>
                            <li class="flex gap-4 opacity-80">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/40 text-sm font-bold">2</span>
                                <div class="pt-0.5">
                                    <p class="text-sm font-bold">Business review</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-100">A Super Admin reviews the registration.</p>
                                </div>
                            </li>
                            <li class="flex gap-4 opacity-80">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/40 text-sm font-bold">3</span>
                                <div class="pt-0.5">
                                    <p class="text-sm font-bold">Secure your workspace</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-100">After approval, set up an authenticator app.</p>
                                </div>
                            </li>
                        </ol>

                        <p class="mt-10 border-t border-white/15 pt-5 text-xs leading-5 text-emerald-100">Once you verify, we’ll take you to your business registration status.</p>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-guest-layout>
