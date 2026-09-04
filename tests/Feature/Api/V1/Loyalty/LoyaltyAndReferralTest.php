<?php

namespace Tests\Feature\Api\V1\Loyalty;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use App\Domain\Loyalty\Enums\LoyaltyTransactionType;
use App\Domain\Referrals\Enums\ReferralStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Events\Reservations\ReservationStatusChanged;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Referrals\ReferralService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class LoyaltyAndReferralTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 12:00:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_business_owner_can_enable_loyalty_program(): void
    {
        [$business, , $owner] = $this->bookableBusinessWithOwner();

        $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/loyalty',
            [
                'name' => 'Club Rewards',
                'is_enabled' => true,
                'earn_rate_points' => 1,
                'earn_amount' => 1000,
            ],
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.name', 'Club Rewards');

        $this->assertDatabaseHas('loyalty_programs', [
            'business_id' => $business->id,
            'is_enabled' => true,
        ]);
    }

    public function test_staff_cannot_update_loyalty_program(): void
    {
        [$business, , , $staff] = $this->bookableBusinessWithOwner(includeStaff: true);

        $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/loyalty',
            ['is_enabled' => true],
            $this->authHeaders($staff),
        )->assertForbidden();
    }

    public function test_completed_reservation_earns_points(): void
    {
        [$business, , $owner] = $this->bookableBusinessWithOwner();
        $customer = User::factory()->create();

        LoyaltyProgram::factory()->create([
            'business_id' => $business->id,
            'is_enabled' => true,
            'earn_rate_points' => 1,
            'earn_amount' => 1000,
        ]);

        $reservation = $this->createReservation($business, $customer, 100_000);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$reservation->id,
            ['status' => ReservationStatus::Completed->value],
            $this->authHeaders($owner),
        )->assertOk();

        $this->assertDatabaseHas('loyalty_transactions', [
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'type' => LoyaltyTransactionType::Earned->value,
            'points' => 100,
            'source_type' => LoyaltyService::SOURCE_RESERVATION,
            'source_id' => $reservation->id,
        ]);

        $this->getJson(
            '/api/v1/me/loyalty?business_id='.$business->id,
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.balance', 100);
    }

    public function test_duplicate_completion_does_not_duplicate_points(): void
    {
        [$business, , $owner] = $this->bookableBusinessWithOwner();
        $customer = User::factory()->create();

        LoyaltyProgram::factory()->create([
            'business_id' => $business->id,
            'is_enabled' => true,
            'earn_rate_points' => 1,
            'earn_amount' => 1000,
        ]);

        $reservation = $this->createReservation($business, $customer, 50_000);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$reservation->id,
            ['status' => ReservationStatus::Completed->value],
            $this->authHeaders($owner),
        )->assertOk();

        Event::dispatch(new ReservationStatusChanged(
            $reservation->fresh(),
            ReservationStatus::Confirmed,
            ReservationStatus::Completed,
            $owner,
        ));

        $this->assertSame(1, LoyaltyTransaction::query()
            ->where('source_type', LoyaltyService::SOURCE_RESERVATION)
            ->where('source_id', $reservation->id)
            ->count());
    }

    public function test_cancelled_reservation_does_not_earn_points(): void
    {
        [$business, , $owner] = $this->bookableBusinessWithOwner();
        $customer = User::factory()->create();

        LoyaltyProgram::factory()->create([
            'business_id' => $business->id,
            'is_enabled' => true,
        ]);

        $reservation = $this->createReservation($business, $customer, 100_000, ReservationStatus::Cancelled);

        Event::dispatch(new ReservationStatusChanged(
            $reservation,
            ReservationStatus::Confirmed,
            ReservationStatus::Cancelled,
            $owner,
        ));

        $this->assertDatabaseMissing('loyalty_transactions', [
            'source_id' => $reservation->id,
            'type' => LoyaltyTransactionType::Earned->value,
        ]);
    }

    public function test_customer_can_redeem_reward(): void
    {
        [$business] = $this->bookableBusinessWithOwner();
        $customer = User::factory()->create();

        LoyaltyProgram::factory()->create([
            'business_id' => $business->id,
            'is_enabled' => true,
        ]);

        LoyaltyAccount::factory()->create([
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'balance' => 1000,
            'lifetime_earned' => 1000,
        ]);

        $reward = LoyaltyReward::factory()->create([
            'business_id' => $business->id,
            'points_cost' => 500,
            'reward_type' => LoyaltyRewardType::Custom,
        ]);

        $response = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/rewards/'.$reward->id.'/redeem',
            [],
            $this->authHeaders($customer),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.points_spent', 500)
            ->assertJsonStructure(['data' => ['redemption_code']]);

        $this->assertSame(500, LoyaltyAccount::query()
            ->where('business_id', $business->id)
            ->where('user_id', $customer->id)
            ->value('balance'));
    }

    public function test_insufficient_balance_rejects_redemption(): void
    {
        [$business] = $this->bookableBusinessWithOwner();
        $customer = User::factory()->create();

        LoyaltyProgram::factory()->create([
            'business_id' => $business->id,
            'is_enabled' => true,
        ]);

        LoyaltyAccount::factory()->create([
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'balance' => 100,
        ]);

        $reward = LoyaltyReward::factory()->create([
            'business_id' => $business->id,
            'points_cost' => 500,
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/rewards/'.$reward->id.'/redeem',
            [],
            $this->authHeaders($customer),
        )->assertUnprocessable();
    }

    public function test_manager_can_adjust_customer_points(): void
    {
        [$business, , $owner] = $this->bookableBusinessWithOwner();
        $customer = User::factory()->create();

        LoyaltyProgram::factory()->create([
            'business_id' => $business->id,
            'is_enabled' => true,
        ]);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/customers/'.$customer->id.'/loyalty/adjust',
            [
                'points' => 200,
                'reason' => 'Customer service compensation',
            ],
            $this->authHeaders($owner),
        )->assertOk();

        $this->assertDatabaseHas('loyalty_accounts', [
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'balance' => 200,
        ]);
    }

    public function test_referral_registration_creates_relationship(): void
    {
        $referrer = User::factory()->create();
        $code = ReferralCode::factory()->create(['user_id' => $referrer->id]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'phone' => '+998901112233',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $code->code,
        ])->assertCreated();

        $referred = User::query()->where('phone', '+998901112233')->first();

        $this->assertDatabaseHas('referrals', [
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'status' => ReferralStatus::Registered->value,
        ]);
    }

    public function test_self_referral_is_rejected(): void
    {
        $user = User::factory()->create();
        $code = ReferralCode::factory()->create(['user_id' => $user->id]);

        $this->expectException(ValidationException::class);

        app(ReferralService::class)->registerReferral($user, $code->code);
    }

    public function test_first_completed_reservation_qualifies_referral(): void
    {
        [$business, , $owner] = $this->bookableBusinessWithOwner();
        $referrer = User::factory()->create();
        $referred = User::factory()->create();
        $code = ReferralCode::factory()->create(['user_id' => $referrer->id]);

        Referral::factory()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'referral_code_id' => $code->id,
            'status' => ReferralStatus::Registered,
        ]);

        $reservation = $this->createReservation($business, $referred, 50_000);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$reservation->id,
            ['status' => ReservationStatus::Completed->value],
            $this->authHeaders($owner),
        )->assertOk();

        $this->assertDatabaseHas('referrals', [
            'referred_user_id' => $referred->id,
            'status' => ReferralStatus::Rewarded->value,
        ]);

        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $referrer->id,
            'type' => LoyaltyTransactionType::Referral->value,
        ]);
    }

    public function test_referral_code_validation_endpoint(): void
    {
        $code = ReferralCode::factory()->create();

        $this->postJson('/api/v1/referrals/validate', ['code' => $code->code])
            ->assertOk()
            ->assertJsonPath('data.valid', true);

        $this->postJson('/api/v1/referrals/validate', ['code' => 'INVALID1'])
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_customer_can_view_referral_summary(): void
    {
        $user = User::factory()->create();
        ReferralCode::factory()->create(['user_id' => $user->id, 'code' => 'TESTCODE']);

        $this->getJson('/api/v1/me/referral', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.code', 'TESTCODE');
    }

    public function test_platform_admin_can_list_referrals(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        Referral::factory()->count(2)->create();

        $this->getJson('/api/v1/admin/referrals', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    /**
     * @return array{0: Business, 1: resource, 2: User}
     */
    private function bookableBusinessWithOwner(bool $includeStaff = false): array
    {
        [$business, $resource] = $this->bookableBusiness();
        $owner = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'member_role' => BusinessMemberRole::Owner,
        ]);

        if ($includeStaff) {
            $staff = User::factory()->create();
            BusinessMember::factory()->create([
                'business_id' => $business->id,
                'user_id' => $staff->id,
                'member_role' => BusinessMemberRole::Staff,
            ]);

            return [$business, $resource, $owner, $staff];
        }

        return [$business, $resource, $owner];
    }

    /**
     * @return array{0: Business, 1: resource}
     */
    private function bookableBusiness(): array
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create([
            'created_by_user_id' => $owner->id,
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $this->seedBusinessHours($business, collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'opens_at' => '10:00',
            'closes_at' => '02:00',
            'is_closed' => false,
        ]));

        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 50_000,
        ]);

        return [$business, $resource];
    }

    private function createReservation(
        Business $business,
        User $customer,
        int $totalAmount,
        ReservationStatus $status = ReservationStatus::Confirmed,
    ): Reservation {
        $resource = Resource::query()->where('business_id', $business->id)->first()
            ?? Resource::factory()->create(['business_id' => $business->id]);

        $start = CarbonImmutable::parse('2026-09-05 18:00:00', $business->timezone)->utc();
        $end = $start->addHours(2);

        return Reservation::factory()->forResource($resource)->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'status' => $status,
            'start_at' => $start,
            'end_at' => $end,
            'total_amount' => $totalAmount,
            'subtotal_amount' => $totalAmount,
            'discount_amount' => 0,
            'currency' => 'UZS',
        ]);
    }
}
