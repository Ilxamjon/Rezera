<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearchAlert extends Model
{
    /** @use HasFactory<\Database\Factories\SavedSearchAlertFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'saved_search_id',
        'user_id',
        'business_id',
        'resource_id',
        'start_at',
        'end_at',
        'match_hash',
        'notification_id',
        'status',
        'notified_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'notified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'notification_id');
    }
}
