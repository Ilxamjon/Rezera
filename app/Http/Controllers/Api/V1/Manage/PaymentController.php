<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Payment\ListBusinessPaymentsRequest;
use App\Http\Resources\Api\V1\PaymentManagementResource;
use App\Models\Business;
use App\Models\Payment;
use App\Services\Payments\PaymentListFilters;
use App\Services\Payments\PaymentQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentController extends BaseApiController
{
    public function index(
        ListBusinessPaymentsRequest $request,
        Business $business,
        PaymentQueryBuilder $queryBuilder,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Payment::class, $business]);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        return $this->paginatedResource(
            $queryBuilder->forBusiness(
                $business,
                PaymentListFilters::fromRequest($request, $business->timezone),
            )->paginate($perPage),
            PaymentManagementResource::class,
        );
    }

    public function show(Business $business, Payment $payment): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $payment);
        Gate::authorize('view', $payment);

        $payment->load(['reservation.resource.group', 'user']);

        return $this->success(new PaymentManagementResource($payment));
    }

    private function ensureBelongsToBusiness(Business $business, Payment $payment): void
    {
        if ($payment->business_id !== $business->id) {
            abort(404);
        }
    }
}
