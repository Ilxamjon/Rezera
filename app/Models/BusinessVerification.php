<?php

namespace App\Models;

use App\Domain\Businesses\Enums\VerificationRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessVerification extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessVerificationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'status',
        'submitted_by_user_id',
        'submitted_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'admin_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => VerificationRequestStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === VerificationRequestStatus::Pending;
    }
}
