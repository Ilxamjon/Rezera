<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('business_id')->constrained('businesses')->restrictOnDelete();
            $table->uuid('resource_id');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->integer('duration_minutes');
            $table->integer('buffer_minutes_applied')->default(0);
            $table->string('status', 32);
            $table->bigInteger('hourly_rate_amount');
            $table->bigInteger('total_amount');
            $table->char('currency', 3)->default('UZS');
            $table->string('confirmation_mode', 16);
            $table->string('payment_status', 32)->default('pay_at_venue');
            $table->string('payment_method', 32)->default('venue');
            $table->string('notes', 500)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignUuid('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancelled_by_actor_type', 32)->nullable();
            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestampsTz();

            $table->foreign(['resource_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('resources')
                ->restrictOnDelete();

            $table->index(['customer_id', 'start_at']);
            $table->index(['business_id', 'start_at']);
            $table->index(['resource_id', 'start_at']);
        });

        // tstzrange cannot be created via Schema Builder.
        DB::statement('ALTER TABLE reservations ADD COLUMN occupancy_range tstzrange NOT NULL');

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending', 'confirmed', 'rejected', 'cancelled', 'expired', 'checked_in', 'completed', 'no_show'))");
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_end_after_start_check CHECK (end_at > start_at)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_duration_positive_check CHECK (duration_minutes > 0)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_duration_matches_check CHECK (duration_minutes = (EXTRACT(EPOCH FROM (end_at - start_at)) / 60)::integer)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_buffer_check CHECK (buffer_minutes_applied >= 0)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_hourly_rate_check CHECK (hourly_rate_amount >= 0)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_total_amount_check CHECK (total_amount >= 0)');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_confirmation_mode_check CHECK (confirmation_mode IN ('instant', 'manual'))");
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_payment_status_check CHECK (payment_status IN ('unpaid', 'pay_at_venue', 'paid', 'refunded', 'void'))");
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_cancelled_by_actor_check CHECK (cancelled_by_actor_type IS NULL OR cancelled_by_actor_type IN ('customer', 'owner', 'staff', 'admin', 'system'))");

        DB::statement("CREATE INDEX reservations_pending_expiry_idx ON reservations (status, expires_at) WHERE status = 'pending'");
        DB::statement("CREATE INDEX reservations_active_start_idx ON reservations (status, start_at) WHERE status IN ('confirmed', 'checked_in')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reservations_active_start_idx');
        DB::statement('DROP INDEX IF EXISTS reservations_pending_expiry_idx');
        Schema::dropIfExists('reservations');
    }
};
