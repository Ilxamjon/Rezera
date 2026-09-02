<?php

namespace App\Http\Resources\Api\V1;

use App\Data\Businesses\BusinessStaffCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessStaffCapabilities */
class BusinessStaffCapabilitiesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BusinessStaffCapabilities $capabilities */
        $capabilities = $this->resource;

        return $capabilities->toArray();
    }
}
