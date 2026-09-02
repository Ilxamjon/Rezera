<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Http\Requests\Api\V1\Concerns\ValidatesBusinessFields;
use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessRequest extends FormRequest
{
    use ValidatesBusinessFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Business::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->businessFieldRules(partial: false);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('timezone')) {
            $this->merge([
                'timezone' => config('rezera.default_business_timezone', 'Asia/Tashkent'),
            ]);
        }

        if (! $this->has('country_code')) {
            $this->merge(['country_code' => 'UZ']);
        }
    }
}
