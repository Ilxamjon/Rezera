<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Http\Resources\Api\V1\BusinessCategoryResource;
use App\Models\Business;
use App\Models\BusinessCategory;
use Illuminate\Http\JsonResponse;

class BusinessCategoryController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $categories = BusinessCategory::query()
            ->where('is_active', true)
            ->withCount([
                'businesses as business_count' => fn ($query) => $query
                    ->where('status', BusinessStatus::Approved)
                    ->where('is_publicly_listed', true)
                    ->whereNull('deleted_at'),
            ])
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        return $this->success(BusinessCategoryResource::collection($categories));
    }
}
