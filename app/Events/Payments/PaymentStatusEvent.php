<?php

namespace App\Events\Payments;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class PaymentStatusEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly ?PaymentStatus $fromStatus = null,
    ) {}
}
