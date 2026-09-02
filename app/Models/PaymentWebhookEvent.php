<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentWebhookEventFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'event_id',
        'payment_id',
        'event_type',
        'payload',
        'signature',
        'status',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => WebhookEventStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
