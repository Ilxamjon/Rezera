<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\Businesses\BusinessOnboardingService;
use App\Services\Businesses\BusinessVisibilityService;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\CompletesBusinessOnboarding;

class BusinessOnboardingAndVerificationTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use CompletesBusinessOnboarding;

    public function test_new_business_starts_onboarding_in_progress(): void
    {
        $business = Business::factory()->draft()->create([
            'verification_status' => BusinessVerificationStatus::Unverified,
            'onboarding_status' => OnboardingStatus::InProgress,
        ]);

        $this->assertSame(OnboardingStatus::InProgress, $business->onboarding_status);
    }

    public function test_owner_can_view_onboarding_progress(): void
    {
        $business = Business::factory()->draft()->create();
        $owner = $this->businessOwner($business);

        $this->getJson('/api/v1/manage/businesses/'.$business->id.'/onboarding', $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonStructure(['data' => ['percentage', 'steps', 'next_step']]);
    }

    public function test_onboarding_completion_calculates_percentage(): void
    {
        $business = Business::factory()->draft()->create();
        $completed = $this->completeBusinessOnboarding($business);

        $progress = app(BusinessOnboardingService::class)->progress($completed);

        $this->assertSame('completed', $progress['status']);
        $this->assertSame(100, $progress['percentage']);
        $this->assertEmpty($progress['remaining_steps']);
    }

    public function test_incomplete_business_cannot_submit_verification(): void
    {
        $business = Business::factory()->draft()->create([
            'verification_status' => BusinessVerificationStatus::Unverified,
        ]);
        $owner = $this->businessOwner($business);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/verification', [], $this->authHeaders($owner))
            ->assertStatus(422)
            ->assertJsonPath('code', 'ONBOARDING_INCOMPLETE');
    }

    public function test_owner_can_submit_verification_when_onboarding_complete(): void
    {
        $business = Business::factory()->draft()->create([
            'verification_status' => BusinessVerificationStatus::Unverified,
        ]);
        $owner = $this->businessOwner($business);
        $this->completeBusinessOnboarding($business);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/verification', [], $this->authHeaders($owner))
            ->assertCreated()
            ->assertJsonPath('data.status', VerificationRequestStatus::Pending->value);

        $this->assertDatabaseHas('business_verifications', [
            'business_id' => $business->id,
            'status' => VerificationRequestStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'verification_status' => BusinessVerificationStatus::Pending->value,
            'status' => BusinessStatus::PendingReview->value,
        ]);
    }

    public function test_staff_cannot_submit_verification(): void
    {
        $business = Business::factory()->draft()->create();
        $staff = User::factory()->create();
        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
            'status' => BusinessMemberStatus::Active,
        ]);
        $this->completeBusinessOnboarding($business);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/verification', [], $this->authHeaders($staff))
            ->assertForbidden();
    }

    public function test_admin_can_approve_verification(): void
    {
        $business = Business::factory()->draft()->create();
        $owner = $this->businessOwner($business);
        $this->completeBusinessOnboarding($business);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/verification', [], $this->authHeaders($owner));

        $verification = BusinessVerification::query()->where('business_id', $business->id)->firstOrFail();
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

        $this->postJson('/api/v1/admin/business-verifications/'.$verification->id.'/approve', [], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.status', VerificationRequestStatus::Approved->value);

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'verification_status' => BusinessVerificationStatus::Verified->value,
            'status' => BusinessStatus::Approved->value,
        ]);
    }

    public function test_admin_can_reject_verification_with_reason(): void
    {
        $business = Business::factory()->draft()->create();
        $owner = $this->businessOwner($business);
        $this->completeBusinessOnboarding($business);
        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/verification', [], $this->authHeaders($owner));

        $verification = BusinessVerification::query()->where('business_id', $business->id)->firstOrFail();
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

        $this->postJson('/api/v1/admin/business-verifications/'.$verification->id.'/reject', [
            'reason' => 'Required business information is incomplete.',
        ], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.status', VerificationRequestStatus::Rejected->value);

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'verification_status' => BusinessVerificationStatus::Rejected->value,
        ]);
    }

    public function test_rejected_business_can_resubmit_verification(): void
    {
        $business = Business::factory()->draft()->create([
            'verification_status' => BusinessVerificationStatus::Rejected,
        ]);
        $owner = $this->businessOwner($business);
        $this->completeBusinessOnboarding($business);

        BusinessVerification::factory()->rejected()->create([
            'business_id' => $business->id,
            'submitted_by_user_id' => $owner->id,
        ]);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/verification/resubmit', [], $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.status', VerificationRequestStatus::Pending->value);
    }

    public function test_unverified_business_not_publicly_discoverable(): void
    {
        config([
            'business_onboarding.public_requires_verification' => true,
            'business_onboarding.public_requires_onboarding_complete' => true,
        ]);

        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'verification_status' => BusinessVerificationStatus::Unverified,
            'onboarding_status' => OnboardingStatus::InProgress,
        ]);

        $this->assertFalse(app(BusinessVisibilityService::class)->isPubliclyDiscoverable($business));
    }

    public function test_verified_completed_business_is_publicly_discoverable(): void
    {
        $business = Business::factory()->create();

        $this->assertTrue(app(BusinessVisibilityService::class)->isPubliclyDiscoverable($business));
    }

    public function test_owner_can_access_management_for_draft_business(): void
    {
        $business = Business::factory()->draft()->create();
        $owner = $this->businessOwner($business);

        $this->getJson('/api/v1/manage/businesses/'.$business->id, $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.status', BusinessStatus::Draft->value);
    }

    public function test_readiness_endpoint_reports_missing_requirements(): void
    {
        $business = Business::factory()->draft()->create([
            'verification_status' => BusinessVerificationStatus::Unverified,
        ]);
        $owner = $this->businessOwner($business);

        $this->getJson('/api/v1/manage/businesses/'.$business->id.'/readiness', $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.ready_for_reservations', false)
            ->assertJsonPath('data.ready_for_verification', false);
    }

    public function test_support_cannot_approve_verification(): void
    {
        $verification = BusinessVerification::factory()->create();
        $support = User::factory()->create(['platform_role' => PlatformRole::Support]);

        $this->postJson('/api/v1/admin/business-verifications/'.$verification->id.'/approve', [], $this->authHeaders($support))
            ->assertForbidden();
    }
}
