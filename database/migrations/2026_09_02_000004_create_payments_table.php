<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignUuid('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('payment_number', 32)->unique();
            $table->string('provider', 32);
            $table->string('provider_payment_id', 128)->nullable();
            $table->string('status', 32);
            $table->string('payment_method', 32)->nullable();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('description', 500)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestampsTz();

            $table->index(['business_id', 'created_at']);
            $table->index(['reservation_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('provider');
            $table->index('provider_payment_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'processing', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive_check CHECK (amount > 0)');
        DB::statement("CREATE UNIQUE INDEX payments_one_active_per_reservation_idx ON payments (reservation_id) WHERE status IN ('pending', 'processing')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_one_active_per_reservation_idx');
        Schema::dropIfExists('payments');
    }
};
