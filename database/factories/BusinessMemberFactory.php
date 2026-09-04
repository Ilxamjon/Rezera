<?php

namespace Database\Factories;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<BusinessMember>
 */
class BusinessMemberFactory extends Factory
{
    protected $model = BusinessMember::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'member_role' => BusinessMemberRole::Owner,
            'status' => BusinessMemberStatus::Active,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null): BusinessMember
    {
        if ($this->count !== null) {
            return parent::create($attributes, $parent);
        }

        if (! array_key_exists('business_id', $attributes) || ! array_key_exists('user_id', $attributes)) {
            return parent::create($attributes, $parent);
        }

        /** @var BusinessMember $member */
        $member = $this->make($attributes, $parent);

        return BusinessMember::query()->updateOrCreate(
            [
                'business_id' => $member->business_id,
                'user_id' => $member->user_id,
            ],
            [
                'member_role' => $member->member_role,
                'status' => $member->status,
                'job_title' => $member->job_title,
            ],
        );
    }
}
