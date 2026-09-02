<?php

namespace App\Http\Requests\Api\V1\Resource;

use App\Domain\Resources\Enums\RateUnit;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Http\Requests\Api\V1\Concerns\MapsResourceInput;
use App\Models\Business;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Rules\ResourceCategoryBelongsToBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateResourceRequest extends FormRequest
{
    use MapsResourceInput;

    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');
        /** @var Resource|null $resource */
        $resource = $this->route('resource');

        return $business !== null
            && $resource !== null
            && $this->user()?->can('update', [$resource, $business]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');
        /** @var Resource $resource */
        $resource = $this->route('resource');

        return [
            'resource_category_id' => [
                'nullable',
                'uuid',
                new ResourceCategoryBelongsToBusiness($business->id),
            ],
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('resources', 'code')
                    ->where(fn ($query) => $query->where('business_id', $business->id)->whereNull('deleted_at'))
                    ->ignore($resource->id),
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'url', 'max:2048'],
            'resource_type' => ['sometimes', Rule::enum(ResourceType::class)],
            'status' => ['sometimes', Rule::enum(ResourceStatus::class)],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'price_unit' => ['sometimes', Rule::enum(RateUnit::class)],
            'metadata' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Resource $resource */
            $resource = $this->route('resource');
            $status = $this->input('status', $resource->status?->value);
            $price = $this->input('price', $resource->hourly_rate_amount);

            if ($status === ResourceStatus::Active->value && $price === null) {
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
