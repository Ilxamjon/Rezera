<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Domain\Subscriptions\Enums\EntitlementValueType;
use App\Http\Resources\Api\V1\Admin\AdminBusinessSubscriptionResource;
use App\Http\Resources\Api\V1\Admin\AdminSubscriptionPlanResource;
use App\Models\BusinessSubscription;
use App\Models\PlanEntitlement;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\SubscriptionPlanResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsView);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = SubscriptionPlan::query()->orderBy('sort_order');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        return $this->paginatedResource(
            $query->with('entitlements')->paginate($perPage),
            AdminSubscriptionPlanResource::class,
        );
    }

    public function store(Request $request, CreateAuditLogAction $audit): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsManage);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:subscription_plans,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'translations' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'monthly_price' => ['required', 'integer', 'min:0'],
            'yearly_price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $plan = SubscriptionPlan::query()->create($validated);

        $audit->execute(
            action: 'subscription_plan.created',
            entityType: 'subscription_plan',
            entityId: $plan->id,
            actor: $request->user(),
            newValues: $plan->only(['code', 'name', 'monthly_price', 'yearly_price']),
            request: $request,
        );

        return $this->created(new AdminSubscriptionPlanResource($plan->load('entitlements')));
    }

    public function show(SubscriptionPlan $plan): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsView);

        return $this->success(new AdminSubscriptionPlanResource($plan->load('entitlements')));
    }

    public function update(Request $request, SubscriptionPlan $plan, CreateAuditLogAction $audit, SubscriptionPlanResolver $resolver): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsManage);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'translations' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'monthly_price' => ['sometimes', 'integer', 'min:0'],
            'yearly_price' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $oldValues = $plan->only(array_keys($validated));
        $plan->update($validated);
        $resolver->forgetPlanCache($plan);

        $audit->execute(
            action: 'subscription_plan.updated',
            entityType: 'subscription_plan',
            entityId: $plan->id,
            actor: $request->user(),
            oldValues: $oldValues,
            newValues: $plan->only(array_keys($validated)),
            request: $request,
        );

        return $this->success(new AdminSubscriptionPlanResource($plan->fresh('entitlements')));
    }

    public function entitlements(SubscriptionPlan $plan): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsView);

        return $this->success($plan->entitlements()->orderBy('feature_code')->get()->map(fn (PlanEntitlement $entitlement) => [
            'feature_code' => $entitlement->feature_code,
            'value_type' => $entitlement->value_type?->value,
            'value' => $entitlement->parsedValue(),
        ]));
    }

    public function syncEntitlements(Request $request, SubscriptionPlan $plan, CreateAuditLogAction $audit, SubscriptionPlanResolver $resolver): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsManage);

        $validated = $request->validate([
            'entitlements' => ['required', 'array'],
            'entitlements.*.feature_code' => ['required', 'string', 'max:64'],
            'entitlements.*.value_type' => ['required', 'string', Rule::in(array_column(EntitlementValueType::cases(), 'value'))],
            'entitlements.*.value' => ['required'],
        ]);

        foreach ($validated['entitlements'] as $entitlement) {
            PlanEntitlement::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'feature_code' => $entitlement['feature_code'],
                ],
                [
                    'value_type' => $entitlement['value_type'],
                    'value' => is_bool($entitlement['value'])
                        ? ($entitlement['value'] ? 'true' : 'false')
                        : (string) $entitlement['value'],
                ],
            );
        }

        $resolver->forgetPlanCache($plan);

        $audit->execute(
            action: 'subscription_plan.entitlements_updated',
            entityType: 'subscription_plan',
            entityId: $plan->id,
            actor: $request->user(),
            newValues: ['entitlements' => $validated['entitlements']],
            request: $request,
        );

        return $this->success($plan->fresh('entitlements')->entitlements->map(fn (PlanEntitlement $entitlement) => [
            'feature_code' => $entitlement->feature_code,
            'value_type' => $entitlement->value_type?->value,
            'value' => $entitlement->parsedValue(),
        ]));
    }
}
