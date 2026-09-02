<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class AdminDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
