<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Referrals\ValidateReferralCodeRequest;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\JsonResponse;

class ReferralValidationController extends BaseApiController
{
    public function store(ValidateReferralCodeRequest $request, ReferralService $referralService): JsonResponse
    {
        $code = $referralService->findActiveCode($request->validated('code'));

        return $this->success([
            'valid' => $code !== null,
        ]);
    }
}
