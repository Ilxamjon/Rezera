<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessRatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $average = $this->resource['average'] ?? $this->rating_average ?? null;
        $count = (int) ($this->resource['count'] ?? $this->rating_count ?? 0);
        $distribution = $this->resource['distribution'] ?? $this->rating_distribution ?? null;

        $payload = [
            'average' => $average !== null ? (float) $average : null,
            'count' => $count,
        ];

        if ($distribution !== null) {
            $payload['distribution'] = [
                '5' => (int) ($distribution[5] ?? $distribution['5'] ?? 0),
                '4' => (int) ($distribution[4] ?? $distribution['4'] ?? 0),
                '3' => (int) ($distribution[3] ?? $distribution['3'] ?? 0),
                '2' => (int) ($distribution[2] ?? $distribution['2'] ?? 0),
                '1' => (int) ($distribution[1] ?? $distribution['1'] ?? 0),
            ];
        }

        return $payload;
    }
}
