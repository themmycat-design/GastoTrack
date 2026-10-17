<section>
    <header>
        <h2 class="text-lg font-extrabold text-[#0A2E2A]">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-slate-500">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="gt-label">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" class="gt-field" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="gt-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" class="gt-field" value="{{ old('email', $user->email) }}" required autocomplete="username">
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm text-amber-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="gt-focus ml-1 font-bold text-[#08745D] underline hover:text-[#00A87E]">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="gt-focus gt-btn-primary">{{ __('Save profile') }}</button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-semibold text-[#08745D]"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
