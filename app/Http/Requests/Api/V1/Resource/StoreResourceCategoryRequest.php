<?php

namespace App\Http\Requests\Api\V1\Resource;

use App\Models\Business;
use App\Models\ResourceGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResourceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');

        return $business !== null && $this->user()?->can('create', [ResourceGroup::class, $business]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('resource_groups', 'name')
                    ->where(fn ($query) => $query->where('business_id', $business->id)->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
