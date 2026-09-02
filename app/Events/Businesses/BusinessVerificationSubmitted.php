<?php

namespace App\Events\Businesses;

use App\Models\Business;
use App\Models\BusinessVerification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BusinessVerificationSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly BusinessVerification $verification) {}
}
