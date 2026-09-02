<?php

namespace App\Http\Resources\Api\V1;

use App\Data\Reservations\EffectiveReservationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EffectiveReservationSettings */
class PublicReservationRulesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EffectiveReservationSettings $settings */
        $settings = $this->resource;

        return $settings->toPublicArray();
    }
}
