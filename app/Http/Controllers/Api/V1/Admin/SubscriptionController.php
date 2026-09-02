<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Resources\Api\V1\Admin\AdminBusinessSubscriptionResource;
use App\Models\BusinessSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsView);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = BusinessSubscription::query()
            ->with(['plan', 'business:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id')->toString());
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->string('plan_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('billing_interval')) {
            $query->where('billing_interval', $request->string('billing_interval')->toString());
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->string('provider')->toString());
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        return $this->paginatedResource($query->paginate($perPage), AdminBusinessSubscriptionResource::class);
    }

    public function show(BusinessSubscription $subscription): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SubscriptionsView);

        return $this->success(new AdminBusinessSubscriptionResource(
            $subscription->load(['plan.entitlements', 'business:id,name']),
        ));
    }
}
