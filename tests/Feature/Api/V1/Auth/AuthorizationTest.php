<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use Illuminate\Support\Facades\Gate;
use Tests\PostgresTestCase;

class AuthorizationTest extends PostgresTestCase
{
    public function test_regular_user_cannot_pass_platform_admin_gate(): void
    {
        $user = User::factory()->create([
            'platform_role' => PlatformRole::User,
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('platform-admin'));
    }

    public function test_platform_admin_passes_platform_admin_gate(): void
    {
        $user = User::factory()->create([
            'platform_role' => PlatformRole::PlatformAdmin,
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('platform-admin'));
    }

    public function test_business_membership_grants_access_only_to_target_business(): void
    {
        $user = User::factory()->create();
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $businessA->id,
            'user_id' => $user->id,
            'member_role' => BusinessMemberRole::Owner,
            'status' => BusinessMemberStatus::Active,
        ]);

        $service = app(BusinessAuthorizationService::class);

        $this->assertTrue($service->canManageBusiness($user, $businessA->id));
        $this->assertFalse($service->canManageBusiness($user, $businessB->id));
        $this->assertTrue($user->canAccessBusiness($businessA->id, BusinessMemberRole::Owner));
        $this->assertFalse($user->canAccessBusiness($businessB->id, BusinessMemberRole::Owner));
    }

    public function test_business_policy_denies_non_member(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $this->assertFalse($user->can('manage', $business));
        $this->assertFalse($user->can('manageBookings', $business));
    }

    public function test_staff_can_manage_bookings_but_not_business_settings_via_policy(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'member_role' => BusinessMemberRole::Staff,
            'status' => BusinessMemberStatus::Active,
        ]);

        $this->assertTrue($user->can('manageBookings', $business));
        $this->assertFalse($user->can('manage', $business));
    }
}
