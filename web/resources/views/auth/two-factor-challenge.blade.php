<x-guest-layout>
    <main class="flex min-h-screen items-center justify-center bg-[#f4f8f7] px-5 py-10">
        <section class="w-full max-w-md rounded-3xl border border-emerald-100 bg-white p-7 shadow-sm sm:p-9">
            <a href="{{ url('/') }}" class="text-sm font-extrabold text-emerald-700">GastoTrack</a>
            <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-gray-900">Verify it’s you</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600">Enter the six-digit code from your authenticator app, or use a recovery code.</p>
            <p class="mt-2 text-xs leading-5 text-gray-500">After verification, this browser will be remembered for 30 days. You will still need your password to sign in.</p>

            @if ($errors->any())
                <div role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 space-y-4">
                @csrf
                <label for="code" class="block text-sm font-bold text-gray-700">Authenticator code</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" autofocus
                    class="block w-full rounded-xl border-gray-300 px-4 py-3 text-center text-lg tracking-[.35em] shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    placeholder="000000">
                <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 font-extrabold text-white hover:bg-emerald-700">Continue</button>
            </form>

            <div class="my-6 border-t border-gray-100"></div>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
                @csrf
                <label for="recovery_code" class="block text-sm font-bold text-gray-700">Use a recovery code</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="off"
                    class="block w-full rounded-xl border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    placeholder="Enter one unused recovery code">
                <button type="submit" class="w-full rounded-xl border border-emerald-200 px-4 py-3 font-bold text-emerald-800 hover:bg-emerald-50">Use recovery code</button>
            </form>
        </section>
    </main>
</x-guest-layout>
