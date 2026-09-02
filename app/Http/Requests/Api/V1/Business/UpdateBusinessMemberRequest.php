<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');
        /** @var BusinessMember|null $member */
        $member = $this->route('member');
        $roleValue = $this->input('member_role', $member?->member_role?->value ?? '');
        $role = BusinessMemberRole::tryFrom((string) $roleValue);

        if ($business === null || $member === null || $role === null) {
            return false;
        }

        return $this->user()?->can('updateMember', [$business, $member, $role]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_role' => ['sometimes', 'required', Rule::enum(BusinessMemberRole::class)],
            'job_title' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if (! $this->filled('member_role') && ! $this->exists('job_title')) {
                $validator->errors()->add('member_role', __('validation.required'));
            }
        });
    }
}
