<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Resources\Api\V1\PaymentManagementResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::PaymentsView);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = Payment::query()->with(['reservation', 'business', 'user']);

        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('payment_number', 'ilike', $term)
                    ->orWhereHas('reservation', fn ($r) => $r->where('reservation_number', 'ilike', $term));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->string('provider'));
        }

        $sort = $request->string('sort')->toString() === 'amount' ? 'amount' : 'created_at';
        $query->orderBy($sort, 'desc');

        return $this->paginatedResource($query->paginate($perPage), PaymentManagementResource::class);
    }

    public function show(Payment $payment): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::PaymentsView);
        $payment->load(['reservation.resource.group', 'business', 'user']);

        return $this->success(new PaymentManagementResource($payment));
    }
}
