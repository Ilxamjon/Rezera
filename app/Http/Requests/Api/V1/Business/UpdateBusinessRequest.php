<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Http\Requests\Api\V1\Concerns\ValidatesBusinessFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessRequest extends FormRequest
{
    use ValidatesBusinessFields;

    public function authorize(): bool
    {
        $business = $this->route('business');

        return $business !== null && $this->user()?->can('update', $business);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->businessFieldRules(partial: true);
    }
}
