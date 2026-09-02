<?php

namespace App\Actions\Payments;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payments\PaymentService;

final class CreatePaymentAction
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function execute(User $customer, Reservation $reservation, PaymentProvider $provider): Payment
    {
        return $this->paymentService->createPayment($customer, $reservation, $provider);
    }
}
