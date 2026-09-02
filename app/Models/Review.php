<?php

namespace App\Models;

use App\Domain\Reviews\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'user_id',
        'reservation_id',
        'resource_id',
        'rating',
        'title',
        'body',
        'status',
        'business_response',
        'business_responded_by',
        'business_responded_at',
        'edited_at',
        'published_at',
        'hidden_at',
        'hidden_by',
        'hidden_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'business_responded_at' => 'datetime',
            'edited_at' => 'datetime',
            'published_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ReviewStatus::Published)
            ->whereNull('deleted_at');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    public function businessResponder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_responded_by');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
