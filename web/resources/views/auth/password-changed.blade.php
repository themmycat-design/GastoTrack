<x-guest-layout>
    <main class="min-h-screen lg:grid lg:grid-cols-[.9fr_1.1fr]">
        <section class="relative hidden overflow-hidden bg-gradient-to-br from-emerald-900 via-emerald-700 to-emerald-500 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div aria-hidden="true" class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10"></div>
            <div aria-hidden="true" class="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-emerald-300/10"></div>

            <a href="{{ url('/') }}" class="relative inline-flex items-center gap-3 text-2xl font-extrabold tracking-tight"><img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-11 w-11 object-contain">GastoTrack</a>

            <div class="relative max-w-xl">
                <p class="mb-4 text-sm font-bold uppercase tracking-[.2em] text-emerald-200">Owner workspace</p>
                <h2 class="text-5xl font-extrabold leading-tight">Your shop is ready when you are.</h2>
                <p class="mt-6 max-w-lg text-lg leading-8 text-emerald-50">Sign in with your new password to return to your business workspace.</p>
            </div>

            <p class="relative text-sm text-emerald-100">Built for coffee, milk tea, and bake shops.</p>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-[#f4f8f7] px-5 py-10 sm:px-10">
            <div class="w-full max-w-xl">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-2xl font-extrabold text-emerald-700"><img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-9 w-9 object-contain">GastoTrack</a>
                    <a href="{{ url('/') }}" class="text-sm font-semibold text-gray-500 hover:text-emerald-700">Back home</a>
                </div>

                <div class="rounded-3xl border border-emerald-100 bg-white px-5 py-6 shadow-sm sm:px-6 sm:py-7">
                    <div aria-hidden="true" class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7"><path d="M5 12.5l4.5 4.5L19 7" /></svg>
                    </div>
                    <p class="text-sm font-bold text-emerald-700">Account recovery complete</p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-gray-900">Your password has changed</h1>
                    <p class="mt-2 text-sm leading-6 text-gray-500">You are signed out. Sign in with your new password to continue. Owner accounts will need an authenticator code again.</p>

                    <a href="{{ route('login') }}" class="mt-6 flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200">
                        Go to sign in
                    </a>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
