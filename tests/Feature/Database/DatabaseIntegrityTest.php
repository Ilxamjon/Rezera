<?php

namespace Tests\Feature\Database;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;

class DatabaseIntegrityTest extends PostgresTestCase
{
    public function test_user_phone_must_be_unique(): void
    {
        User::factory()->create(['phone' => '+998901111111']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['phone' => '+998901111111']);
    }

    public function test_resource_active_status_requires_hourly_rate(): void
    {
        $business = Business::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Resource::query()->create([
            'business_id' => $business->id,
            'name' => 'PC-01',
            'code' => 'PC-01',
            'resource_type' => 'pc',
            'status' => 'active',
            'capacity' => 1,
            'hourly_rate_amount' => null,
            'currency' => 'UZS',
            'metadata' => [],
            'sort_order' => 0,
        ]);
    }

    public function test_reservation_end_must_be_after_start(): void
    {
        $business = Business::factory()->create();
        $resource = Resource::factory()->create(['business_id' => $business->id]);
        $customer = User::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Reservation::query()->create([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'start_at' => now()->utc()->addDay()->setTime(21, 0),
            'end_at' => now()->utc()->addDay()->setTime(18, 0),
            'duration_minutes' => 180,
            'buffer_minutes_applied' => 0,
            'status' => 'confirmed',
            'hourly_rate_amount' => 20_000,
            'subtotal_amount' => 60_000,
            'discount_amount' => 0,
            'total_amount' => 60_000,
            'currency' => 'UZS',
            'confirmation_mode' => 'instant',
            'payment_status' => 'pay_at_venue',
            'payment_method' => 'venue',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function test_negative_resource_rate_is_rejected(): void
    {
        $business = Business::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Resource::query()->create([
            'business_id' => $business->id,
            'name' => 'PC-02',
            'code' => 'PC-02',
            'resource_type' => 'pc',
            'status' => 'inactive',
            'capacity' => 1,
            'hourly_rate_amount' => -100,
            'currency' => 'UZS',
            'metadata' => [],
            'sort_order' => 0,
        ]);
    }

    public function test_business_category_multilingual_name_is_stored_as_jsonb(): void
    {
        $category = BusinessCategory::factory()->create([
            'name' => [
                'uz' => 'Kompyuter klubi',
                'kaa' => 'Kompyuter klubi',
                'ru' => 'Компьютерный клуб',
            ],
        ]);

        $row = DB::table('business_categories')->where('id', $category->id)->first();

        $this->assertNotNull($row);
        $decoded = json_decode($row->name, true);
        $this->assertSame('Компьютерный клуб', $decoded['ru']);
    }

    public function test_business_member_relationship_is_enforced(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $business->members()->create([
            'user_id' => $user->id,
            'member_role' => 'owner',
            'status' => 'active',
        ]);

        $this->assertTrue($user->canAccessBusiness($business->id));
    }
}
