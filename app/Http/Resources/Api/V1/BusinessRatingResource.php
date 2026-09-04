<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessRatingResource extends JsonResource
{
    /**
     * Preserve string keys like "5" / "1" on rating distribution maps.
     * Without this, JsonResource::filter() reindexes numeric keys via array_values().
     */
    public bool $preserveKeys = true;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : null;

        $average = $data['average'] ?? $this->rating_average ?? null;
        $count = (int) ($data['count'] ?? $this->rating_count ?? 0);
        $distribution = $data['distribution'] ?? $this->rating_distribution ?? null;

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
