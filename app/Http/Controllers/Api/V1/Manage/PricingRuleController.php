<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Platform\CreateAuditLogAction;
use App\Actions\Pricing\CreatePricingRuleAction;
use App\Actions\Pricing\DeletePricingRuleAction;
use App\Actions\Pricing\UpdatePricingRuleAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\PricingPreviewController;
use App\Http\Requests\Api\V1\Pricing\PricingPreviewRequest;
use App\Http\Requests\Api\V1\Pricing\StorePricingRuleRequest;
use App\Http\Requests\Api\V1\Pricing\UpdatePricingRuleRequest;
use App\Http\Resources\Api\V1\PricingPreviewResource;
use App\Http\Resources\Api\V1\PricingRuleResource;
use App\Models\Business;
use App\Models\PricingRule;
use App\Services\Reservations\ReservationQuoteService;
use App\Support\Reservations\ReservationIntervalResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PricingRuleController extends BaseApiController
{
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewAny', [PricingRule::class, $business]);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = PricingRule::query()
            ->where('business_id', $business->id)
            ->orderByDesc('priority')
            ->orderByDesc('created_at');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('resource_id')) {
            $query->where('resource_id', $request->string('resource_id')->toString());
        }

        if ($request->filled('resource_category_id')) {
            $query->where('resource_group_id', $request->string('resource_category_id')->toString());
        }

        return $this->paginatedResource($query->paginate($perPage), PricingRuleResource::class);
    }

    public function store(
        StorePricingRuleRequest $request,
        Business $business,
        CreatePricingRuleAction $createPricingRule,
        CreateAuditLogAction $audit,
    ): JsonResponse {
        $rule = $createPricingRule->execute($business, $request->user(), $request->validated());

        $audit->execute(
            action: 'pricing_rule.created',
            entityType: 'pricing_rule',
            entityId: $rule->id,
            actor: $request->user(),
            newValues: $rule->only(['name', 'pricing_type', 'price', 'priority', 'is_active']),
            request: $request,
        );

        return $this->created(new PricingRuleResource($rule), __('pricing.created'));
    }

    public function show(Business $business, PricingRule $rule): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $rule);
        Gate::authorize('view', [$rule, $business]);

        return $this->success(new PricingRuleResource($rule));
    }

    public function update(
        UpdatePricingRuleRequest $request,
        Business $business,
        PricingRule $rule,
        UpdatePricingRuleAction $updatePricingRule,
        CreateAuditLogAction $audit,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $rule);

        $oldValues = $rule->only(['name', 'pricing_type', 'price', 'priority', 'is_active']);
        $updated = $updatePricingRule->execute($rule, $request->validated());

        $audit->execute(
            action: 'pricing_rule.updated',
            entityType: 'pricing_rule',
            entityId: $updated->id,
            actor: $request->user(),
            oldValues: $oldValues,
            newValues: $updated->only(['name', 'pricing_type', 'price', 'priority', 'is_active']),
            request: $request,
        );

        return $this->success(new PricingRuleResource($updated), __('pricing.updated'));
    }

    public function destroy(
        Business $business,
        PricingRule $rule,
        DeletePricingRuleAction $deletePricingRule,
        Request $request,
        CreateAuditLogAction $audit,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $rule);
        Gate::authorize('delete', [$rule, $business]);

        $audit->execute(
            action: 'pricing_rule.deleted',
            entityType: 'pricing_rule',
            entityId: $rule->id,
            actor: $request->user(),
            oldValues: $rule->only(['name', 'pricing_type', 'price']),
            request: $request,
        );

        $deletePricingRule->execute($rule);

        return $this->success(null, __('pricing.deleted'));
    }

    public function preview(
        PricingPreviewRequest $request,
        Business $business,
        ReservationQuoteService $quoteService,
        ReservationIntervalResolver $intervalResolver,
    ): JsonResponse {
        return app(PricingPreviewController::class)->store($request, $business, $quoteService, $intervalResolver);
    }

    private function ensureBelongsToBusiness(Business $business, PricingRule $rule): void
    {
        if ($rule->business_id !== $business->id) {
            abort(404);
        }
    }
}
