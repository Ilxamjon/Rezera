<?php

namespace App\Models;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMember extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessMemberFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'user_id',
        'member_role',
        'job_title',
        'invited_by_user_id',
        'joined_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'member_role' => BusinessMemberRole::class,
            'status' => BusinessMemberStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
