<?php

namespace App\Events\Businesses;

use App\Models\Business;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BusinessOnboardingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Business $business) {}
}
