<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('confirmation_mode', 16)->default('instant');
            $table->integer('min_duration_minutes')->default(60);
            $table->integer('max_duration_minutes')->default(480);
            $table->integer('duration_step_minutes')->default(60);
            $table->integer('cancellation_deadline_minutes')->default(60);
            $table->integer('pending_expiry_minutes')->default(30);
            $table->integer('check_in_early_minutes')->default(15);
            $table->integer('no_show_grace_minutes')->default(20);
            $table->integer('buffer_minutes')->default(0);
            $table->integer('min_advance_minutes')->default(0);
            $table->integer('max_advance_days')->default(14);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_confirmation_mode_check CHECK (confirmation_mode IN ('instant', 'manual'))");
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_min_duration_check CHECK (min_duration_minutes > 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_max_duration_check CHECK (max_duration_minutes >= min_duration_minutes)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_duration_step_check CHECK (duration_step_minutes > 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_cancellation_deadline_check CHECK (cancellation_deadline_minutes >= 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_pending_expiry_check CHECK (pending_expiry_minutes > 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_check_in_early_check CHECK (check_in_early_minutes >= 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_no_show_grace_check CHECK (no_show_grace_minutes >= 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_buffer_check CHECK (buffer_minutes >= 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_min_advance_check CHECK (min_advance_minutes >= 0)');
        DB::statement('ALTER TABLE booking_policies ADD CONSTRAINT booking_policies_max_advance_check CHECK (max_advance_days > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_policies');
    }
};
