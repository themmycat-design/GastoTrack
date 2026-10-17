<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/gastotrack-logo.png') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profile settings | GastoTrack</title>
    @include('layouts.owner-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased">
<div class="min-h-screen lg:flex">
    @include('layouts.owner-navigation')
    <main class="min-w-0 flex-1">
        @include('layouts.owner-topbar')
        <div class="mx-auto max-w-[1600px] px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
            <div class="gt-enter mx-auto max-w-4xl space-y-6 pb-10">
                <header>
                    <p class="text-sm font-semibold text-[#00A87E]">Account settings</p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Your profile</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Update your sign-in details and keep your account secure.</p>
                </header>

                @if(session('status') === 'verification-link-sent')
                    <p role="status" class="rounded-xl border border-[#C8DED7] bg-[#EAF8F2] px-4 py-3 text-sm font-semibold text-[#08745D]">A new verification link has been sent to your email address.</p>
                @endif

                <section class="gt-card p-5 sm:p-7">
                    @include('profile.partials.update-profile-information-form')
                </section>

                <section class="gt-card p-5 sm:p-7">
                    @include('profile.partials.update-password-form')
                </section>

                <section class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm sm:p-7">
                    @include('profile.partials.delete-user-form')
                </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
