<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\BusinessMember;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\BuildsWorkingHoursSchedule;

class BusinessWorkingHoursTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use BuildsWorkingHoursSchedule;

    public function test_authorized_user_can_update_weekly_schedule(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/working-hours',
            ['working_hours' => $this->defaultWorkingHoursPayload()],
            $this->authHeaders($owner),
        );

        $response
            ->assertOk()
            ->assertJsonCount(7, 'data');

        $this->assertDatabaseCount('business_hours', 7);
    }

    public function test_unauthorized_user_cannot_update_working_hours(): void
    {
        $business = Business::factory()->create();
        $outsider = User::factory()->create();

        $response = $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/working-hours',
            ['working_hours' => $this->defaultWorkingHoursPayload()],
            $this->authHeaders($outsider),
        );

        $response->assertForbidden();
    }

    public function test_overnight_schedule_is_accepted(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/working-hours',
            ['working_hours' => $this->overnightFridaySchedule()],
            $this->authHeaders($owner),
        );

        $response->assertOk();

        $this->assertDatabaseHas('business_hours', [
            'business_id' => $business->id,
            'weekday' => 5,
            'opens_at' => '20:00:00',
            'closes_at' => '04:00:00',
        ]);
    }

    public function test_update_replaces_existing_schedule_atomically(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        BusinessHour::factory()->create([
            'business_id' => $business->id,
            'weekday' => 1,
        ]);

        $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/working-hours',
            ['working_hours' => $this->defaultWorkingHoursPayload()],
            $this->authHeaders($owner),
        )->assertOk();

        $this->assertDatabaseCount('business_hours', 7);
        $this->assertEquals(1, BusinessHour::query()->where('business_id', $business->id)->where('weekday', 1)->count());
    }

    public function test_staff_cannot_update_working_hours(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/working-hours',
            ['working_hours' => $this->defaultWorkingHoursPayload()],
            $this->authHeaders($staff),
        )->assertForbidden();
    }
}
