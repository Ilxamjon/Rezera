<?php

namespace App\Services\Reservations;

final class ReservationListFilters
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $resourceId = null,
        public readonly ?string $businessId = null,
        public readonly ?string $date = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $search = null,
        public readonly ?string $scope = null,
        public readonly ?string $sort = null,
        public readonly string $sortDirection = 'asc',
        public readonly ?string $timezone = null,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request, ?string $timezone = null): self
    {
        $scope = $request->filled('scope') ? $request->string('scope')->toString() : null;

        if ($request->filled('date') && $request->string('date')->toString() === 'today') {
            $scope = 'today';
        }

        return new self(
            status: $request->filled('status') ? $request->string('status')->toString() : null,
            resourceId: $request->filled('resource_id') ? $request->string('resource_id')->toString() : null,
            businessId: $request->filled('business_id') ? $request->string('business_id')->toString() : null,
            date: $request->filled('date') && $request->string('date')->toString() !== 'today'
                ? $request->string('date')->toString()
                : null,
            from: $request->filled('from') ? $request->string('from')->toString() : null,
            to: $request->filled('to') ? $request->string('to')->toString() : null,
            search: $request->filled('search') ? $request->string('search')->toString() : null,
            scope: $scope,
            sort: $request->filled('sort') ? $request->string('sort')->toString() : null,
            sortDirection: $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc',
            timezone: $timezone,
        );
    }
}
