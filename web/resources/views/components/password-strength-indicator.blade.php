@props(['inputId'])

@php($indicatorId = $inputId.'-strength-indicator')

<div id="{{ $indicatorId }}" class="mt-2" aria-live="polite">
    <div class="flex items-center justify-between gap-3 text-xs">
        <span class="font-semibold text-slate-600">Password strength</span>
        <span data-strength-label class="font-bold text-slate-500">Not entered</span>
    </div>
    <div class="mt-2 flex gap-1.5" aria-hidden="true">
        @for($segment = 1; $segment <= 3; $segment++)
            <span data-strength-segment="{{ $segment }}" class="h-1.5 flex-1 rounded-full bg-slate-200 transition-colors"></span>
        @endfor
    </div>
    <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1.5 text-[11px]">
        <span data-strength-check="length" class="inline-flex items-center gap-1.5 text-slate-500"><span aria-hidden="true">○</span>8+ characters</span>
        <span data-strength-check="case" class="inline-flex items-center gap-1.5 text-slate-500"><span aria-hidden="true">○</span>Upper + lowercase</span>
        <span data-strength-check="number" class="inline-flex items-center gap-1.5 text-slate-500"><span aria-hidden="true">○</span>A number</span>
        <span data-strength-check="symbol" class="inline-flex items-center gap-1.5 text-slate-500"><span aria-hidden="true">○</span>A symbol</span>
    </div>
</div>

@once
    <script>
        (() => {
            const initializePasswordStrength = () => {
                const update = input => {
                    const indicator = document.getElementById(input.dataset.passwordStrength);
                    if (!indicator) return;

                    const password = input.value;
                    const length = Array.from(password).length;
                    const hasMixedCase = /[a-z]/.test(password) && /[A-Z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const hasSymbol = /[^a-zA-Z0-9\s]/u.test(password);
                    const requirements = {
                        length: length >= 8,
                        case: hasMixedCase,
                        number: hasNumber,
                        symbol: hasSymbol,
                    };
                    const traitCount = [hasMixedCase, hasNumber, hasSymbol].filter(Boolean).length;
                    const strength = !password ? 'empty' : (
                        length >= 8 && hasMixedCase && hasNumber && hasSymbol ? 'strong' :
                        length >= 8 && traitCount >= 2 ? 'moderate' : 'weak'
                    );

                    const labels = {
                        empty: input.required ? 'Not entered' : 'Optional',
                        weak: 'Weak · invalid',
                        moderate: 'Moderate · not yet valid',
                        strong: 'Strong · valid',
                    };
                    const labelColors = {
                        empty: 'text-slate-500',
                        weak: 'text-rose-700',
                        moderate: 'text-amber-700',
                        strong: 'text-emerald-700',
                    };
                    const segmentColors = {
                        weak: ['bg-rose-500', 'bg-slate-200', 'bg-slate-200'],
                        moderate: ['bg-amber-500', 'bg-amber-500', 'bg-slate-200'],
                        strong: ['bg-emerald-500', 'bg-emerald-500', 'bg-emerald-500'],
                        empty: ['bg-slate-200', 'bg-slate-200', 'bg-slate-200'],
                    };

                    const label = indicator.querySelector('[data-strength-label]');
                    label.textContent = labels[strength];
                    label.className = `font-bold ${labelColors[strength]}`;
                    indicator.querySelectorAll('[data-strength-segment]').forEach((segment, index) => {
                        segment.className = `h-1.5 flex-1 rounded-full transition-colors ${segmentColors[strength][index]}`;
                    });
                    indicator.querySelectorAll('[data-strength-check]').forEach(check => {
                        const passed = requirements[check.dataset.strengthCheck];
                        check.className = `inline-flex items-center gap-1.5 ${passed ? 'font-semibold text-emerald-700' : 'text-slate-500'}`;
                        check.querySelector('span').textContent = passed ? '✓' : '○';
                    });

                    input.setCustomValidity(password && strength !== 'strong'
                        ? 'Use at least 8 characters, uppercase and lowercase letters, a number, and a symbol.'
                        : '');
                };

                document.querySelectorAll('[data-password-strength]').forEach(input => {
                    input.addEventListener('input', () => update(input));
                    update(input);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializePasswordStrength, {once: true});
            } else {
                initializePasswordStrength();
            }
        })();
    </script>
@endonce
