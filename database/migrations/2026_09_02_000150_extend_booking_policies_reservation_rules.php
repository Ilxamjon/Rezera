<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_policies', function (Blueprint $table): void {
            $table->boolean('customer_can_cancel')->default(true)->after('cancellation_deadline_minutes');
            $table->boolean('business_can_cancel')->default(true)->after('customer_can_cancel');
            $table->boolean('allow_same_day_reservations')->default(true)->after('max_advance_days');
            $table->unsignedSmallInteger('max_active_reservations_per_customer')->nullable()->after('allow_same_day_reservations');
            $table->unsignedSmallInteger('max_daily_reservations_per_customer')->nullable()->after('max_active_reservations_per_customer');
            $table->boolean('require_customer_note')->default(false)->after('max_daily_reservations_per_customer');
            $table->json('metadata')->nullable()->after('require_customer_note');
        });

        Schema::table('resources', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_duration_minutes')->nullable()->after('hourly_rate_amount');
            $table->unsignedSmallInteger('max_duration_minutes')->nullable()->after('min_duration_minutes');
            $table->unsignedSmallInteger('duration_step_minutes')->nullable()->after('max_duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->nullable()->after('duration_step_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn([
                'min_duration_minutes',
                'max_duration_minutes',
                'duration_step_minutes',
                'buffer_minutes',
            ]);
        });

        Schema::table('booking_policies', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_can_cancel',
                'business_can_cancel',
                'allow_same_day_reservations',
                'max_active_reservations_per_customer',
                'max_daily_reservations_per_customer',
                'require_customer_note',
                'metadata',
            ]);
        });
    }
};
