<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Payments\CreatePaymentAction;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Payment\CreatePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends BaseApiController
{
    public function store(
        CreatePaymentRequest $request,
        Reservation $reservation,
        CreatePaymentAction $createPayment,
    ): JsonResponse {
        Gate::authorize('create', [Payment::class, $reservation]);

        $payment = $createPayment->execute(
            customer: $request->user(),
            reservation: $reservation,
            provider: PaymentProvider::from($request->validated('provider')),
        );

        return $this->created(
            new PaymentResource($payment->load(['reservation', 'business'])),
            __('payments.created'),
        );
    }

    public function show(Request $request, Payment $payment, PaymentService $paymentService): JsonResponse
    {
        Gate::authorize('view', $payment);

        if ($request->boolean('sync')) {
            $payment = $paymentService->synchronizeStatus($payment);
        }

        $payment->load(['reservation', 'business']);

        return $this->success(new PaymentResource($payment));
    }
}
