<?php

namespace App\Support\Analytics;

use App\Domain\Analytics\Enums\AnalyticsGranularity;
use Illuminate\Http\Request;

final class AnalyticsFilter
{
    public function __construct(
        public readonly ?string $resourceId = null,
        public readonly ?string $resourceCategoryId = null,
        public readonly ?string $reservationStatus = null,
        public readonly ?string $paymentStatus = null,
        public readonly ?string $customerId = null,
        public readonly AnalyticsGranularity $granularity = AnalyticsGranularity::Day,
        public readonly ?string $sort = null,
        public readonly string $direction = 'desc',
        public readonly int $limit = 10,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $granularity = AnalyticsGranularity::tryFrom((string) $request->string('granularity'))
            ?? AnalyticsGranularity::Day;

        return new self(
            resourceId: $request->filled('resource_id') ? $request->string('resource_id')->toString() : null,
            resourceCategoryId: $request->filled('resource_category_id') ? $request->string('resource_category_id')->toString() : null,
            reservationStatus: $request->filled('status') ? $request->string('status')->toString() : null,
            paymentStatus: $request->filled('payment_status') ? $request->string('payment_status')->toString() : null,
            customerId: $request->filled('customer_id') ? $request->string('customer_id')->toString() : null,
            granularity: $granularity,
            sort: $request->filled('sort') ? $request->string('sort')->toString() : null,
            direction: $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc',
            limit: min(max((int) $request->integer('limit', 10), 1), 100),
        );
    }
}
