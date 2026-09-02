<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $quote */
        $quote = $this->resource;

        return [
            'currency' => $quote['currency'],
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'segments' => $quote['segments'],
            'promo' => $quote['promo'] ?? null,
        ];
    }
}
