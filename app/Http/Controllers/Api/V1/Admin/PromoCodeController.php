<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Promotions\CreatePromoCodeAction;
use App\Actions\Promotions\DeletePromoCodeAction;
use App\Actions\Promotions\UpdatePromoCodeAction;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Requests\Api\V1\Promo\StorePromoCodeRequest;
use App\Http\Requests\Api\V1\Promo\UpdatePromoCodeRequest;
use App\Http\Resources\Api\V1\PromoCodeResource;
use App\Models\Business;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoCodeController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::PromotionsView);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = PromoCode::query()
            ->with('business:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id')->toString());
        }

        if ($request->has('is_platform')) {
            $request->boolean('is_platform')
                ? $query->whereNull('business_id')
                : $query->whereNotNull('business_id');
        }

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

    public function show(PromoCode $promo): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::PromotionsView);

        $promo->load('business:id,name');

        return $this->success(new PromoCodeResource($promo));
    }

    public function store(
        StorePromoCodeRequest $request,
        CreatePromoCodeAction $createPromoCode,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::PromotionsManage);

        $business = null;
        if ($request->filled('business_id')) {
            $business = Business::query()->findOrFail($request->input('business_id'));
        }

        $promo = $createPromoCode->execute($business, $request->user(), $request->validated());

        return $this->created(new PromoCodeResource($promo), __('promotions.created'));
    }

    public function update(
        UpdatePromoCodeRequest $request,
        PromoCode $promo,
        UpdatePromoCodeAction $updatePromoCode,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::PromotionsManage);

        $updated = $updatePromoCode->execute($promo, $request->validated());

        return $this->success(new PromoCodeResource($updated), __('promotions.updated'));
    }

    public function destroy(PromoCode $promo, DeletePromoCodeAction $deletePromoCode): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::PromotionsManage);

        $deletePromoCode->execute($promo);

        return $this->success(null, __('promotions.deleted'));
    }
}
