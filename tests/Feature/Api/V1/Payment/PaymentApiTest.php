<?php

namespace Tests\Feature\Api\V1\Payment;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\PaymentStatus as ReservationPaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class PaymentApiTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.providers.mock.enabled' => true,
            'payment.providers.mock.allow_simulation' => true,
            'payment.providers.mock.webhook_secret' => 'test-secret',
            'payment.providers.payme.enabled' => false,
        ]);
    }

    public function test_customer_can_create_payment_with_server_calculated_amount(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Mock->value],
            $this->authHeaders($customer),
        )
            ->assertCreated()
            ->assertJsonPath('data.amount', 60_000)
            ->assertJsonPath('data.currency', 'UZS')
            ->assertJsonPath('data.status', PaymentStatus::Pending->value);

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'user_id' => $customer->id,
            'amount' => 60_000,
            'currency' => 'UZS',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_payment(): void
    {
        $reservation = $this->createPayableReservation(User::factory()->create());

        $this->postJson('/api/v1/me/reservations/'.$reservation->id.'/payments', [
            'provider' => PaymentProvider::Mock->value,
        ])->assertUnauthorized();
    }

    public function test_customer_cannot_pay_another_users_reservation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $reservation = $this->createPayableReservation($owner);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Mock->value],
            $this->authHeaders($intruder),
        )->assertForbidden();
    }

    public function test_disabled_provider_is_rejected(): void
    {
        config(['payment.providers.payme.enabled' => false]);

        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Payme->value],
            $this->authHeaders($customer),
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_provider_disabled');
    }

    public function test_duplicate_active_payment_returns_existing_payment(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer);

        $first = $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Mock->value],
            $this->authHeaders($customer),
        )->assertCreated();

        $second = $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Mock->value],
            $this->authHeaders($customer),
        )->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Payment::query()->where('reservation_id', $reservation->id)->count());
    }

    public function test_mock_webhook_marks_payment_paid_and_confirms_pending_reservation(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer, ReservationStatus::Pending);

        $create = $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Mock->value],
            $this->authHeaders($customer),
        )->assertCreated();

        $payment = Payment::query()->findOrFail($create->json('data.id'));

        $this->postJson('/api/v1/payments/webhooks/mock', [
            'event_id' => 'evt_'.Str::uuid(),
            'provider_payment_id' => $payment->provider_payment_id,
            'status' => PaymentStatus::Paid->value,
        ], [
            'X-Mock-Webhook-Secret' => 'test-secret',
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.processed', true);

        $reservation->refresh();
        $payment->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(ReservationPaymentStatus::Paid, $reservation->payment_status);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    public function test_webhook_is_idempotent(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer);

        $create = $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Mock->value],
            $this->authHeaders($customer),
        )->assertCreated();

        $payment = Payment::query()->findOrFail($create->json('data.id'));
        $payload = [
            'event_id' => 'evt_duplicate',
            'provider_payment_id' => $payment->provider_payment_id,
            'status' => PaymentStatus::Paid->value,
        ];
        $headers = [
            'X-Mock-Webhook-Secret' => 'test-secret',
            'Accept' => 'application/json',
        ];

        $this->postJson('/api/v1/payments/webhooks/mock', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/payments/webhooks/mock', $payload, $headers)->assertOk();

        $this->assertSame(1, \App\Models\PaymentWebhookEvent::query()->where('event_id', 'evt_duplicate')->count());
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this->postJson('/api/v1/payments/webhooks/mock', [
            'event_id' => 'evt_bad_sig',
            'provider_payment_id' => 'mock_missing',
            'status' => PaymentStatus::Paid->value,
        ], [
            'X-Mock-Webhook-Secret' => 'wrong-secret',
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_customer_can_view_own_payment(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer);
        $payment = Payment::factory()->forReservation($reservation)->create([
            'user_id' => $customer->id,
        ]);

        $this->getJson('/api/v1/me/payments/'.$payment->id, $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonPath('data.payment_number', $payment->payment_number)
            ->assertJsonMissingPath('data.metadata');
    }

    public function test_customer_cannot_view_another_users_payment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $reservation = $this->createPayableReservation($owner);
        $payment = Payment::factory()->forReservation($reservation)->create([
            'user_id' => $owner->id,
        ]);

        $this->getJson('/api/v1/me/payments/'.$payment->id, $this->authHeaders($intruder))
            ->assertForbidden();
    }

    public function test_owner_can_list_business_payments(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer);
        $owner = User::query()->findOrFail($reservation->business->created_by_user_id);
        Payment::factory()->forReservation($reservation)->create(['user_id' => $customer->id]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$reservation->business_id.'/payments',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_cross_business_payment_access_is_blocked(): void
    {
        $customer = User::factory()->create();
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $businessA = $this->createBusiness($ownerA);
        $businessB = $this->createBusiness($ownerB);
        $reservation = $this->createPayableReservation($customer, ReservationStatus::Confirmed, $businessB);
        $payment = Payment::factory()->forReservation($reservation)->create(['user_id' => $customer->id]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/payments/'.$payment->id,
            $this->authHeaders($ownerA),
        )->assertNotFound();
    }

    public function test_cannot_pay_cancelled_reservation(): void
    {
        $customer = User::factory()->create();
        $reservation = $this->createPayableReservation($customer, ReservationStatus::Cancelled);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/payments',
            ['provider' => PaymentProvider::Mock->value],
            $this->authHeaders($customer),
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_not_allowed');
    }

    public function test_payment_transition_service_rejects_invalid_transition(): void
    {
        $service = app(\App\Services\Payments\PaymentTransitionService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assertCanTransition(PaymentStatus::Paid, PaymentStatus::Pending);
    }

    private function createBusiness(?User $owner = null): Business
    {
        $owner ??= User::factory()->create();

        $business = Business::factory()->create([
            'created_by_user_id' => $owner->id,
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

        return $business;
    }

    private function createPayableReservation(
        User $customer,
        ReservationStatus $status = ReservationStatus::Confirmed,
        ?Business $business = null,
    ): Reservation {
        $business ??= $this->createBusiness();
        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
            'code' => 'PC-'.Str::upper(Str::random(4)),
        ]);

        $start = CarbonImmutable::parse('2026-09-06 10:00:00', $business->timezone)->utc();
        $end = $start->addHours(2);

        return Reservation::factory()->forResource($resource)->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'reservation_number' => 'RZ-20260906-'.fake()->unique()->numerify('######'),
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 120,
            'status' => $status,
            'payment_status' => ReservationPaymentStatus::PayAtVenue,
            'hourly_rate_amount' => 30_000,
            'subtotal_amount' => 60_000,
            'discount_amount' => 0,
            'total_amount' => 60_000,
        ]);
    }
}
