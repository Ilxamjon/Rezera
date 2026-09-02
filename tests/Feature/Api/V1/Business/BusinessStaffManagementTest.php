<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\BusinessMemberInvitation;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class BusinessStaffManagementTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_owner_can_view_role_definitions(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/staff/roles',
            $this->authHeaders($owner),
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'roles' => [['role', 'label', 'permissions']],
                    'suggested_job_titles',
                    'permissions',
                ],
            ]);
    }

    public function test_staff_capabilities_reflect_role(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
            'job_title' => 'Reception Staff',
        ]);

        $response = $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/staff/me',
            $this->authHeaders($staff),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.member_role', BusinessMemberRole::Staff->value)
            ->assertJsonPath('data.job_title', 'Reception Staff')
            ->assertJsonPath('data.permissions.manage_business', false)
            ->assertJsonPath('data.permissions.operate_bookings', true);
    }

    public function test_owner_can_invite_staff_by_phone(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $invitee = User::factory()->create(['phone' => '+998903334455']);

        $response = $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/invitations',
            [
                'phone' => '90 333 44 55',
                'member_role' => BusinessMemberRole::Staff->value,
                'job_title' => 'Operator',
            ],
            $this->authHeaders($owner),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', BusinessMemberInvitationStatus::Pending->value)
            ->assertJsonPath('data.job_title', 'Operator');

        $this->assertDatabaseHas('business_member_invitations', [
            'business_id' => $business->id,
            'phone' => '+998903334455',
            'member_role' => BusinessMemberRole::Staff->value,
        ]);
    }

    public function test_user_can_accept_pending_invitation(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $invitee = User::factory()->create(['phone' => '+998905556677']);

        $invitation = BusinessMemberInvitation::factory()->create([
            'business_id' => $business->id,
            'phone' => $invitee->phone,
            'invited_user_id' => $invitee->id,
            'invited_by_user_id' => $owner->id,
            'member_role' => BusinessMemberRole::Staff,
            'job_title' => 'Reception Staff',
        ]);

        $response = $this->postJson(
            '/api/v1/me/business-invitations/'.$invitation->id.'/accept',
            [],
            $this->authHeaders($invitee),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.member_role', BusinessMemberRole::Staff->value)
            ->assertJsonPath('data.job_title', 'Reception Staff');

        $this->assertDatabaseHas('business_members', [
            'business_id' => $business->id,
            'user_id' => $invitee->id,
            'status' => BusinessMemberStatus::Active->value,
        ]);
    }

    public function test_user_can_decline_invitation(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $invitee = User::factory()->create();

        $invitation = BusinessMemberInvitation::factory()->create([
            'business_id' => $business->id,
            'phone' => $invitee->phone,
            'invited_user_id' => $invitee->id,
            'invited_by_user_id' => $owner->id,
        ]);

        $this->postJson(
            '/api/v1/me/business-invitations/'.$invitation->id.'/decline',
            [],
            $this->authHeaders($invitee),
        )
            ->assertOk()
            ->assertJsonPath('data.status', BusinessMemberInvitationStatus::Declined->value);
    }

    public function test_direct_member_add_records_job_title(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $staffUser = User::factory()->create(['phone' => '+998907778899']);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/members',
            [
                'phone' => '90 777 88 99',
                'member_role' => BusinessMemberRole::Staff->value,
                'job_title' => 'Administrator',
            ],
            $this->authHeaders($owner),
        )->assertCreated()->assertJsonPath('data.job_title', 'Administrator');
    }
}
