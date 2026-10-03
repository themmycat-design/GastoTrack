<x-guest-layout>
    <main class="min-h-screen lg:grid lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-gradient-to-br from-emerald-900 via-emerald-700 to-emerald-500 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-emerald-300/10"></div>

            <a href="{{ url('/') }}" class="relative text-2xl font-extrabold tracking-tight">GastoTrack</a>

            <div class="relative max-w-xl">
                <p class="mb-4 text-sm font-bold uppercase tracking-[.2em] text-emerald-200">Owner workspace</p>
                <h1 class="text-5xl font-extrabold leading-tight">Know where your money goes. Run your shop with confidence.</h1>
                <p class="mt-6 max-w-lg text-lg leading-8 text-emerald-50">One place for sales, expenses, inventory, staff activity, and practical business insights.</p>

                <div class="mt-10 grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                        <p class="text-2xl font-extrabold">Live</p>
                        <p class="mt-1 text-xs text-emerald-100">Financial overview</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                        <p class="text-2xl font-extrabold">Low stock</p>
                        <p class="mt-1 text-xs text-emerald-100">Early warnings</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                        <p class="text-2xl font-extrabold">Secure</p>
                        <p class="mt-1 text-xs text-emerald-100">Role-based access</p>
                    </div>
                </div>
            </div>

            <p class="relative text-sm text-emerald-100">Built for coffee, milk tea, and bake shops.</p>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-[#f4f8f7] px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <a href="{{ url('/') }}" class="text-2xl font-extrabold text-emerald-700">GastoTrack</a>
                    <a href="{{ url('/') }}" class="text-sm font-semibold text-gray-500 hover:text-emerald-700">Back home</a>
                </div>

                <div class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-sm sm:p-8">
                    <div class="mb-8">
                        <p class="text-sm font-bold text-emerald-700">Welcome back</p>
                        <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900">Sign in to your account</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500">Use the email and password connected to your GastoTrack business.</p>
                    </div>

                    <x-auth-session-status class="mb-5 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700" :status="session('status')" />

                    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPassword: false }">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-bold text-gray-700">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm transition focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="owner@yourshop.com">
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label for="password" class="text-sm font-bold text-gray-700">Password</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-xs font-bold text-emerald-700 hover:underline">Forgot password?</a>
                                @endif
                            </div>
                            <div class="relative">
                                <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password"
                                    class="block w-full rounded-xl border-gray-300 px-4 py-3 pr-16 text-sm shadow-sm transition focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="Enter your password">
                                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 px-4 text-xs font-bold text-emerald-700" x-text="showPassword ? 'Hide' : 'Show'"></button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <label class="flex cursor-pointer items-center gap-3 text-sm text-gray-600">
                            <input type="checkbox" name="remember" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            Keep me signed in on this device
                        </label>

                        <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3.5 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200">
                            Sign in
                        </button>
                    </form>

                    <div class="mt-7 border-t border-gray-100 pt-6 text-center text-sm text-gray-500">
                        New to GastoTrack?
                        <a href="{{ route('register') }}" class="ml-1 font-extrabold text-emerald-700 hover:underline">Create an owner account</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
