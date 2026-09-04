<?php

namespace Database\Factories;

use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Models\BookingPolicy;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<BookingPolicy>
 */
class BookingPolicyFactory extends Factory
{
    protected $model = BookingPolicy::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'confirmation_mode' => ConfirmationMode::Instant,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 480,
            'duration_step_minutes' => 60,
            'cancellation_deadline_minutes' => 60,
            'pending_expiry_minutes' => 30,
            'check_in_early_minutes' => 15,
            'no_show_grace_minutes' => 20,
            'buffer_minutes' => 0,
            'min_advance_minutes' => 0,
            'max_advance_days' => 14,
            'customer_can_cancel' => true,
            'business_can_cancel' => true,
            'allow_same_day_reservations' => true,
            'max_active_reservations_per_customer' => null,
            'max_daily_reservations_per_customer' => null,
            'require_customer_note' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null): BookingPolicy
    {
        if ($this->count !== null) {
            return parent::create($attributes, $parent);
        }

        if (! array_key_exists('business_id', $attributes)) {
            return parent::create($attributes, $parent);
        }

        /** @var BookingPolicy $policy */
        $policy = $this->make($attributes, $parent);
        $values = collect($policy->getAttributes())
            ->except(['id', 'business_id', 'created_at', 'updated_at'])
            ->all();

        return BookingPolicy::query()->updateOrCreate(
            ['business_id' => $policy->business_id],
            $values,
        );
    }
}
