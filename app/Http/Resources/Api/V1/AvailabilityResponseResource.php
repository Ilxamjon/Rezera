<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Availability\Enums\AvailabilityReason;
use App\Services\Availability\AvailabilityResult;
use App\Services\Availability\ResourceAvailabilityResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResponseResource extends JsonResource
{
    /**
     * @param  AvailabilityResult  $resource
     */
    public function toArray(Request $request): array
    {
        /** @var AvailabilityResult $result */
        $result = $this->resource;
        $managementView = (bool) $request->attributes->get('availability_management_view', false);

        return [
            'business' => [
                'id' => $result->business->id,
                'name' => $result->business->name,
                'timezone' => $result->businessTimezone(),
            ],
            'date' => $result->date->format('Y-m-d'),
            'start_time' => $result->startTime,
            'end_time' => $result->endTime,
            'request_status' => $result->requestLevelReason?->value ?? AvailabilityReason::Available->value,
            'resources' => collect($result->resources)
                ->map(fn (ResourceAvailabilityResult $item): array => $this->mapResource($item, $managementView))
                ->values()
                ->all(),
        ];
    }

    private function mapResource(ResourceAvailabilityResult $item, bool $managementView): array
    {
        $payload = [
            'id' => $item->resource->id,
            'name' => $item->resource->name,
            'code' => $item->resource->code,
            'resource_type' => $item->resource->resource_type?->value,
            'category' => $item->resource->relationLoaded('group') && $item->resource->group !== null
                ? new ResourceCategoryResource($item->resource->group)
                : null,
            'status' => $item->status(),
            'price' => $item->resource->hourly_rate_amount,
            'price_unit' => $item->resource->rate_unit?->value ?? 'hour',
            'currency' => $item->resource->currency,
        ];

        if ($managementView || $item->reason !== AvailabilityReason::Available) {
            $payload['reason'] = $item->reason->value;
        }

        return $payload;
    }
}
