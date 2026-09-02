<?php

namespace App\Support\Calendar;

final class CalendarFilter
{
    public function __construct(
        public readonly ?string $resourceId = null,
        public readonly ?string $resourceCategoryId = null,
        public readonly bool $includeCancelled = false,
        public readonly bool $includeAvailableGaps = true,
        public readonly bool $detailed = false,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            resourceId: $request->filled('resource_id') ? $request->string('resource_id')->toString() : null,
            resourceCategoryId: $request->filled('resource_category_id')
                ? $request->string('resource_category_id')->toString()
                : null,
            includeCancelled: $request->boolean('include_cancelled'),
            includeAvailableGaps: ! $request->boolean('hide_available_gaps'),
            detailed: $request->boolean('detailed'),
        );
    }
}
