<?php

namespace App\Actions\Reviews;

use App\Domain\Reviews\Enums\ReviewReportReason;
use App\Domain\Reviews\Enums\ReviewReportStatus;
use App\Events\Reviews\ReviewReported;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class ReportReviewAction
{
    public function execute(User $reporter, Review $review, ReviewReportReason $reason, ?string $description): ReviewReport
    {
        if ($review->trashed()) {
            throw ValidationException::withMessages([
                'review' => [__('reviews.cannot_report_deleted')],
            ]);
        }

        try {
            $report = ReviewReport::query()->create([
                'review_id' => $review->id,
                'reporter_user_id' => $reporter->id,
                'reason' => $reason,
                'description' => $description,
                'status' => ReviewReportStatus::Open,
            ]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages([
                    'review' => [__('reviews.already_reported')],
                ]);
            }

            throw $exception;
        }

        ReviewReported::dispatch($report->fresh(['review']));

        return $report;
    }
}
