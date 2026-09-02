<?php

namespace App\Models;

use App\Domain\Subscriptions\Enums\EntitlementValueType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEntitlement extends Model
{
    /** @use HasFactory<\Database\Factories\PlanEntitlementFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'plan_id',
        'feature_code',
        'value_type',
        'value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value_type' => EntitlementValueType::class,
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function parsedValue(): bool|int|string|float|array|null
    {
        return match ($this->value_type) {
            EntitlementValueType::Boolean => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            EntitlementValueType::Integer => $this->value === '' ? null : (int) $this->value,
            EntitlementValueType::Decimal => $this->value === '' ? null : (float) $this->value,
            EntitlementValueType::Json => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
