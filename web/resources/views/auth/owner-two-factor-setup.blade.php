<x-guest-layout>
    <main class="flex min-h-screen items-center justify-center bg-[#f4f8f7] px-5 py-10">
        <section class="w-full max-w-xl rounded-3xl border border-emerald-100 bg-white p-6 shadow-sm sm:p-9">
            <a href="{{ url('/') }}" class="text-sm font-extrabold text-emerald-700">GastoTrack</a>
            <p class="mt-5 text-sm font-bold uppercase tracking-wide text-emerald-700">Required account security</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-gray-900">Set up your authenticator app</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600">Scan the QR code with Google Authenticator, Microsoft Authenticator, or another TOTP app. You can also enter the setup key manually.</p>
            <p class="mt-2 text-xs leading-5 text-gray-500">After you confirm the code, this browser will be remembered for 30 days. You will still need your password to sign in.</p>

            <div class="mt-6 flex justify-center rounded-2xl border border-gray-100 bg-white p-4">
                <div class="h-48 w-48 [&_svg]:h-full [&_svg]:w-full">{!! $qrCodeSvg !!}</div>
            </div>

            <div class="mt-5">
                <label for="secret-key" class="block text-sm font-bold text-gray-700">Manual setup key</label>
                <input id="secret-key" readonly value="{{ $secretKey }}" onclick="this.select()"
                    class="mt-2 block w-full rounded-xl border-gray-300 bg-gray-50 px-4 py-3 font-mono text-sm tracking-wider text-gray-900">
                <p class="mt-2 text-xs leading-5 text-gray-500">Keep this key private. Anyone who has it can generate sign-in codes for your account.</p>
            </div>

            <form method="POST" action="{{ route('owner.two-factor.confirm') }}" class="mt-7 space-y-4">
                @csrf
                <label for="code" class="block text-sm font-bold text-gray-700">Enter the code shown in your app</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required
                    class="block w-full rounded-xl border-gray-300 px-4 py-3 text-center text-lg tracking-[.35em] shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    placeholder="000000">
                @error('code')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 font-extrabold text-white hover:bg-emerald-700">Confirm authenticator</button>
            </form>
        </section>
    </main>
</x-guest-layout>
