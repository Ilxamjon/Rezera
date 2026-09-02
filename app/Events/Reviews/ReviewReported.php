<?php

namespace App\Events\Reviews;

use App\Models\ReviewReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReviewReported
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ReviewReport $report,
    ) {}
}
