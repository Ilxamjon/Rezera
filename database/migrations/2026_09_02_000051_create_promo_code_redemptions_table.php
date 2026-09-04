<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('promo_code_id')->constrained('promo_codes')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reservation_id')->nullable()->unique()->constrained('reservations')->restrictOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->bigInteger('discount_amount');
            $table->char('currency', 3)->default('UZS');
            $table->string('status', 32);
            $table->timestampTz('redeemed_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'promo_code_id']);
            $table->index('promo_code_id');
        });

        DB::statement("ALTER TABLE promo_code_redemptions ADD CONSTRAINT promo_code_redemptions_status_check CHECK (status IN ('reserved', 'redeemed', 'cancelled'))");
        DB::statement('ALTER TABLE promo_code_redemptions ADD CONSTRAINT promo_code_redemptions_discount_check CHECK (discount_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');
    }
};
