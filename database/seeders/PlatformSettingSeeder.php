<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'default_currency', 'value' => 'UZS', 'type' => 'string', 'description' => 'Default currency', 'is_public' => true],
            ['key' => 'default_locale', 'value' => 'ru', 'type' => 'string', 'description' => 'Default locale', 'is_public' => true],
            ['key' => 'support_phone', 'value' => '+998901234567', 'type' => 'string', 'description' => 'Support phone', 'is_public' => true],
            ['key' => 'support_email', 'value' => 'support@rezera.uz', 'type' => 'string', 'description' => 'Support email', 'is_public' => true],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'description' => 'Maintenance mode', 'is_public' => true],
        ];

        foreach ($defaults as $setting) {
            PlatformSetting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
