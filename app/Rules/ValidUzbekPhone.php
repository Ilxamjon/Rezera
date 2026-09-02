<?php

namespace App\Rules;

use App\Support\Phone\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class ValidUzbekPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            PhoneNormalizer::normalize((string) $value);
        } catch (InvalidArgumentException) {
            $fail(__('validation.phone'));
        }
    }
}
