<x-guest-layout>
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F4F8F7] px-5 py-10 sm:px-8">
        <div aria-hidden="true" class="pointer-events-none absolute -left-28 -top-32 h-80 w-80 rounded-full bg-[#00C897]/10 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -right-24 h-96 w-96 rounded-full bg-emerald-300/20 blur-3xl"></div>

        <div class="relative w-full max-w-5xl">
            <a href="{{ url('/') }}" class="gt-focus mb-7 inline-flex items-center gap-2.5 rounded-lg text-xl font-extrabold tracking-tight text-[#08745D]">
                <img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-10 w-10 object-contain">
                GastoTrack
            </a>

            <div class="grid overflow-hidden rounded-[2rem] border border-[#DDE9E5] bg-white shadow-[0_24px_70px_rgba(10,46,42,0.10)] lg:grid-cols-[1.15fr_.85fr]">
                <section class="p-7 sm:p-10 lg:p-12">
                    <div class="mb-7 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#EAF8F2] text-[#00A87E]">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="11" rx="2.5" />
                            <path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3" />
                        </svg>
                    </div>

                    <p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#00A87E]">Secure your owner account</p>
                    <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-4xl">Choose a new password</h1>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-slate-600 sm:text-base sm:leading-7">
                        Create a new password for your GastoTrack account. After it is changed, you’ll need to sign in again.
                    </p>

                    <form method="POST" action="{{ route('password.store') }}" class="mt-7 space-y-5" x-data="{ showPassword: false, showConfirmation: false }">
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-bold text-slate-700">Account email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                                class="gt-focus block w-full rounded-xl border border-[#CAD9D4] bg-white px-4 py-3 text-sm text-[#0A2E2A] shadow-sm transition placeholder:text-slate-400 focus:border-[#00A87E] focus:ring-2 focus:ring-[#00A87E]/20">
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-bold text-slate-700">New password</label>
                            <div class="relative">
                                <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required minlength="8" autocomplete="new-password"
                                    data-password-strength="password-strength-indicator"
                                    class="gt-focus block w-full rounded-xl border border-[#CAD9D4] bg-white px-4 py-3 pr-16 text-sm text-[#0A2E2A] shadow-sm transition placeholder:text-slate-400 focus:border-[#00A87E] focus:ring-2 focus:ring-[#00A87E]/20"
                                    placeholder="Create a strong password">
                                <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide new password' : 'Show new password'"
                                    class="gt-focus absolute inset-y-0 right-0 rounded-r-xl px-4 text-xs font-bold text-[#08745D] hover:bg-[#F4F8F7]" x-text="showPassword ? 'Hide' : 'Show'"></button>
                            </div>
                            <x-password-strength-indicator input-id="password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-bold text-slate-700">Confirm new password</label>
                            <div class="relative">
                                <input id="password_confirmation" :type="showConfirmation ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                                    class="gt-focus block w-full rounded-xl border border-[#CAD9D4] bg-white px-4 py-3 pr-16 text-sm text-[#0A2E2A] shadow-sm transition placeholder:text-slate-400 focus:border-[#00A87E] focus:ring-2 focus:ring-[#00A87E]/20"
                                    placeholder="Enter the new password again">
                                <button type="button" @click="showConfirmation = !showConfirmation" :aria-label="showConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                                    class="gt-focus absolute inset-y-0 right-0 rounded-r-xl px-4 text-xs font-bold text-[#08745D] hover:bg-[#F4F8F7]" x-text="showConfirmation ? 'Hide' : 'Show'"></button>
                            </div>
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <button type="submit" class="gt-focus flex min-h-12 w-full items-center justify-center rounded-xl bg-[#00A87E] px-5 py-3.5 text-sm font-extrabold text-white shadow-sm transition hover:bg-[#08745D]">
                            Reset password
                        </button>
                    </form>

                    <p class="mt-5 text-center text-sm text-slate-600">
                        Need another reset link?
                        <a href="{{ route('password.request') }}" class="gt-focus rounded font-bold text-[#08745D] underline underline-offset-2">Request a new one</a>
                    </p>
                    <p class="mt-3 text-center text-sm">
                        <a href="{{ route('login') }}" class="gt-focus rounded font-bold text-slate-500 hover:text-[#08745D]">Back to sign in</a>
                    </p>
                </section>

                <aside class="relative overflow-hidden bg-gradient-to-br from-[#073B32] via-[#08745D] to-[#00A87E] p-7 text-white sm:p-10 lg:p-12">
                    <div aria-hidden="true" class="absolute -right-16 -top-16 h-56 w-56 rounded-full border border-white/10"></div>
                    <div aria-hidden="true" class="absolute -bottom-24 -left-16 h-64 w-64 rounded-full border border-white/10"></div>

                    <div class="relative">
                        <p class="text-xs font-extrabold uppercase tracking-[.18em] text-emerald-100">Account recovery</p>
                        <h2 class="mt-3 text-2xl font-extrabold tracking-tight">Get back to your workspace.</h2>
                        <p class="mt-3 text-sm leading-6 text-emerald-50/90">Choose a password that only you know, then sign in again to continue.</p>

                        <ol class="mt-9 space-y-6">
                            <li class="flex gap-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-sm font-extrabold text-[#08745D]">1</span>
                                <div class="pt-0.5">
                                    <p class="text-sm font-extrabold">Set a new password</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-100">Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol.</p>
                                </div>
                            </li>
                            <li class="flex gap-4 opacity-85">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/40 text-sm font-bold">2</span>
                                <div class="pt-0.5">
                                    <p class="text-sm font-bold">Use your reset link in time</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-100">This link expires after {{ config('auth.passwords.users.expire') }} minutes. Request another if it expires.</p>
                                </div>
                            </li>
                            <li class="flex gap-4 opacity-85">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/40 text-sm font-bold">3</span>
                                <div class="pt-0.5">
                                    <p class="text-sm font-bold">Sign in again</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-100">Your current browser session will end after the password is changed.</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-guest-layout>
