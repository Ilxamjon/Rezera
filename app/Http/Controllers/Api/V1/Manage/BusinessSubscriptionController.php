<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Subscriptions\SubscribeBusinessAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Subscription\CancelBusinessSubscriptionRequest;
use App\Http\Requests\Api\V1\Subscription\SubscribeBusinessRequest;
use App\Http\Resources\Api\V1\BusinessSubscriptionResource;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Models\Business;
use App\Services\Subscriptions\BusinessEntitlementService;
use App\Services\Subscriptions\BusinessUsageService;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use App\Services\Subscriptions\SubscriptionPlanResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessSubscriptionController extends BaseApiController
{
    public function show(
        Business $business,
        BusinessEntitlementService $entitlements,
        BusinessUsageService $usage,
        SubscriptionPlanResolver $planResolver,
    ): JsonResponse {
        Gate::authorize('viewManagement', $business);

        $subscription = $entitlements->effectiveSubscription($business);
        $plan = $entitlements->effectivePlan($business);
        $upgradeOptions = $planResolver->publicPlans()
            ->filter(fn ($candidate) => $candidate->sort_order > $plan->sort_order)
            ->values();

        return $this->success([
            'subscription' => $subscription
                ? new BusinessSubscriptionResource($subscription->load(['plan.entitlements', 'pendingPlan.entitlements']))
                : null,
            'plan' => new SubscriptionPlanResource($plan->load('entitlements')),
            'entitlements' => $entitlements->entitlements($business),
            'usage' => $usage->summary($business),
            'upgrade_options' => SubscriptionPlanResource::collection($upgradeOptions),
        ]);
    }

    public function store(
        SubscribeBusinessRequest $request,
        Business $business,
        SubscribeBusinessAction $subscribeBusiness,
    ): JsonResponse {
        Gate::authorize('manageSubscription', $business);

        $result = $subscribeBusiness->execute(
            business: $business,
            actor: $request->user(),
            planCode: $request->validated('plan_code'),
            billingInterval: $request->validated('billing_interval'),
        );

        return $this->success([
            'subscription' => $result['subscription']
                ? new BusinessSubscriptionResource($result['subscription'])
                : null,
            'payment' => $result['payment'] ? new PaymentResource($result['payment']) : null,
            'requires_payment' => $result['requires_payment'],
        ], $result['requires_payment']
            ? __('subscriptions.payment_required')
            : __('subscriptions.updated'));
    }

    public function usage(Business $business, BusinessUsageService $usage): JsonResponse
    {
        Gate::authorize('viewManagement', $business);

        return $this->success($usage->summary($business));
    }

    public function cancel(
        CancelBusinessSubscriptionRequest $request,
        Business $business,
        BusinessEntitlementService $entitlements,
        SubscriptionLifecycleService $lifecycle,
    ): JsonResponse {
        Gate::authorize('manageSubscription', $business);

        $subscription = $entitlements->effectiveSubscription($business);

        if ($subscription === null) {
            abort(404);
        }

        $updated = $lifecycle->cancel(
            subscription: $subscription,
            atPeriodEnd: $request->boolean('at_period_end', true),
            reason: $request->validated('reason'),
            actor: $request->user(),
        );

        return $this->success(
            new BusinessSubscriptionResource($updated->load(['plan.entitlements', 'pendingPlan.entitlements'])),
            __('subscriptions.cancelled'),
        );
    }

    public function resume(
        Business $business,
        BusinessEntitlementService $entitlements,
        SubscriptionLifecycleService $lifecycle,
    ): JsonResponse {
        Gate::authorize('manageSubscription', $business);

        $subscription = $entitlements->effectiveSubscription($business);

        if ($subscription === null) {
            abort(404);
        }

        $updated = $lifecycle->resume($subscription, request()->user());

        return $this->success(
            new BusinessSubscriptionResource($updated->load(['plan.entitlements', 'pendingPlan.entitlements'])),
            __('subscriptions.resumed'),
        );
    }
}
