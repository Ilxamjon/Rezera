<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'slug' => ['required', 'string', 'max:64', Rule::unique('business_categories', 'slug')->ignore($categoryId)],
            'name' => ['required', 'array'],
            'name.uz' => ['nullable', 'string', 'max:120'],
            'name.kaa' => ['nullable', 'string', 'max:120'],
            'name.ru' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'array'],
            'icon' => ['nullable', 'string', 'max:64'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
