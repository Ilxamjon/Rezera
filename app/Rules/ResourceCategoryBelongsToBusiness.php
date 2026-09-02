<?php

namespace App\Rules;

use App\Models\ResourceGroup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ResourceCategoryBelongsToBusiness implements ValidationRule
{
    public function __construct(
        private readonly string $businessId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $exists = ResourceGroup::query()
            ->where('id', $value)
            ->where('business_id', $this->businessId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $fail(__('resources.category_belongs_to_business'));
        }
    }
}
