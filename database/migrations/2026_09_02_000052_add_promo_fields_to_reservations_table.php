<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->bigInteger('subtotal_amount')->nullable()->after('hourly_rate_amount');
            $table->bigInteger('discount_amount')->default(0)->after('subtotal_amount');
            $table->foreignUuid('promo_code_id')->nullable()->after('discount_amount')->constrained('promo_codes')->nullOnDelete();
            $table->string('promo_code_snapshot', 64)->nullable()->after('promo_code_id');
            $table->string('discount_type', 32)->nullable()->after('promo_code_snapshot');
            $table->bigInteger('discount_value_snapshot')->nullable()->after('discount_type');
        });

        DB::statement('UPDATE reservations SET subtotal_amount = total_amount, discount_amount = 0 WHERE subtotal_amount IS NULL');
        DB::statement('ALTER TABLE reservations ALTER COLUMN subtotal_amount SET NOT NULL');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_discount_amount_check CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_subtotal_amount_check CHECK (subtotal_amount >= 0)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_total_matches_discount_check CHECK (total_amount = GREATEST(subtotal_amount - discount_amount, 0))');
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn([
                'subtotal_amount',
                'discount_amount',
                'promo_code_snapshot',
                'discount_type',
                'discount_value_snapshot',
            ]);
        });
    }
};
