@props(['passwordId' => 'password', 'confirmationId' => 'password_confirmation'])

@php($indicatorId = $confirmationId.'-match-indicator')

<p id="{{ $indicatorId }}" data-password-match-for="{{ $confirmationId }}" data-password-match-password="{{ $passwordId }}" class="mt-2 text-xs font-semibold text-slate-500" aria-live="polite">Re-enter your password to confirm it.</p>

@once
    <script>
        (() => {
            const initializePasswordMatches = () => {
                document.querySelectorAll('[data-password-match-for]').forEach(indicator => {
                    const password = document.getElementById(indicator.dataset.passwordMatchPassword);
                    const confirmation = document.getElementById(indicator.dataset.passwordMatchFor);
                    if (!password || !confirmation) return;

                    const update = () => {
                        const hasPassword = password.value.length > 0;
                        const entered = confirmation.value.length > 0;
                        const matches = entered && hasPassword && confirmation.value === password.value;
                        const incomplete = !confirmation.required && hasPassword && !entered;
                        const mismatch = entered && !matches;
                        confirmation.setCustomValidity(mismatch ? 'Passwords do not match.' : incomplete ? 'Confirm the new password.' : '');
                        confirmation.setAttribute('aria-invalid', mismatch || incomplete ? 'true' : 'false');
                        indicator.textContent = !hasPassword && !entered && !confirmation.required
                            ? 'Leave blank to keep the current password.'
                            : incomplete ? 'Confirm the new password.'
                            : !entered ? 'Re-enter your password to confirm it.'
                            : matches ? 'Passwords match.' : 'Passwords do not match.';
                        indicator.classList.remove('text-slate-500', 'text-emerald-700', 'text-rose-700');
                        indicator.classList.add(incomplete || mismatch ? 'text-rose-700' : matches ? 'text-emerald-700' : 'text-slate-500');
                    };

                    password.addEventListener('input', update);
                    confirmation.addEventListener('input', update);
                    window.addEventListener('pageshow', update);
                    update();
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializePasswordMatches, {once: true});
            } else {
                initializePasswordMatches();
            }
        })();
    </script>
@endonce
