<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NotificationDelivery */
class NotificationDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'channel' => $this->channel?->value,
            'provider' => $this->provider,
            'status' => $this->status?->value,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'error_message' => $this->when($this->status?->value === 'failed', $this->error_message),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
