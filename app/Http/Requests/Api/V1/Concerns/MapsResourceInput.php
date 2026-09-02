<?php

namespace App\Http\Requests\Api\V1\Concerns;

trait MapsResourceInput
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mapResourceInput(array $validated): array
    {
        if (array_key_exists('resource_category_id', $validated)) {
            $validated['resource_group_id'] = $validated['resource_category_id'];
            unset($validated['resource_category_id']);
        }

        if (array_key_exists('price', $validated)) {
            $validated['hourly_rate_amount'] = $validated['price'];
            unset($validated['price']);
        }

        if (array_key_exists('price_unit', $validated)) {
            $validated['rate_unit'] = $validated['price_unit'];
            unset($validated['price_unit']);
        }

        if (array_key_exists('image', $validated)) {
            $validated['image_url'] = $validated['image'];
            unset($validated['image']);
        }

        return $validated;
    }
}
