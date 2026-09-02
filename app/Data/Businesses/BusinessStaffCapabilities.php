<?php

namespace App\Data\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberRole;

final readonly class BusinessStaffCapabilities
{
    /**
     * @param  array<string, bool>  $permissions
     */
    public function __construct(
        public string $businessId,
        public BusinessMemberRole $role,
        public ?string $jobTitle,
        public array $permissions,
        public bool $isPlatformAdmin,
    ) {}

    public function can(string $permission): bool
    {
        if ($this->isPlatformAdmin) {
            return true;
        }

        return $this->permissions[$permission] ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'business_id' => $this->businessId,
            'member_role' => $this->role->value,
            'job_title' => $this->jobTitle,
            'permissions' => $this->permissions,
            'is_platform_admin' => $this->isPlatformAdmin,
        ];
    }
}
