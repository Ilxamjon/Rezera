<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\PublicReservationRulesResource;
use App\Models\Business;
use App\Services\Reservations\ReservationRulesEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReservationRulesController extends BaseApiController
{
    public function show(Business $business, ReservationRulesEngine $rulesEngine): JsonResponse
    {
        Gate::authorize('viewPublic', $business);

        $settings = $rulesEngine->getEffectiveSettings($business);

        return $this->success(new PublicReservationRulesResource($settings));
    }
}
