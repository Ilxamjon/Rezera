<?php

namespace App\Models;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMemberInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessMemberInvitationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'phone',
        'invited_user_id',
        'invited_by_user_id',
        'member_role',
        'job_title',
        'status',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'member_role' => BusinessMemberRole::class,
            'status' => BusinessMemberInvitationStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === BusinessMemberInvitationStatus::Pending
            && $this->expires_at->isFuture();
    }
}
