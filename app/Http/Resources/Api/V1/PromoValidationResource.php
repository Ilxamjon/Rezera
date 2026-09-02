<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Promotions\Enums\PromoValidationReason;
use App\Services\Promotions\DiscountResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiscountResult
 */
class PromoValidationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DiscountResult $result */
        $result = $this->resource;

        $data = [
            'valid' => $result->isValid,
            'subtotal' => $result->subtotal,
            'total' => $result->total,
            'currency' => $result->currency,
        ];

        if ($result->isValid && $result->promoCode !== null) {
            $data['code'] = $result->promoCode->code;
            $data['discount_type'] = $result->promoCode->discount_type?->value;
            $data['discount_value'] = $result->promoCode->discount_value;
            $data['discount_amount'] = $result->discountAmount;
        } else {
            $data['reason'] = $result->reason->value;
            if ($result->reason !== PromoValidationReason::InvalidCode && $result->promoCode !== null) {
                $data['code'] = $result->promoCode->code;
            }
        }

        return $data;
    }
}
