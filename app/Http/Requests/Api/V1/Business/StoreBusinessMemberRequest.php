<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneInput;
use App\Models\Business;
use App\Rules\ValidUzbekPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessMemberRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');
        $role = BusinessMemberRole::tryFrom((string) $this->input('member_role', ''));

        if ($business === null || $role === null) {
            return false;
        }

        return $this->user()?->can('addMember', [$business, $role]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:16', new ValidUzbekPhone, 'exists:users,phone'],
            'member_role' => ['required', Rule::enum(BusinessMemberRole::class)],
            'job_title' => ['nullable', 'string', 'max:120'],
        ];
    }
}
