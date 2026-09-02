<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Promo\ValidatePromoCodeRequest;
use App\Http\Resources\Api\V1\PromoValidationResource;
use App\Models\Business;
use App\Models\Resource;
use App\Services\Promotions\DiscountEngine;
use App\Support\Reservations\ReservationIntervalResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PromoCodeValidationController extends BaseApiController
{
    public function store(
        ValidatePromoCodeRequest $request,
        Business $business,
        DiscountEngine $discountEngine,
        ReservationIntervalResolver $intervalResolver,
    ): JsonResponse {
        Gate::authorize('validate', [\App\Models\PromoCode::class, $business]);

        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $date = CarbonImmutable::parse($request->input('date'), $timezone)->startOfDay();
        $interval = $intervalResolver->resolve(
            $date,
            $request->input('start_time'),
            $request->input('end_time'),
            $timezone,
        );
        $utc = $intervalResolver->toUtcPayload($interval);

        $resource = Resource::query()
            ->where('id', $request->input('resource_id'))
            ->where('business_id', $business->id)
            ->firstOrFail();

        $result = $discountEngine->preview(
            user: $request->user(),
            business: $business,
            resource: $resource,
            startAtUtc: $utc['start_at'],
            endAtUtc: $utc['end_at'],
            timezone: $timezone,
            code: $request->input('code'),
        );

        return $this->success(new PromoValidationResource($result));
    }
}
