<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array{code: string, total_referred: int, qualified: int, rewarded: int} $data */
        $data = $this->resource;

        return [
            'code' => $data['code'],
            'total_referred' => $data['total_referred'],
            'qualified' => $data['qualified'],
            'rewarded' => $data['rewarded'],
        ];
    }
}
