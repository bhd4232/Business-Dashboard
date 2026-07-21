<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class AccessibleCompany implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if (! filled($value) || ! $user || ! $user->canAccessCompany((int) $value)) {
            $fail('You are not allowed to manage storefront media for the selected company.');
        }
    }
}
