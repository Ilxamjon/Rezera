<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Pricing\PricingPreviewRequest;
use App\Http\Resources\Api\V1\PricingPreviewResource;
use App\Models\Business;
use App\Models\Resource;
use App\Services\Reservations\ReservationQuoteService;
use App\Support\Reservations\ReservationIntervalResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PricingPreviewController extends BaseApiController
{
    public function store(
        PricingPreviewRequest $request,
        Business $business,
        ReservationQuoteService $quoteService,
        ReservationIntervalResolver $intervalResolver,
    ): JsonResponse {
        Gate::authorize('preview', [\App\Models\PricingRule::class, $business]);

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

        $quote = $quoteService->quote(
            user: $request->user(),
            business: $business,
            resource: $resource,
            startAtUtc: $utc['start_at'],
            endAtUtc: $utc['end_at'],
            timezone: $timezone,
            promoCode: $request->input('promo_code'),
        );

        return $this->success(new PricingPreviewResource($quote));
    }
}
