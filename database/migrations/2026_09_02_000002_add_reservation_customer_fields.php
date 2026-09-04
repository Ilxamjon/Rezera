<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('reservation_number', 32)->nullable()->after('id');
            $table->string('customer_name_snapshot', 120)->nullable()->after('customer_id');
            $table->string('customer_phone_snapshot', 16)->nullable()->after('customer_name_snapshot');
            $table->timestampTz('cancelled_at')->nullable()->after('cancellation_reason');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->unique('reservation_number');
            $table->index(['business_id', 'status', 'start_at']);
            $table->index(['resource_id', 'status', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'status', 'start_at']);
            $table->dropIndex(['resource_id', 'status', 'start_at', 'end_at']);
            $table->dropUnique(['reservation_number']);
            $table->dropColumn([
                'reservation_number',
                'customer_name_snapshot',
                'customer_phone_snapshot',
                'cancelled_at',
            ]);
        });
    }
};
