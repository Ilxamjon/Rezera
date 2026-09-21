<?php

namespace Tests\Feature\Api\V1\Reservation;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class ReservationManagementTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    public function test_customer_can_list_and_view_own_reservations(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createReservationFor($customer);

        $this->getJson('/api/v1/me/reservations', $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $reservation->id);

        $this->getJson('/api/v1/me/reservations/'.$reservation->id, $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonPath('data.reservation_number', $reservation->reservation_number);
    }

    public function test_customer_cannot_view_another_customers_reservation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $reservation = $this->createReservationFor($owner);

        $this->getJson('/api/v1/me/reservations/'.$reservation->id, $this->authHeaders($intruder))
            ->assertForbidden();
    }

    public function test_customer_can_cancel_confirmed_reservation(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createReservationFor($customer);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/cancel',
            ['reason' => 'Changed plans'],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::Cancelled->value,
            'cancellation_reason' => 'Changed plans',
        ]);
    }

    public function test_owner_can_view_business_reservations(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createReservationFor($customer);
        $owner = User::query()->findOrFail($reservation->business->created_by_user_id);

        $this->getJson(
            '/api/v1/manage/businesses/'.$reservation->business_id.'/reservations',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.customer_phone_snapshot', $customer->phone);
    }

    public function test_owner_can_confirm_pending_reservation(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createReservationFor($customer, ReservationStatus::Pending);
        $owner = User::query()->findOrFail($reservation->business->created_by_user_id);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$reservation->business_id.'/reservations/'.$reservation->id,
            ['status' => ReservationStatus::Confirmed->value],
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Confirmed->value);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createReservationFor($customer, ReservationStatus::Completed);
        $owner = User::query()->findOrFail($reservation->business->created_by_user_id);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$reservation->business_id.'/reservations/'.$reservation->id,
            ['status' => ReservationStatus::Confirmed->value],
            $this->authHeaders($owner),
        )->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_price_snapshot_persists_after_resource_price_change(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createReservationFor($customer);

        Resource::query()->where('id', $reservation->resource_id)->update(['hourly_rate_amount' => 99_999]);

        $this->getJson('/api/v1/me/reservations/'.$reservation->id, $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonPath('data.total_amount', 60_000)
            ->assertJsonPath('data.hourly_rate_amount', 30_000);
    }

    private function createReservationFor(User $customer, ReservationStatus $status = ReservationStatus::Confirmed): Reservation
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $this->seedBusinessHours($business, collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => '10:00',
            'closes_at' => '02:00',
        ])->all());

        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
            'code' => 'PC-'.Str::upper(Str::random(4)),
        ]);

        $start = CarbonImmutable::now('Asia/Tashkent')->addDays(2)->setTime(20, 0)->utc();
        $end = $start->addHours(2);

        return Reservation::factory()->forResource($resource)->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'reservation_number' => 'RZ-'.$start->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 120,
            'status' => $status,
            'hourly_rate_amount' => 30_000,
            'subtotal_amount' => 60_000,
            'discount_amount' => 0,
            'total_amount' => 60_000,
        ]);
    }
}
