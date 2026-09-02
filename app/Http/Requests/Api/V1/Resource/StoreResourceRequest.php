<?php

namespace App\Http\Requests\Api\V1\Resource;

use App\Domain\Resources\Enums\RateUnit;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Http\Requests\Api\V1\Concerns\MapsResourceInput;
use App\Models\Business;
use App\Models\Resource;
use App\Rules\ResourceCategoryBelongsToBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResourceRequest extends FormRequest
{
    use MapsResourceInput;

    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');

        return $business !== null && $this->user()?->can('create', [Resource::class, $business]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'resource_category_id' => [
                'nullable',
                'uuid',
                new ResourceCategoryBelongsToBusiness($business->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('resources', 'code')
                    ->where(fn ($query) => $query->where('business_id', $business->id)->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'url', 'max:2048'],
            'resource_type' => ['sometimes', Rule::enum(ResourceType::class)],
            'status' => ['sometimes', Rule::enum(ResourceStatus::class)],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'price' => ['required', 'integer', 'min:0'],
            'price_unit' => ['sometimes', Rule::enum(RateUnit::class)],
            'metadata' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('status', ResourceStatus::Active->value);

            if ($status === ResourceStatus::Active->value && $this->input('price') === null) {
                $validator->errors()->add('price', __('resources.active_requires_price'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        return $this->mapResourceInput(parent::validated());
    }
}
