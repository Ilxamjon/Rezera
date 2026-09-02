<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\Phone\PhoneNormalizer;
use InvalidArgumentException;

trait NormalizesPhoneInput
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('phone')) {
            return;
        }

        try {
            $this->merge([
                'phone' => PhoneNormalizer::normalize($this->string('phone')->toString()),
            ]);
        } catch (InvalidArgumentException) {
            // Leave the original value; ValidUzbekPhone will fail validation.
        }
    }
}
