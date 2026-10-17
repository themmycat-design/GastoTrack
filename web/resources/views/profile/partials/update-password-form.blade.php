<section>
    <header>
        <h2 class="text-lg font-extrabold text-[#0A2E2A]">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-slate-500">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="gt-label">{{ __('Current password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="gt-field" autocomplete="current-password">
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password" class="gt-label">{{ __('New password') }}</label>
            <input id="update_password_password" name="password" type="password" class="gt-field" minlength="8" autocomplete="new-password" data-password-strength="update_password_password-strength-indicator">
            <x-password-strength-indicator input-id="update_password_password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="gt-label">{{ __('Confirm password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="gt-field" autocomplete="new-password">
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="gt-focus gt-btn-primary">{{ __('Update password') }}</button>

            @if (session('status') === 'password-updated')
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
