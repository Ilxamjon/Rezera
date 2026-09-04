<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewSummaryResource extends JsonResource
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
        $data = is_array($this->resource) ? $this->resource : [];

        $distribution = $data['distribution'] ?? null;

        return [
            'average' => isset($data['average']) && $data['average'] !== null ? (float) $data['average'] : null,
            'count' => (int) ($data['count'] ?? 0),
            'distribution' => $distribution !== null ? [
                '1' => (int) ($distribution[1] ?? $distribution['1'] ?? 0),
                '2' => (int) ($distribution[2] ?? $distribution['2'] ?? 0),
                '3' => (int) ($distribution[3] ?? $distribution['3'] ?? 0),
                '4' => (int) ($distribution[4] ?? $distribution['4'] ?? 0),
                '5' => (int) ($distribution[5] ?? $distribution['5'] ?? 0),
            ] : null,
        ];
    }
}
