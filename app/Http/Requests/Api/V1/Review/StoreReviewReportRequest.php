<?php

namespace App\Http\Requests\Api\V1\Review;

use App\Domain\Reviews\Enums\ReviewReportReason;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Review $review */
        $review = $this->route('review');

        return $this->user()?->can('report', $review) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(ReviewReportReason::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ReviewReportReason
    {
        return ReviewReportReason::from($this->string('reason')->toString());
    }

    public function description(): ?string
    {
        if (! $this->filled('description')) {
            return null;
        }

        $description = trim($this->string('description')->toString());

        return $description === '' ? null : $description;
    }
}
