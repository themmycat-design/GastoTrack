<x-guest-layout>
    <main class="flex min-h-screen items-center justify-center bg-[#f4f8f7] px-5 py-10">
        <section class="w-full max-w-xl rounded-3xl border border-emerald-100 bg-white p-6 shadow-sm sm:p-9">
            <a href="{{ url('/') }}" class="text-sm font-extrabold text-emerald-700">GastoTrack</a>
            <p class="mt-5 text-sm font-bold uppercase tracking-wide text-emerald-700">Authenticator enabled</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-gray-900">Save your recovery codes</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600">Each code can be used once if you lose access to your authenticator. Store them somewhere private. This page will not show them again.</p>

            <ul class="mt-6 grid grid-cols-2 gap-3 rounded-2xl bg-gray-50 p-4 font-mono text-sm text-gray-900 sm:grid-cols-4">
                @foreach ($recoveryCodes as $code)
                    <li class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-center">{{ $code }}</li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('owner.two-factor.recovery-codes.acknowledge') }}" class="mt-7">
                @csrf
                <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 font-extrabold text-white hover:bg-emerald-700">I’ve saved my codes</button>
            </form>
        </section>
    </main>
</x-guest-layout>
