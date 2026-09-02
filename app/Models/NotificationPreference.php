<?php

namespace App\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationPreferenceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'channel' => NotificationChannel::class,
            'is_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
