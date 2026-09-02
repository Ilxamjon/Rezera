<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = PlatformSetting::query()
            ->where('is_public', true)
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (PlatformSetting $setting) => [$setting->key => $this->castValue($setting)])
            ->all();

        return ApiResponse::success(['settings' => $settings]);
    }

    private function castValue(PlatformSetting $setting): mixed
    {
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode((string) $setting->value, true),
            default => $setting->value,
        };
    }
}
