<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Promotions\CreatePromoCodeAction;
use App\Actions\Promotions\DeletePromoCodeAction;
use App\Actions\Promotions\UpdatePromoCodeAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Promo\StorePromoCodeRequest;
use App\Http\Requests\Api\V1\Promo\UpdatePromoCodeRequest;
use App\Http\Resources\Api\V1\PromoCodeResource;
use App\Models\Business;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PromoCodeController extends BaseApiController
{
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewAny', [PromoCode::class, $business]);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = PromoCode::query()
            ->where('business_id', $business->id)
            ->orderByDesc('created_at');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('code', 'ilike', $search)
                    ->orWhere('name', 'ilike', $search);
            });
        }

        return $this->paginatedResource($query->paginate($perPage), PromoCodeResource::class);
    }

    public function store(
        StorePromoCodeRequest $request,
        Business $business,
        CreatePromoCodeAction $createPromoCode,
    ): JsonResponse {
        $promo = $createPromoCode->execute($business, $request->user(), $request->validated());

        return $this->created(new PromoCodeResource($promo), __('promotions.created'));
    }

    public function show(Business $business, PromoCode $promo): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $promo);
        Gate::authorize('view', [$promo, $business]);

        return $this->success(new PromoCodeResource($promo));
    }

    public function update(
        UpdatePromoCodeRequest $request,
        Business $business,
        PromoCode $promo,
        UpdatePromoCodeAction $updatePromoCode,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $promo);

        $updated = $updatePromoCode->execute($promo, $request->validated());

        return $this->success(new PromoCodeResource($updated), __('promotions.updated'));
    }

    public function destroy(
        Business $business,
        PromoCode $promo,
        DeletePromoCodeAction $deletePromoCode,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $promo);
        Gate::authorize('delete', [$promo, $business]);

        $deletePromoCode->execute($promo);

        return $this->success(null, __('promotions.deleted'));
    }

    private function ensureBelongsToBusiness(Business $business, PromoCode $promo): void
    {
        if ($promo->business_id !== $business->id) {
            abort(404);
        }
    }
}
