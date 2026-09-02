<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\PlatformSettingSeeder;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class AdminApiTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSettingSeeder::class);
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['platform_role' => PlatformRole::User]);

        $this->getJson('/api/v1/admin/dashboard', $this->authHeaders($user))
            ->assertForbidden();
    }

    public function test_business_owner_cannot_access_admin_dashboard(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $this->getJson('/api/v1/admin/dashboard', $this->authHeaders($owner))
            ->assertForbidden();
    }

    public function test_platform_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::PlatformAdmin]);

        $this->getJson('/api/v1/admin/dashboard', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.users.total', fn ($v) => $v >= 1);
    }

    public function test_support_cannot_suspend_users(): void
    {
        $support = User::factory()->create(['platform_role' => PlatformRole::Support]);
        $target = User::factory()->create();

        $this->patchJson(
            '/api/v1/admin/users/'.$target->id.'/status',
            ['status' => UserStatus::Suspended->value, 'reason' => 'test'],
            $this->authHeaders($support),
        )->assertForbidden();
    }

    public function test_admin_can_suspend_user_and_create_audit_log(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $this->patchJson(
            '/api/v1/admin/users/'.$target->id.'/status',
            ['status' => UserStatus::Suspended->value, 'reason' => 'Policy violation'],
            $this->authHeaders($admin),
        )
            ->assertOk()
            ->assertJsonPath('data.status', UserStatus::Suspended->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.status_changed',
            'entity_type' => 'user',
            'entity_id' => $target->id,
        ]);
    }

    public function test_admin_can_list_and_update_business_status(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $this->getJson('/api/v1/admin/businesses', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure(['data' => ['items']]);

        $this->patchJson(
            '/api/v1/admin/businesses/'.$business->id.'/status',
            ['status' => BusinessStatus::Suspended->value, 'reason' => 'Review'],
            $this->authHeaders($admin),
        )
            ->assertOk()
            ->assertJsonPath('data.status', BusinessStatus::Suspended->value);
    }

    public function test_admin_can_verify_business(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $business = Business::factory()->create();

        $this->patchJson(
            '/api/v1/admin/businesses/'.$business->id.'/verification',
            ['status' => BusinessVerificationStatus::Verified->value, 'note' => 'OK'],
            $this->authHeaders($admin),
        )
            ->assertOk()
            ->assertJsonPath('data.verification_status', BusinessVerificationStatus::Verified->value);
    }

    public function test_admin_can_manage_categories(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

        $this->postJson('/api/v1/admin/categories', [
            'slug' => 'gaming-clubs',
            'name' => ['uz' => 'Oʻyin klublari', 'ru' => 'Игровые клубы', 'kaa' => 'Oyun klubları'],
            'sort_order' => 1,
            'is_active' => true,
        ], $this->authHeaders($admin))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'gaming-clubs');
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $category = BusinessCategory::factory()->create();
        Business::factory()->create(['category_id' => $category->id]);

        $this->deleteJson('/api/v1/admin/categories/'.$category->id, [], $this->authHeaders($admin))
            ->assertUnprocessable();
    }

    public function test_public_config_returns_only_public_settings(): void
    {
        PlatformSetting::query()->create([
            'key' => 'internal_flag',
            'value' => 'secret',
            'type' => 'string',
            'is_public' => false,
        ]);

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.settings.maintenance_mode', false)
            ->assertJsonMissingPath('data.settings.internal_flag');
    }

    public function test_user_cannot_view_another_user_via_admin_api(): void
    {
        $support = User::factory()->create(['platform_role' => PlatformRole::Support]);
        $target = User::factory()->create();

        $this->getJson('/api/v1/admin/users/'.$target->id, $this->authHeaders($support))
            ->assertOk();

        $regular = User::factory()->create(['platform_role' => PlatformRole::User]);
        $this->getJson('/api/v1/admin/users/'.$target->id, $this->authHeaders($regular))
            ->assertForbidden();
    }
}
