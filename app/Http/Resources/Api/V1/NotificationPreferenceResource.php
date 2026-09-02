<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{notification_type: string, channel: string, enabled: bool}
 */
class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'notification_type' => $this->resource['notification_type'],
            'channel' => $this->resource['channel'],
            'enabled' => $this->resource['enabled'],
        ];
    }
}
