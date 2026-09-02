<?php

namespace App\Actions\Pricing;

use App\Domain\Pricing\Enums\PricingType;
use App\Models\PricingRule;

final class UpdatePricingRuleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PricingRule $rule, array $data): PricingRule
    {
        $attributes = [];

        foreach (['name', 'description', 'start_time', 'end_time', 'specific_date', 'starts_at', 'ends_at', 'metadata', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('pricing_type', $data)) {
            $attributes['pricing_type'] = PricingType::from($data['pricing_type']);
        }

        if (array_key_exists('price', $data)) {
            $attributes['price'] = (int) $data['price'];
        }

        if (array_key_exists('currency', $data)) {
            $attributes['currency'] = $data['currency'];
        }

        if (array_key_exists('day_of_week', $data)) {
            $attributes['day_of_week'] = $data['day_of_week'] !== null ? (int) $data['day_of_week'] : null;
        }

        if (array_key_exists('priority', $data)) {
            $attributes['priority'] = (int) $data['priority'];
        }

        if (array_key_exists('resource_id', $data)) {
            $attributes['resource_id'] = $data['resource_id'];
            $attributes['resource_group_id'] = null;
        }

        if (array_key_exists('resource_category_id', $data) || array_key_exists('resource_group_id', $data)) {
            $attributes['resource_group_id'] = $data['resource_category_id'] ?? $data['resource_group_id'] ?? null;
            $attributes['resource_id'] = null;
        }

        $rule->update($attributes);

        return $rule->fresh();
    }
}
