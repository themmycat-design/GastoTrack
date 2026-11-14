<x-guest-layout>
    <main class="min-h-screen lg:grid lg:grid-cols-[.9fr_1.1fr]">
        <section class="relative hidden overflow-hidden bg-gradient-to-br from-emerald-900 via-emerald-700 to-emerald-500 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div aria-hidden="true" class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10"></div>
            <div aria-hidden="true" class="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-emerald-300/10"></div>

            <a href="{{ url('/') }}" class="relative inline-flex items-center gap-3 text-2xl font-extrabold tracking-tight"><img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-11 w-11 object-contain">GastoTrack</a>

            <div class="relative max-w-xl">
                <p class="mb-4 text-sm font-bold uppercase tracking-[.2em] text-emerald-200">Owner workspace</p>
                <h1 class="text-5xl font-extrabold leading-tight">Get back to your shop with confidence.</h1>
                <p class="mt-6 max-w-lg text-lg leading-8 text-emerald-50">Reset your password to return to your sales, inventory, and business insights.</p>
            </div>

            <p class="relative text-sm text-emerald-100">Built for coffee, milk tea, and bake shops.</p>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-[#f4f8f7] px-5 py-10 sm:px-10">
            <div class="w-full max-w-xl">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-2xl font-extrabold text-emerald-700"><img src="{{ asset('images/gastotrack-logo.png') }}" alt="" class="h-9 w-9 object-contain">GastoTrack</a>
                    <a href="{{ url('/') }}" class="text-sm font-semibold text-gray-500 hover:text-emerald-700">Back home</a>
                </div>

                <div class="rounded-3xl border border-emerald-100 bg-white px-5 py-4 shadow-sm sm:px-6 sm:py-5">
                    <div class="mb-4">
                        <p class="text-sm font-bold text-emerald-700">Account recovery</p>
                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-gray-900">Forgot your password?</h1>
                        <p class="mt-1 text-sm leading-5 text-gray-500">Enter the email connected to your GastoTrack business. We’ll send you a link to choose a new password.</p>
                    </div>

                    <x-auth-session-status class="mb-3 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700" :status="session('status')" />

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="email" class="mb-1 block text-sm font-bold text-gray-700">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                                class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm transition focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="owner@yourshop.com">
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200">
                            Send reset link
                        </button>
                    </form>

                    <div class="mt-4 border-t border-gray-100 pt-4 text-center text-sm text-gray-500">
                        Remember your password?
                        <a href="{{ route('login') }}" class="ml-1 font-extrabold text-emerald-700 hover:underline">Back to sign in</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
