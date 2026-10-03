<x-guest-layout>
    <main class="min-h-screen lg:grid lg:grid-cols-[.9fr_1.1fr]">
        <section class="relative hidden overflow-hidden bg-gradient-to-br from-emerald-900 via-emerald-700 to-emerald-500 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-emerald-300/10"></div>

            <a href="{{ url('/') }}" class="relative text-2xl font-extrabold tracking-tight">GastoTrack</a>

            <div class="relative max-w-lg">
                <p class="mb-4 text-sm font-bold uppercase tracking-[.2em] text-emerald-200">Set up your business</p>
                <h1 class="text-5xl font-extrabold leading-tight">A clearer view of your shop starts here.</h1>
                <p class="mt-6 text-lg leading-8 text-emerald-50">Create the owner workspace first. You can invite staff and configure products after signing in.</p>

                <ol class="mt-10 space-y-5">
                    <li class="flex items-start gap-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white font-extrabold text-emerald-700">1</span>
                        <div><p class="font-bold">Create your owner account</p><p class="mt-1 text-sm text-emerald-100">Your secure access to reports and settings.</p></div>
                    </li>
                    <li class="flex items-start gap-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white font-extrabold text-emerald-700">2</span>
                        <div><p class="font-bold">Add products and inventory</p><p class="mt-1 text-sm text-emerald-100">Set prices, ingredients, and low-stock levels.</p></div>
                    </li>
                    <li class="flex items-start gap-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white font-extrabold text-emerald-700">3</span>
                        <div><p class="font-bold">Invite your staff</p><p class="mt-1 text-sm text-emerald-100">Give staff access to the daily tools they need.</p></div>
                    </li>
                </ol>
            </div>

            <p class="relative text-sm text-emerald-100">No unnecessary setup. Start with the essentials.</p>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-[#f4f8f7] px-5 py-10 sm:px-10">
            <div class="w-full max-w-xl">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <a href="{{ url('/') }}" class="text-2xl font-extrabold text-emerald-700">GastoTrack</a>
                    <a href="{{ url('/') }}" class="text-sm font-semibold text-gray-500 hover:text-emerald-700">Back home</a>
                </div>

                <div class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-sm sm:p-8">
                    <div class="mb-7">
                        <p class="text-sm font-bold text-emerald-700">Owner registration</p>
                        <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900">Create your workspace</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500">Tell us who you are and which business you manage.</p>
                    </div>

                    <form method="POST" action="{{ route('register') }}" class="space-y-5" x-data="{ showPassword: false }">
                        @csrf

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="name" class="mb-2 block text-sm font-bold text-gray-700">Owner name</label>
                                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                                    class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="Juan Dela Cruz">
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <label for="business_name" class="mb-2 block text-sm font-bold text-gray-700">Business name</label>
                                <input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required autocomplete="organization"
                                    class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="Your shop name">
                                <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="business_type" class="mb-2 block text-sm font-bold text-gray-700">Business type</label>
                                <select id="business_type" name="business_type" required class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">Select type</option>
                                    <option value="cafe" {{ old('business_type') === 'cafe' ? 'selected' : '' }}>Coffee or milk tea shop</option>
                                    <option value="restaurant" {{ old('business_type') === 'restaurant' ? 'selected' : '' }}>Food or bake shop</option>
                                    <option value="retail" {{ old('business_type') === 'retail' ? 'selected' : '' }}>Retail shop</option>
                                    <option value="service" {{ old('business_type') === 'service' ? 'selected' : '' }}>Service business</option>
                                    <option value="other" {{ old('business_type') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                <x-input-error :messages="$errors->get('business_type')" class="mt-2" />
                            </div>
                            <div>
                                <label for="email" class="mb-2 block text-sm font-bold text-gray-700">Email address</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                                    class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="owner@yourshop.com">
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="password" class="mb-2 block text-sm font-bold text-gray-700">Password</label>
                                <div class="relative">
                                    <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="new-password"
                                        class="block w-full rounded-xl border-gray-300 px-4 py-3 pr-16 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        placeholder="At least 8 characters">
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 px-4 text-xs font-bold text-emerald-700" x-text="showPassword ? 'Hide' : 'Show'"></button>
                                </div>
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div>
                                <label for="password_confirmation" class="mb-2 block text-sm font-bold text-gray-700">Confirm password</label>
                                <input id="password_confirmation" :type="showPassword ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                                    class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="Repeat your password">
                            </div>
                        </div>

                        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-800">This creates the owner account. Staff accounts can be added later from Staff Management.</p>

                        <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3.5 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200">
                            Create owner workspace
                        </button>
                    </form>

                    <div class="mt-7 border-t border-gray-100 pt-6 text-center text-sm text-gray-500">
                        Already have an account?
                        <a href="{{ route('login') }}" class="ml-1 font-extrabold text-emerald-700 hover:underline">Sign in</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
