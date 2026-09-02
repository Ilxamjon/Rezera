<?php

namespace App\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationDeliveryFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'notification_id',
        'channel',
        'provider',
        'status',
        'provider_message_id',
        'sent_at',
        'failed_at',
        'error_message',
        'metadata',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationDeliveryStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'notification_id');
    }
}
