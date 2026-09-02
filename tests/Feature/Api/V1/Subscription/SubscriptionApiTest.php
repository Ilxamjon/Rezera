<?php

namespace Tests\Feature\Api\V1\Subscription;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\BusinessSubscription;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\BusinessCategory;
use App\Services\Subscriptions\BusinessEntitlementService;
use Illuminate\Support\Facades\Config;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class SubscriptionApiTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_public_subscription_plans_endpoint_lists_active_public_plans(): void
    {
        $response = $this->getJson('/api/v1/subscription-plans');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertContains('free', $codes);
        $this->assertContains('pro', $codes);
        $this->assertNotContains('enterprise', $codes);
    }

    public function test_business_owner_can_view_subscription_summary(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $this->getJson('/api/v1/manage/businesses/'.$business->id.'/subscription', $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.usage.resources.limit', 5);
    }

    public function test_staff_cannot_manage_subscription(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
            'status' => BusinessMemberStatus::Active,
        ]);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/subscription', [
            'plan_code' => 'pro',
            'billing_interval' => 'monthly',
        ], $this->authHeaders($staff))->assertForbidden();
    }

    public function test_subscribe_to_free_plan_activates_subscription(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        BusinessSubscription::query()->where('business_id', $business->id)->delete();

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/subscription', [
            'plan_code' => 'free',
            'billing_interval' => 'monthly',
        ], $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.requires_payment', false)
            ->assertJsonPath('data.subscription.status', SubscriptionStatus::Active->value);

        $this->assertDatabaseHas('business_subscriptions', [
            'business_id' => $business->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_paid_upgrade_requires_payment_foundation(): void
    {
        Config::set('subscriptions.trial_enabled', false);

        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->postJson('/api/v1/manage/businesses/'.$business->id.'/subscription', [
            'plan_code' => 'pro',
            'billing_interval' => 'monthly',
        ], $this->authHeaders($owner));

        $response->assertOk()
            ->assertJsonPath('data.requires_payment', true)
            ->assertJsonPath('data.payment.amount', 990000);

        $this->assertDatabaseHas('payments', [
            'business_id' => $business->id,
            'amount' => 990000,
            'status' => 'pending',
        ]);
    }

    public function test_resource_creation_blocked_when_plan_limit_reached(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $category = ResourceGroup::factory()->create(['business_id' => $business->id]);

        Resource::factory()->count(5)->create([
            'business_id' => $business->id,
            'resource_group_id' => $category->id,
        ]);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/resources', [
            'resource_category_id' => $category->id,
            'name' => 'PC #06',
            'code' => 'PC-006',
            'resource_type' => ResourceType::Pc->value,
            'price' => 15000,
            'price_unit' => 'hour',
        ], $this->authHeaders($owner))
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_LIMIT_REACHED')
            ->assertJsonPath('feature', SubscriptionFeature::RESOURCES_MAX);
    }

    public function test_advanced_analytics_requires_pro_plan(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $this->getJson('/api/v1/manage/businesses/'.$business->id.'/analytics/revenue', $this->authHeaders($owner))
            ->assertForbidden()
            ->assertJsonPath('code', 'PLAN_FEATURE_RESTRICTED')
            ->assertJsonPath('feature', SubscriptionFeature::ANALYTICS_ADVANCED);
    }

    public function test_cancel_and_resume_subscription(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/subscription/cancel', [
            'at_period_end' => true,
            'reason' => 'too_expensive',
        ], $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.cancellation_reason', 'too_expensive');

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/subscription/resume', [], $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.renewal.scheduled_cancellation', false);
    }

    public function test_admin_can_list_subscription_plans(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

        $this->getJson('/api/v1/admin/subscription-plans', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_support_can_view_but_not_manage_subscription_plans(): void
    {
        $support = User::factory()->create(['platform_role' => PlatformRole::Support]);

        $this->getJson('/api/v1/admin/subscription-plans', $this->authHeaders($support))->assertOk();
        $this->postJson('/api/v1/admin/subscription-plans', [
            'code' => 'custom',
            'name' => 'Custom',
            'monthly_price' => 1000,
            'yearly_price' => 10000,
            'currency' => 'UZS',
        ], $this->authHeaders($support))->assertForbidden();
    }

    public function test_entitlement_service_uses_default_free_plan_without_subscription(): void
    {
        $business = Business::factory()->create();
        BusinessSubscription::query()->where('business_id', $business->id)->delete();

        $entitlements = app(BusinessEntitlementService::class);

        $this->assertSame('free', $entitlements->effectivePlan($business)->code);
        $this->assertFalse($entitlements->has($business, SubscriptionFeature::ANALYTICS_ADVANCED));
        $this->assertSame(5, $entitlements->limit($business, SubscriptionFeature::RESOURCES_MAX));
    }

    public function test_duplicate_active_subscriptions_are_prevented(): void
    {
        $business = Business::factory()->create();
        $plan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();

        $this->expectException(\Illuminate\Database\QueryException::class);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_new_business_receives_default_subscription(): void
    {
        $owner = User::factory()->create();

        $response = $this->postJson('/api/v1/businesses', [
            'category_id' => BusinessCategory::factory()->create()->id,
            'name' => 'New Club',
            'city' => 'Tashkent',
            'address_line' => 'Amir Temur 1',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
        ], $this->authHeaders($owner));

        $businessId = $response->json('data.id');

        $this->assertDatabaseHas('business_subscriptions', [
            'business_id' => $businessId,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }
}
