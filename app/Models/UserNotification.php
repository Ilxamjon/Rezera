<?php

namespace App\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserNotification extends Model
{
    /** @use HasFactory<\Database\Factories\UserNotificationFactory> */
    use HasFactory, HasUuids;

    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'title',
        'body',
        'data',
        'status',
        'idempotency_key',
        'read_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
            'data' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'notification_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
