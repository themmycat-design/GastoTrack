<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Status - GastoTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <main class="w-full max-w-lg bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center">
        <div class="mx-auto mb-5 h-14 w-14 rounded-full flex items-center justify-center {{ auth()->user()->status === 'active' && $business?->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700' }}">
            <span class="text-2xl">{{ auth()->user()->status === 'active' && $business?->status === 'pending' ? '⏳' : '!' }}</span>
        </div>
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">GastoTrack</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">
            @if(auth()->user()->status !== 'active')
                Owner account deactivated
            @elseif($business?->status === 'pending')
                Awaiting platform approval
            @elseif($business?->status === 'suspended')
                Business account suspended
            @else
                Business account unavailable
            @endif
        </h1>
        <p class="mt-3 text-slate-600">
            @if(auth()->user()->status !== 'active')
                Contact the GastoTrack platform administrator to restore access to your owner account.
            @elseif($business?->status === 'pending')
                {{ $business->name }} has been registered. A Super Administrator must approve it before the owner and staff can use business features.
            @elseif($business?->suspension_reason)
                {{ $business->suspension_reason }}
            @else
                Contact the GastoTrack platform administrator for assistance.
            @endif
        </p>
        <form method="POST" action="{{ route('logout') }}" class="mt-7">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white hover:bg-slate-800">Sign out</button>
        </form>
    </main>
</body>
</html>
