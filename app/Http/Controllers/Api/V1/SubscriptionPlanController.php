<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\SubscriptionPlanResolver;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends BaseApiController
{
    public function index(SubscriptionPlanResolver $planResolver): JsonResponse
    {
        $plans = $planResolver->publicPlans();

        return $this->success(SubscriptionPlanResource::collection($plans));
    }

    public function show(string $plan, SubscriptionPlanResolver $planResolver): JsonResponse
    {
        $model = $planResolver->findPublicByCode($plan);

        if ($model === null) {
            abort(404);
        }

        $model->load('entitlements');

        return $this->success(new SubscriptionPlanResource($model));
    }
}
