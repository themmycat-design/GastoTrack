<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordStrength implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $hasMinimumLength = mb_strlen($value) >= 8;
        $hasMixedCase = preg_match('/[a-z]/', $value) === 1 && preg_match('/[A-Z]/', $value) === 1;
        $hasNumber = preg_match('/[0-9]/', $value) === 1;
        $hasSymbol = preg_match('/[^a-zA-Z0-9\s]/u', $value) === 1;

        if (! $hasMinimumLength || ! $hasMixedCase || ! $hasNumber || ! $hasSymbol) {
            $fail('The :attribute must be at least 8 characters and include uppercase and lowercase letters, a number, and a symbol.');
        }
    }
}
