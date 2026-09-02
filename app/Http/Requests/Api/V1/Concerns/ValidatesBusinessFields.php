<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\Time\Timezone;
use Illuminate\Validation\Validator;

trait ValidatesBusinessFields
{
    /**
     * @return array<string, mixed>
     */
    protected function businessFieldRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'category_id' => [$required, 'uuid', 'exists:business_categories,id'],
            'name' => [$required, 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'translations' => ['nullable', 'array'],
            'translations.uz' => ['nullable', 'string', 'max:500'],
            'translations.kaa' => ['nullable', 'string', 'max:500'],
            'translations.ru' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:16'],
            'email' => ['nullable', 'email', 'max:255'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'cover_image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $timezone = $this->input('timezone', config('rezera.default_business_timezone'));

            if ($timezone !== null) {
                try {
                    Timezone::assertValid((string) $timezone);
                } catch (\InvalidArgumentException) {
                    $validator->errors()->add('timezone', __('validation.timezone'));
                }
            }

            $lat = $this->input('latitude');
            $lng = $this->input('longitude');

            if (($lat === null) xor ($lng === null)) {
                $validator->errors()->add('latitude', __('business.coordinates_pair_required'));
            }
        });
    }
}
