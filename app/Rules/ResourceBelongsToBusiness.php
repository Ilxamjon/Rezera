<?php

namespace App\Rules;

use App\Models\Resource;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ResourceBelongsToBusiness implements ValidationRule
{
    public function __construct(
        private readonly string $businessId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Resource::query()
            ->where('id', $value)
            ->where('business_id', $this->businessId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $fail(__('reservations.resource_not_in_business'));
        }
    }
}
