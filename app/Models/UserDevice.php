<?php

namespace App\Models;

use App\Domain\Notifications\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    /** @use HasFactory<\Database\Factories\UserDeviceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'device_id',
        'platform',
        'push_token',
        'app_version',
        'locale',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
