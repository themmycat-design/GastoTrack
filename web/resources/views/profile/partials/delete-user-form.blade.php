<section class="space-y-6">
    <header>
        <h2 class="text-lg font-extrabold text-rose-800">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-slate-600">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <button type="button" class="gt-focus gt-btn-danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">{{ __('Delete account') }}</button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-5 sm:p-7">
            @csrf
            @method('delete')

            <h2 class="text-lg font-extrabold text-[#0A2E2A]">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm leading-6 text-slate-600">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <label for="password" class="sr-only">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" class="gt-field" placeholder="{{ __('Password') }}" autocomplete="current-password">

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-col-reverse justify-end gap-3 border-t border-[#EAF2EF] pt-5 sm:flex-row">
                <button type="button" class="gt-focus gt-btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="gt-focus gt-btn-danger">{{ __('Delete account') }}</button>
            </div>
        </form>
    </x-modal>
</section>
