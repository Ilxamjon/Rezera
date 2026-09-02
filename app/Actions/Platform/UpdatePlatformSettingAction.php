<?php

namespace App\Actions\Platform;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class UpdatePlatformSettingAction
{
    private const SECRET_KEYS = [
        'api_key', 'secret', 'password', 'token', 'credential',
    ];

    public function __construct(
        private readonly CreateAuditLogAction $audit,
    ) {}

    public function execute(PlatformSetting $setting, string $value, User $actor, ?Request $request = null): PlatformSetting
    {
        foreach (self::SECRET_KEYS as $needle) {
            if (str_contains(strtolower($setting->key), $needle)) {
                throw ValidationException::withMessages([
                    'key' => [__('admin.setting_not_allowed')],
                ]);
            }
        }

        $old = ['value' => $setting->value];
        $setting->update(['value' => $value]);

        $this->audit->execute(
            action: 'platform_setting.updated',
            entityType: 'platform_setting',
            entityId: $setting->id,
            actor: $actor,
            oldValues: $old,
            newValues: ['value' => $value],
            request: $request,
        );

        return $setting->fresh();
    }
}
