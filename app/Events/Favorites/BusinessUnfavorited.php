<?php

namespace App\Events\Favorites;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BusinessUnfavorited
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Business $business,
    ) {}
}
