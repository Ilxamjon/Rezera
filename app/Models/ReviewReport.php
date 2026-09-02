<?php

namespace App\Models;

use App\Domain\Reviews\Enums\ReviewReportReason;
use App\Domain\Reviews\Enums\ReviewReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'review_id',
        'reporter_user_id',
        'reason',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReviewReportReason::class,
            'status' => ReviewReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }
}
