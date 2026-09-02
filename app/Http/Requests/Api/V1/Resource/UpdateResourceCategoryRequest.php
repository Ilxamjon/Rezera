<?php

namespace App\Http\Requests\Api\V1\Resource;

use App\Models\Business;
use App\Models\ResourceGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResourceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');
        /** @var ResourceGroup|null $category */
        $category = $this->route('category');

        return $business !== null
            && $category !== null
            && $this->user()?->can('update', [$category, $business]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');
        /** @var ResourceGroup $category */
        $category = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('resource_groups', 'name')
                    ->where(fn ($query) => $query->where('business_id', $business->id)->whereNull('deleted_at'))
                    ->ignore($category->id),
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
